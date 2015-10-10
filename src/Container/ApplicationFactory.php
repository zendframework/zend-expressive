<?php
/**
 * Zend Framework (http://framework.zend.com/)
 *
 * @see       https://github.com/zendframework/zend-expressive for the canonical source repository
 * @copyright Copyright (c) 2015 Zend Technologies USA Inc. (http://www.zend.com)
 * @license   https://github.com/zendframework/zend-expressive/blob/master/LICENSE.md New BSD License
 */

namespace Zend\Expressive\Container;

use Interop\Container\ContainerInterface;
use Zend\Diactoros\Response\EmitterInterface;
use Zend\Expressive\Application;
use Zend\Expressive\Exception;
use Zend\Expressive\Router\FastRouteRouter;
use Zend\Expressive\Router\Route;
use Zend\Expressive\Router\RouterInterface;

/**
 * Factory to use with an IoC container in order to return an Application instance.
 *
 * This factory uses the following services, if available:
 *
 * - 'Zend\Expressive\Router\RouterInterface'. If missing, a FastRoute router
 *   bridge will be instantiated and used.
 * - 'Zend\Expressive\FinalHandler'. The service should be a callable to use as
 *   the final handler when the middleware pipeline is exhausted.
 * - 'Zend\Diactoros\Response\EmitterInterface'. If missing, an EmitterStack is
 *   created, adding a SapiEmitter to the bottom of the stack.
 * - 'config' (an array or ArrayAccess object). If present, and it contains route
 *   definitions, these will be used to seed routes in the Application instance
 *   before returning it.
 *
 * When introspecting the `config` service, the following structure can be used
 * to define routes:
 *
 * <code>
 * return [
 *     'routes' => [
 *         [
 *             'path' => '/path/to/match',
 *             'middleware' => 'Middleware Service Name or Callable',
 *             'allowed_methods' => [ 'GET', 'POST', 'PATCH' ],
 *             'options' => [
 *                 'stuff' => 'to',
 *                 'pass'  => 'to',
 *                 'the'   => 'underlying router',
 *             ],
 *         ],
 *         // etc.
 *     ],
 * ];
 * </code>
 *
 * Each route MUST have a path and middleware key at the minimum.
 *
 * The "allowed_methods" key may be omitted, can be either an array or the
 * value of the Zend\Expressive\Router\Route::HTTP_METHOD_ANY constant; any
 * valid HTTP method token is allowed, which means you can specify custom HTTP
 * methods as well.
 *
 * The "options" key may also be omitted, and its interpretation will be
 * dependent on the underlying router used.
 *
 * Furthermore, you can define middleware to pipe to the application to run on
 * every invocation (assuming they match and/or other middleware does not
 * return a response earlier). Use the following configuration:
 *
 * <code>
 * return [
 *     'middleware_pipeline' => [
 *         // An array of middleware to register prior to registration of the
 *         // routing middleware:
 *         'pre_routing' => [
 *         ],
 *         // An array of middleware to register after registration of the
 *         // routing middleware:
 *         'post_routing' => [
 *         ],
 *     ],
 * ];
 * </code>
 *
 * Each item in either the `pre_routing` or `post_routing` array must be an
 * array with the following specification:
 *
 * <code>
 * [
 *     // required:
 *     'middleware' => 'Name of middleware service, or a callable',
 *     // optional:
 *     'path'  => '/path/to/match',
 *     'error' => true,
 * ]
 * </code>
 *
 * Note that the `path` element can only be a literal. `error` indicates
 * whether or not the middleware represents error middleware; this is done
 * so that Expressive can lazy-load an error middleware service (more below).
 * Omitting `error` or setting it to a non-true value is the default,
 * indicating the middleware is standard middleware.
 *
 * Middleware are pipe()'d to the application instance in the order in which
 * they appear. "pre_routing" middleware will execute before the application's
 * routing middleware, while "post_routing" middleware will execute afterwards.
 *
 * Middleware piped may be either callables or service names. If you specify
 * the middleware's `error` flag as `true`, the middleware will be piped using
 * Application::pipeErrorHandler() instead of Application::pipe().
 */
