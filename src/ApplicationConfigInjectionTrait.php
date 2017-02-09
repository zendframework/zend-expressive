<?php
/**
 * @see       https://github.com/zendframework/zend-expressive for the canonical source repository
 * @copyright Copyright (c) 2016-2017 Zend Technologies USA Inc. (http://www.zend.com)
 * @license   https://github.com/zendframework/zend-expressive/blob/master/LICENSE.md New BSD License
 */

namespace Zend\Expressive;

use SplPriorityQueue;
use Zend\Expressive\Router\Route;

trait ApplicationConfigInjectionTrait
{
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
     *         Application::ROUTING_MIDDLEWARE,
     *         Application::DISPATCH_MIDDLEWARE,
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
     *     'priority' => 1, // integer
     * ]
     * </code>
     *
     * Note that the `path` element can only be a literal.
     *
     * `priority` is used to shape the order in which middleware is piped to the
     * application. Values are integers, with high values having higher priority
     * (piped earlier), and low/negative values having lower priority (piped last).
     * Default priority if none is specified is 1. Middleware with the same
     * priority are piped in the order in which they appear.
     *
     * Middleware piped may be either callables or service names.
     *
     * Additionally, you can specify an array of callables or service names as
     * the `middleware` value of a specification. Internally, this will create
     * a `Zend\Stratigility\MiddlewarePipe` instance, with the middleware
     * specified piped in the order provided.
     *
     * @param null|array $config If null, attempts to pull the 'config' service
     *     from the composed container.
     * @return void
     */
    public function injectPipelineFromConfig(array $config = null)
    {
        if (null === $config) {
            $config = $this->container->has('config') ? $this->container->get('config') : [];
        }

        if (empty($config['middleware_pipeline'])) {
            if (! isset($config['routes']) || ! is_array($config['routes'])) {
                return;
            }

            $this->pipeRoutingMiddleware();
            $this->pipeDispatchMiddleware();
            return;
        }

        // Create a priority queue from the specifications
        $queue = array_reduce(
            array_map($this->createCollectionMapper(), $config['middleware_pipeline']),
            $this->createPriorityQueueReducer(),
            new SplPriorityQueue()
        );

        foreach ($queue as $spec) {
            $path = isset($spec['path']) ? $spec['path'] : '/';
            $this->pipe($path, $spec['middleware']);
        }
    }

    /**
     * Inject routes from configuration.
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
     *             'allowed_methods' => ['GET', 'POST', 'PATCH'],
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
     * @param null|array $config If null, attempts to pull the 'config' service
     *     from the composed container.
     * @return void
     * @throws Exception\InvalidArgumentException
     */
    public function injectRoutesFromConfig(array $config = null)
    {
        if (null === $config) {
            $config = $this->container->has('config') ? $this->container->get('config') : [];
        }

        if (! isset($config['routes']) || ! is_array($config['routes'])) {
            return;
        }

        foreach ($config['routes'] as $spec) {
            if (! isset($spec['path']) || ! isset($spec['middleware'])) {
                continue;
            }

            $methods = Route::HTTP_METHOD_ANY;
            if (isset($spec['allowed_methods'])) {
                $methods = $spec['allowed_methods'];
                if (! is_array($methods)) {
                    throw new Exception\InvalidArgumentException(sprintf(
                        'Allowed HTTP methods for a route must be in form of an array; received "%s"',
                        gettype($methods)
                    ));
                }
            }

            $name  = isset($spec['name']) ? $spec['name'] : null;
            $route = new Route($spec['path'], $spec['middleware'], $methods, $name);

            if (isset($spec['options'])) {
                $options = $spec['options'];
                if (! is_array($options)) {
                    throw new Exception\InvalidArgumentException(sprintf(
                        'Route options must be an array; received "%s"',
                        gettype($options)
                    ));
                }

                $route->setOptions($options);
            }

            $this->route($route);
        }
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
     * @return callable
     * @throws Exception\InvalidArgumentException
     */
    private function createCollectionMapper()
    {
        $appMiddlewares = [
            Application::ROUTING_MIDDLEWARE,
            Application::DISPATCH_MIDDLEWARE,
        ];

        return function ($item) use ($appMiddlewares) {
            if (in_array($item, $appMiddlewares, true)) {
                return ['middleware' => $item];
            }

            if (! is_array($item) || ! array_key_exists('middleware', $item)) {
                throw new Exception\InvalidArgumentException(sprintf(
                    'Invalid pipeline specification received; must be an array containing a middleware '
                    . 'key, or one of the Application::*_MIDDLEWARE constants; received %s',
                    is_object($item) ? get_class($item) : gettype($item)
                ));
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
    private function createPriorityQueueReducer()
    {
        // $serial is used to ensure that items of the same priority are enqueued
        // in the order in which they are inserted.
        $serial = PHP_INT_MAX;
        return function ($queue, $item) use (&$serial) {
            $priority = isset($item['priority']) && is_int($item['priority'])
                ? $item['priority']
                : 1;
            $queue->insert($item, [$priority, $serial]);
            $serial -= 1;
            return $queue;
        };
    }
}
