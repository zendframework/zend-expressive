<?php
/**
 * @see       http://github.com/zendframework/zend-expressive for the canonical source repository
 * @copyright Copyright (c) 2015-2016 Zend Technologies USA Inc. (http://www.zend.com)
 * @license   http://framework.zend.com/license/new-bsd New BSD License
 */

namespace Zend\Expressive;

use ReflectionProperty;
use SplPriorityQueue;
use Zend\Expressive\Router\Route;

class ApplicationUtils
{
    /**
     * Non-instantiable
     */
    private function __construct()
    {
    }

    /**
     * Inject a middleware pipeline from the middleware_pipeline configuration.
     *
     * Inspects the configuration provided to determine if a middleware pipeline
     * exists to inject in the application.
     *
     * If no pipeline is defined, but routes *are*, then the method will inject
     * the routing and dispatch middleware.
     *
     * Use the following configuration format:
     *
     * <code>
     * return [
     *     'middleware_pipeline' => [
     *         // An array of middleware to register with the pipeline.
     *         // entries to register prior to routing/dispatching...
     *         Zend\Expressive\Container\ApplicationFactory::ROUTING_MIDDLEWARE,
     *         Zend\Expressive\Container\ApplicationFactory::DISPATCH_MIDDLEWARE,
     *         // entries to register after routing/dispatching...
     *     ],
     * ];
     * </code>
     *
     * Each item in the middleware_pipeline array (with the exception of the routing
     * and dispatch middleware entries) must be of the following specification:
     *
     * <code>
     * [
     *     // required:
     *     'middleware' => 'Name of middleware service, or a callable',
     *     // optional:
     *     'path'  => '/path/to/match',
     *     'error' => true,
     *     'priority' => 1, // integer
     * ]
     * </code>
     *
     * Note that the `path` element can only be a literal.
     *
     * `error` indicates whether or not the middleware represents error
     * middleware; this is done so that Expressive can lazy-load an error
     * middleware service (more below). Omitting `error` or setting it to a
     * non-true value is the default, indicating the middleware is standard
     * middleware.
     *
     * `priority` is used to shape the order in which middleware is piped to the
     * application. Values are integers, with high values having higher priority
     * (piped earlier), and low/negative values having lower priority (piped last).
     * Default priority if none is specified is 1. Middleware with the same
     * priority are piped in the order in which they appear.
     *
     * Middleware piped may be either callables or service names. If you specify
     * the middleware's `error` flag as `true`, the middleware will be piped using
     * `Application::pipeErrorHandler()` instead of `Application::pipe()`.
     *
     * Additionally, you can specify an array of callables or service names as
     * the `middleware` value of a specification. Internally, this will create
     * a `Zend\Stratigility\MiddlewarePipe` instance, with the middleware
     * specified piped in the order provided.
     *
     * Please note: error middleware is deprecated starting with the 1.1 release.
     *
     * @param Application $application
     * @param array $config
     * @return void
     */
    public static function injectPipelineFromConfig(Application $application, array $config)
    {
        if (empty($config['middleware_pipeline'])) {
            if (! isset($config['routes']) || ! is_array($config['routes'])) {
                return;
            }

            $application->pipeRoutingMiddleware();
            $application->pipeDispatchMiddleware();
            return;
        }

        // Create a priority queue from the specifications
        $queue = array_reduce(
            array_map(self::createCollectionMapper($application), $config['middleware_pipeline']),
            self::createPriorityQueueReducer(),
            new SplPriorityQueue()
        );

        foreach ($queue as $spec) {
            $path  = isset($spec['path']) ? $spec['path'] : '/';
            $error = array_key_exists('error', $spec) ? (bool) $spec['error'] : false;
            $pipe  = $error ? 'pipeErrorHandler' : 'pipe';

            $application->{$pipe}($path, $spec['middleware']);
        }
    }

    /**
     * Inject routes from  configuration.
     *
     * Introspects the provided configuration for routes to inject in the
     * application instance.
     *
     * The following configuration structure can be used to define routes:
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
     * @param Application $application
     * @param array $config
     * @return void
     */
    public static function injectRoutesFromConfig(Application $application, array $config)
    {
        if (! isset($config['routes']) || ! is_array($config['routes'])) {
            return;
        }

        foreach ($config['routes'] as $spec) {
            if (! isset($spec['path']) || ! isset($spec['middleware'])) {
                continue;
            }

            if (isset($spec['allowed_methods'])) {
                $methods = $spec['allowed_methods'];
                if (! is_array($methods)) {
                    throw new Container\Exception\InvalidArgumentException(sprintf(
                        'Allowed HTTP methods for a route must be in form of an array; received "%s"',
                        gettype($methods)
                    ));
                }
            } else {
                $methods = Route::HTTP_METHOD_ANY;
            }
            $name    = isset($spec['name']) ? $spec['name'] : null;
            $route   = new Route($spec['path'], $spec['middleware'], $methods, $name);

            if (isset($spec['options'])) {
                $options = $spec['options'];
                if (! is_array($options)) {
                    throw new Container\Exception\InvalidArgumentException(sprintf(
                        'Route options must be an array; received "%s"',
                        gettype($options)
                    ));
                }

                $route->setOptions($options);
            }

            $application->route($route);
        }
    }