class ApplicationFactory
{
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
        $router = $container->has(RouterInterface::class)
            ? $container->get(RouterInterface::class)
            : new FastRouteRouter();

        $finalHandler = $container->has('Zend\Expressive\FinalHandler')
            ? $container->get('Zend\Expressive\FinalHandler')
            : null;

        $emitter = $container->has(EmitterInterface::class)
            ? $container->get(EmitterInterface::class)
            : null;

        $app = new Application($router, $container, $finalHandler, $emitter);

        $this->injectPreMiddleware($app, $container);
        $this->injectRoutes($app, $container);
        $this->injectPostMiddleware($app, $container);

        return $app;
    }

    /**
     * Inject routes from configuration, if any.
     *
     * @param Application $app
     * @param ContainerInterface $container
     */
    private function injectRoutes(Application $app, ContainerInterface $container)
    {
        $config = $container->has('config') ? $container->get('config') : [];
        if (! isset($config['routes'])) {
            $app->pipeRoutingMiddleware();
            return;
        }

        foreach ($config['routes'] as $spec) {
            if (! isset($spec['path']) || ! isset($spec['middleware'])) {
                continue;
            }

            $methods = (isset($spec['allowed_methods']) && is_array($spec['allowed_methods']))
                ? $spec['allowed_methods']
                : null;
            $name    = isset($spec['name']) ? $spec['name'] : null;
            $methods = (null === $methods) ? Route::HTTP_METHOD_ANY : $methods;
            $route   = new Route($spec['path'], $spec['middleware'], $methods, $name);

            if (isset($spec['options']) && is_array($spec['options'])) {
                $route->setOptions($spec['options']);
            }

            $app->route($route);
        }
    }

    /**
     * Given a collection of middleware specifications, pipe them to the application.
     *
     * @param array $collection
     * @param Application $app
     * @param ContainerInterface $container
     * @throws Exception\InvalidMiddlewareException for invalid middleware.
     */
    private function injectMiddleware(array $collection, Application $app, ContainerInterface $container)
    {
        foreach ($collection as $spec) {
            if (! array_key_exists('middleware', $spec)) {
                continue;
            }

            $path       = isset($spec['path']) ? $spec['path'] : '/';
            $middleware = $spec['middleware'];
            $error      = array_key_exists('error', $spec) ? (bool) $spec['error'] : false;
            $pipe       = $error ? 'pipeErrorHandler' : 'pipe';

            $app->{$pipe}($path, $middleware);
        }
    }

    /**
     * Inject middleware to pipe before the routing middleware.
     *
     * Pre-routing middleware is specified as the configuration subkey
     * middleware_pipeline.pre_routing.
     *
     * @param Application $app
     * @param ContainerInterface $container
     */
    private function injectPreMiddleware(Application $app, ContainerInterface $container)
    {
        $config = $container->has('config') ? $container->get('config') : [];
        if (! isset($config['middleware_pipeline']['pre_routing']) ||
            ! is_array($config['middleware_pipeline']['pre_routing'])
        ) {
            return;
        }

        $this->injectMiddleware($config['middleware_pipeline']['pre_routing'], $app, $container);
    }

    /**
     * Inject middleware to pipe after the routing middleware.
     *
     * Post-routing middleware is specified as the configuration subkey
     * middleware_pipeline.post_routing.
     *
     * @param Application $app
     * @param ContainerInterface $container
     */
    private function injectPostMiddleware(Application $app, ContainerInterface $container)
    {
        $config = $container->has('config') ? $container->get('config') : [];
        if (! isset($config['middleware_pipeline']['post_routing']) ||
            ! is_array($config['middleware_pipeline']['post_routing'])
        ) {
            return;
        }

        $this->injectMiddleware($config['middleware_pipeline']['post_routing'], $app, $container);
    }
}
