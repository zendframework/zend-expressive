<?php
/**
 * @see       https://github.com/zendframework/zend-expressive for the canonical source repository
 * @copyright Copyright (c) 2015-2017 Zend Technologies USA Inc. (http://www.zend.com)
 * @license   https://github.com/zendframework/zend-expressive/blob/master/LICENSE.md New BSD License
 */

namespace Zend\Expressive\Container;

use ArrayObject;
use Interop\Http\ServerMiddleware\MiddlewareInterface;
use Psr\Container\ContainerInterface;
use Psr\Http\Message\ResponseInterface;
use ReflectionProperty;
use Zend\Diactoros\Response\EmitterInterface;
use Zend\Expressive\Application;
use Zend\Expressive\Router\FastRouteRouter;
use Zend\Expressive\Router\Middleware\DispatchMiddleware;
use Zend\Expressive\Router\Middleware\RouteMiddleware;
use Zend\Expressive\Router\RouterInterface;

/**
 * Factory to use with an IoC container in order to return an Application instance.
 *
 * This factory uses the following services, if available:
 *
 * - 'Zend\Expressive\Router\RouterInterface'. If missing, a FastRoute router
 *   bridge will be instantiated and used.
 * - 'Zend\Expressive\Delegate\DefaultDelegate'. The service should be
 *   either a `Interop\Http\ServerMiddleware\DelegateInterface` instance, or
 *   a callable that accepts a request and optionally a response; the instance
 *   will be used as the default delegate when the middleware pipeline is
 *   exhausted. If none is provided, `Zend\Expressive\Application` will create
 *   a `Zend\Expressive\Delegate\NotFoundDelegate` instance using the response
 *   prototype only.
 * - 'Zend\Diactoros\Response\EmitterInterface'. If missing, an EmitterStack is
 *   created, adding a SapiEmitter to the bottom of the stack.
 * - 'config' (an array or ArrayAccess object). If present, and it contains route
 *   definitions, these will be used to seed routes in the Application instance
 *   before returning it.
 *
 * Please see `Zend\Expressive\ApplicationConfigInjectionTrait` for details on how
 * to provide routing and middleware pipeline configuration; this factory
 * delegates to the methods in that trait in order to seed the
 * `Application` instance (which composes the trait).
 *
 * You may disable injection of configured routing and the middleware pipeline
 * by enabling the `zend-expressive.programmatic_pipeline` configuration flag.
 */
class ApplicationFactory
{
    const DISPATCH_MIDDLEWARE = Application::DISPATCH_MIDDLEWARE;
    const ROUTING_MIDDLEWARE = Application::ROUTING_MIDDLEWARE;

    /**
     * Create and return an Application instance.
     *
     * See the class level docblock for information on what services this
     * factory will optionally consume.
     *
     * @param ContainerInterface $container
     * @return Application
     */
    public function __invoke(ContainerInterface $container)
    {
        $config = $container->has('config') ? $container->get('config') : [];
        $config = $config instanceof ArrayObject ? $config->getArrayCopy() : $config;

        $router = $container->has(RouterInterface::class)
            ? $container->get(RouterInterface::class)
            : new FastRouteRouter();

        $delegate = $container->has('Zend\Expressive\Delegate\DefaultDelegate')
            ? $container->get('Zend\Expressive\Delegate\DefaultDelegate')
            : null;

        $emitter = $container->has(EmitterInterface::class)
            ? $container->get(EmitterInterface::class)
            : null;

        $app = new Application($router, $container, $delegate, $emitter);

        if (empty($config['zend-expressive']['programmatic_pipeline'])) {
            $this->injectRoutesAndPipeline($container, $router, $app, $config);
        }

        return $app;
    }

    /**
     * Injects routes and the middleware pipeline into the application.
     *
     * @return void
     */
    private function injectRoutesAndPipeline(
        ContainerInterface $container,
        RouterInterface $router,
        Application $app,
        array $config
    ) {
        if (empty($config['middleware_pipeline'])
            && (! isset($config['routes']) || ! is_array($config['routes']))
        ) {
            return;
        }

        if (empty($config['middleware_pipeline']) && isset($config['routes']) && is_array($config['routes'])) {
            $app->pipe($this->getRoutingMiddleware($container, $router, $app));
            $app->pipe($this->getDispatchMiddleware($container, $app));
        }

        ApplicationConfigInjectionDelegator::injectRoutesFromConfig($app, $config);
        ApplicationConfigInjectionDelegator::injectPipelineFromConfig($app, $config);
    }

    /**
     * Discovers or creates the route middleware.
     *
     * If the RouteMiddleware is present in the container, it returns the
     * service.
     *
     * Otherwise, it creates RouteMiddleware using the router being composed in
     * the application, along with a response prototype.
     *
     * @return MiddlewareInterface
     */
    private function getRoutingMiddleware(ContainerInterface $container, RouterInterface $router, Application $app)
    {
        if ($container->has(RouteMiddleware::class)) {
            return $container->get(RouteMiddleware::class);
        }

        return new RouteMiddleware(
            $router,
            $this->getResponsePrototype($container, $app)
        );
    }

    /**
     * Discover or create the dispatch middleware.
     *
     * If the DispatchMiddleware is present in the application's container, it
     * returns the service. Otherwise, instantiates and returns it directly.
     *
     * @return MiddlewareInterface
     */
    private function getDispatchMiddleware(ContainerInterface $container, Application $app)
    {
        return $container->has(DispatchMiddleware::class)
            ? $container->get(DispatchMiddleware::class)
            : new DispatchMiddleware();
    }

    /**
     * Get the response prototype.
     *
     * If not available in the container, uses reflection to pull it from the
     * application.
     *
     * If in the container, fetches it. If the value is callable, uses it as
     * a factory to generate and return the response.
     *
     * @return ResponseInterface
     */
    private function getResponsePrototype(ContainerInterface $container, Application $app)
    {
        if (! $container->has(ResponseInterface::class)) {
            $r = new ReflectionProperty($app, 'responsePrototype');
            $r->setAccessible(true);
            return $r->getValue($app);
        }

        $response = $container->get(ResponseInterface::class);
        return is_callable($response) ? $response() : $response;
    }
}
