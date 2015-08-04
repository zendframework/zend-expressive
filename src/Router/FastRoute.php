<?php
/**
 * Zend Framework (http://framework.zend.com/)
 *
 * @see       http://github.com/zendframework/zend-diactoros for the canonical source repository
 * @copyright Copyright (c) 2015 Zend Technologies USA Inc. (http://www.zend.com)
 * @license   https://github.com/zendframework/zend-diactoros/blob/master/LICENSE.md New BSD License
 */

namespace Zend\Expressive\Router;

use FastRoute\DataGenerator\GroupCountBased as RouteGenerator;
use FastRoute\Dispatcher\GroupCountBased as Dispatcher;
use FastRoute\RouteCollector;
use FastRoute\RouteParser\Std as RouteParser;
use Psr\Http\Message\ServerRequestInterface as Request;

/**
 * Router implementation bridging nikic/fast-route.
 */
class FastRoute implements RouterInterface
{
    /**
     * @var callable A factory callback that can return a dispatcher.
     */
    private $dispatcherCallback;

    /**
     * FastRoute router
     *
     * @var FastRoute\RouteCollector
     */
    private $router;

    /**
     * All attached routes as Route instances
     *
     * @var Route[]
     */
    private $routes;

    /**
     * Constructor
     *
     * Accepts optionally a FastRoute RouteCollector and a callable factory
     * that can return a FastRoute dispatcher.
     *
     * If either is not provided defaults will be used:
     *
     * - A RouteCollector instance will be created composing a RouteParser and
     *   RouteGenerator.
     * - A callable that returns a GroupCountBased dispatcher will be created.
     *
     * @param null|RouteCollector $router If not provided, a default
     *     implementation will be used.
     * @param null|callable $dispatcherFactory Callable that will return a
     *     FastRoute dispatcher.
     */
    public function __construct(RouteCollector $router = null, callable $dispatcherFactory = null)
    {
        if (null === $router) {
            $router = $this->createRouter();
        }

        $this->router = $router;
        $this->dispatcherCallback = $dispatcherFactory;
    }

    /**
     * Add a route to the collection.
     *
     * Uses the HTTP methods associated (creating sane defaults for an empty
     * list or Route::HTTP_METHOD_ANY) and the path, and uses the path as
     * the name (to allow later lookup of the middleware).
     *
     * @param Route $route
     */
    public function addRoute(Route $route)
    {
        $methods = $route->getAllowedMethods();

        if ($methods === Route::HTTP_METHOD_ANY) {
            $methods = ['GET', 'POST', 'PUT', 'PATCH', 'DELETE', 'HEAD', 'OPTIONS', 'TRACE'];
        }

        if (empty($methods)) {
            $methods = ['GET', 'HEAD', 'OPTIONS'];
        }

        $this->router->addRoute($methods, $route->getPath(), $route->getPath());
        $this->routes[] = $route;
    }

    /**
     * @param  Request $request
     * @return boolean
     */
    public function match(Request $request)
    {
        $path       = $request->getUri()->getPath();
        $method     = $request->getMethod();
        $dispatcher = $this->getDispatcher($this->router->getData());
        $result     = $dispatcher->dispatch($method, $path);

        if ($result[0] != Dispatcher::FOUND) {
            return $this->marshalFailedRoute($result);
        }

        return $this->marshalMatchedRoute($result, $method);
    }

    /**
     * Create a default FastRoute Collector instance
     *
     * @return RouteCollector
     */
    private function createRouter()
    {
        return new RouteCollector(new RouteParser, new RouteGenerator);
    }

    /**
     * Retrieve the dispatcher instance.
     *
     * Uses the callable factory in $dispatcherCallback, passing it $data
     * (which should be derived from the router's getData() method); this
     * approach is done to allow testing against the dispatcher.
     *
     * @param  array|object $data Data from RouteCollection::getData()
     * @return Dispatcher
     */
    private function getDispatcher($data)
    {
        if (! $this->dispatcherCallback) {
            $this->dispatcherCallback = $this->createDispatcherCallback();
        }

        $factory = $this->dispatcherCallback;
        return $factory($data);
    }

    /**
     * Return a default implemententation of a callback that can return a Dispatcher.
     *
     * @return callable
     */
    private function createDispatcherCallback()
    {
        return function ($data) {
            return new Dispatcher($data);
        };
    }

    /**
     * Marshal a routing failure result.
     *
     * If the failure was due to the HTTP method, passes the allowed HTTP
     * methods to the factory.
     *
     * @return RouteResult
     */
    private function marshalFailedRoute(array $result)
    {
        if ($result[0] === Dispatcher::METHOD_NOT_ALLOWED) {
            return RouteResult::fromRouteFailure($result[1]);
        }
        return RouteResult::fromRouteFailure();
    }

    /**
     * Marshals a route result based on the results of matching and the current HTTP method.
     *
     * @param array $result
     * @param string $method
     * @return RouteResult
     */
    private function marshalMatchedRoute(array $result, $method)
    {
        $path       = $result[1];
        $middleware = array_reduce($this->routes, function ($middleware, $route) use ($path, $method) {
            if ($middleware) {
                return $middleware;
            }

            if ($path !== $route->getPath()) {
                return $middleware;
            }

            if (! $route->allowsMethod($method)) {
                return $middleware;
            }

            return $route->getMiddleware();
        }, false);

        return RouteResult::fromRouteMatch(
            $path,
            $middleware,
            $result[2]
        );
    }
}
