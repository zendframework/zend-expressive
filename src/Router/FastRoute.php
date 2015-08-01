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

class FastRoute implements RouterInterface
{
    /**
     * FastRoute router
     *
     * @var FastRoute\RouteCollector
     */
    protected $router;

    /**
     * All attached routes as Route instances
     *
     * @var Route[]
     */
    protected $routes;

    /**
     * Construct
     */
    public function __construct(RouteCollector $router = null)
    {
        if (null === $router) {
            $router = $this->createRouter();
        }

        $this->router = $router;
    }

    /**
     * Create a default FastRoute Collector instance
     *
     * @return RouteCollector
     */
    protected function createRouter()
    {
        return new RouteCollector(new RouteParser, new RouteGenerator);
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
        $methods = $route->getMethods();
        if (! is_array($methods)) {
            $methods = ($methods === Route::HTTP_METHOD_ANY)
                ? ['GET', 'POST', 'PUT', 'PATCH', 'DELETE', 'HEAD', 'OPTIONS', 'TRACE']
                : ['GET', 'HEAD', 'OPTIONS'];
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
        $dispatcher = new Dispatcher($this->router->getData());
        $result     = $dispatcher->dispatch($method, $path);

        if ($result[0] != Dispatcher::FOUND) {
            return $this->marshalFailedRoute($result);
        }

        return $this->marshalMatchedRoute($result, $method);
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