    /**
     * Create and return the pipeline map callback.
     *
     * The returned callback has the signature:
     *
     * <code>
     * function ($item) : callable|string
     * </code>
     *
     * It is suitable for mapping pipeline middleware representing the application
     * routing o dispatching middleware to a callable; if the provided item does not
     * match either, the item is returned verbatim.
     *
     * @todo Remove ROUTE_RESULT_OBSERVER_MIDDLEWARE detection for 1.1
     * @param Application $app
     * @return callable
     */
    private static function createPipelineMapper(Application $app)
    {
        return function ($item) use ($app) {
            if ($item === Container\ApplicationFactory::ROUTING_MIDDLEWARE) {
                return [$app, 'routeMiddleware'];
            }

            if ($item === Container\ApplicationFactory::DISPATCH_MIDDLEWARE) {
                return [$app, 'dispatchMiddleware'];
            }

            if ($item === Container\ApplicationFactory::ROUTE_RESULT_OBSERVER_MIDDLEWARE) {
                $r = new ReflectionProperty($app, 'routeResultObserverMiddlewareIsRegistered');
                $r->setAccessible(true);
                $r->setValue($app, true);
                return [$app, 'routeResultObserverMiddleware'];
            }

            return $item;
        };
    }

    /**
     * Create the collection mapping function.
     *
     * Returns a callable with the following signature:
     *
     * <code>
     * function (array|string $item) : array
     * </code>
     *
     * When it encounters one of the self::*_MIDDLEWARE constants, it passes
     * the value to the `createPipelineMapper()` callback to create a spec
     * that uses the return value as pipeline middleware.
     *
     * If the 'middleware' value is an array, it uses the `createPipelineMapper()`
     * callback as an array mapper in order to ensure the self::*_MIDDLEWARE
     * are injected correctly.
     *
     * If the 'middleware' value is missing, or not viable as middleware, it
     * raises an exception, to ensure the pipeline is built correctly.
     *
     * @param Application $app
     * @return callable
     */
    private static function createCollectionMapper(Application $app)
    {
        $pipelineMap = self::createPipelineMapper($app);
        $appMiddlewares = [
            Container\ApplicationFactory::ROUTING_MIDDLEWARE,
            Container\ApplicationFactory::DISPATCH_MIDDLEWARE,
            Container\ApplicationFactory::ROUTE_RESULT_OBSERVER_MIDDLEWARE
        ];

        return function ($item) use ($app, $pipelineMap, $appMiddlewares) {
            if (in_array($item, $appMiddlewares, true)) {
                return ['middleware' => $pipelineMap($item)];
            }

            if (! is_array($item) || ! array_key_exists('middleware', $item)) {
                throw new Container\Exception\InvalidArgumentException(sprintf(
                    'Invalid pipeline specification received; must be an array containing a middleware '
                    . 'key, or one of the ApplicationFactory::*_MIDDLEWARE constants; received %s',
                    (is_object($item) ? get_class($item) : gettype($item))
                ));
            }

            if (! is_callable($item['middleware']) && is_array($item['middleware'])) {
                $item['middleware'] = array_map($pipelineMap, $item['middleware']);
            }

            return $item;
        };
    }

    /**
     * Create reducer function that will reduce an array to a priority queue.
     *
     * Creates and returns a function with the signature:
     *
     * <code>
     * function (SplQueue $queue, array $item) : SplQueue
     * </code>
     *
     * The function is useful to reduce an array of pipeline middleware to a
     * priority queue.
     *
     * @return callable
     */
    private static function createPriorityQueueReducer()
    {
        // $serial is used to ensure that items of the same priority are enqueued
        // in the order in which they are inserted.
        $serial = PHP_INT_MAX;
        return function ($queue, $item) use (&$serial) {
            $priority = isset($item['priority']) && is_int($item['priority'])
                ? $item['priority']
                : 1;
            $queue->insert($item, [$priority, $serial--]);
            return $queue;
        };
    }
}