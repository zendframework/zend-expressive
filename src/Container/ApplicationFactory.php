<?php
/**
 * @see       https://github.com/zendframework/zend-expressive for the canonical source repository
 * @copyright Copyright (c) 2015-2016 Zend Technologies USA Inc. (http://www.zend.com)
 * @license   https://github.com/zendframework/zend-expressive/blob/master/LICENSE.md New BSD License
 */

namespace Zend\Expressive\Container;

use ArrayObject;
use Interop\Container\ContainerInterface;
use Zend\Diactoros\Response\EmitterInterface;
use Zend\Expressive\Application;
use Zend\Expressive\Router\FastRouteRouter;
use Zend\Expressive\Router\RouterInterface;
use Zend\Stratigility\FinalHandler;
use Zend\Stratigility\NoopFinalHandler;

/**
 * Factory to use with an IoC container in order to return an Application instance.
 *
 * This factory uses the following services, if available:
 *
 * - 'Zend\Expressive\Router\RouterInterface'. If missing, a FastRoute router
 *   bridge will be instantiated and used.
 * - 'Zend\Stratigility\NoopFinalHandler'. The service should be a callable to use as
 *   the default delegate when the middleware pipeline is exhausted. The factory will
 *   create an empty instance if none is found.
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

        $delegate = $this->marshalNoopFinalHandler($container);

        $emitter = $container->has(EmitterInterface::class)
            ? $container->get(EmitterInterface::class)
            : null;

        $app = new Application($router, $container, $delegate, $emitter);

        if (empty($config['zend-expressive']['programmatic_pipeline'])) {
            $this->injectRoutesAndPipeline($app, $config);
        }

        return $app;
    }

    /**
     * Injects routes and the middleware pipeline into the application.
     *
     * @param Application $app
     * @param array $config
     */
    private function injectRoutesAndPipeline(Application $app, array $config)
    {
        $app->injectRoutesFromConfig($config);
        $app->injectPipelineFromConfig($config);
    }

    /**
     * @param ContainerInterface $container
     * @return callable|NoopFinalHandler
     */
    private function marshalNoopFinalHandler(ContainerInterface $container)
    {
        return $container->has(NoopFinalHandler::class)
            ? $container->get(NoopFinalHandler::class)
            : new NoopFinalHandler();
    }
}
