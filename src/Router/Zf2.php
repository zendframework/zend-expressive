<?php
/**
 * Zend Framework (http://framework.zend.com/)
 *
 * @see       https://github.com/zendframework/zend-expressive for the canonical source repository
 * @copyright Copyright (c) 2015 Zend Technologies USA Inc. (http://www.zend.com)
 * @license   https://github.com/zendframework/zend-expressive/blob/master/LICENSE.md New BSD License
 */

namespace Zend\Expressive\Router;

use Psr\Http\Message\ServerRequestInterface as PsrRequest;
use Zend\Mvc\Router\Http\TreeRouteStack;
use Zend\Mvc\Router\RouteMatch;
use Zend\Psr7Bridge\Psr7ServerRequest;

/**
 * Router implementation that consumes zend-mvc TreeRouteStack.
 *
 * This router implementation consumes zend-mvc's TreeRouteStack, (the default
 * router implementation in a ZF2 application). The addRoute() method injects
 * segment routes into the TreeRouteStack, and manages an internal route stack
 * in order to do HTTP method negotiation after a successful match (as the ZF2
 * "Method" router implementation will return a result indistinguishable from a
 * 404 otherwise).
 */
class Zf2 implements RouterInterface
{
    /**
     * @var Route[] Registered routes
     */
    private $routes = [];

    /**
     * @var TreeRouteStack
     */
    private $router;

    /**
     * @param null|TreeRouteStack $router
     */
    public function __construct(TreeRouteStack $router = null)
    {
        if (null === $router) {
            $router = $this->createRouter();
        }

        $this->router = $router;
    }

    /**
     * @param Route $route
     */
    public function addRoute(Route $route)
    {
        $path    = $route->getPath();
        $options = $route->getOptions() ?: [];
        $options = array_replace_recursive($options, [
            'route'   => $route->getPath(),
            'defaults' => [
                'middleware' => $route->getMiddleware(),
            ],
        ]);

        $spec = [
            'type'    => 'segment',
            'options' => $options,
        ];

        $this->router->addRoute($path, $spec);
        $this->routes[$path] = $route;
    }

    /**
     * Attempt to match an incoming request to a registered route.
     *
     * @param PsrRequest $request
     * @return RouteResult
     */
    public function match(PsrRequest $request)
    {
        $match = $this->router->match(Psr7ServerRequest::toZend($request, true));

        if (null === $match) {
            return RouteResult::fromRouteFailure();
        }

        $allowedMethods = $this->getAllowedMethods($match->getMatchedRouteName());
        if (! $this->methodIsAllowed($request->getMethod(), $allowedMethods)) {
            return RouteResult::fromRouteFailure($allowedMethods);
        }

        return $this->marshalSuccessResultFromRouteMatch($match);
    }

    /**
     * @return TreeRouteStack
     */
    private function createRouter()
    {
        return new TreeRouteStack();
    }

    /**
     * Create a succesful RouteResult from the given RouteMatch.
     *
     * @param RouteMatch $match
     * @return RouteResult
     */
    private function marshalSuccessResultFromRouteMatch(RouteMatch $match)
    {
        $params = $match->getParams();
        $middleware = isset($params['middleware'])
            ? $params['middleware']
            : $this->getMiddlewareFromRoute($match->getMatchedRouteName());

        return RouteResult::fromRouteMatch(
            $match->getMatchedRouteName(),
            $middleware,
            $params
        );
    }

    /**
     * Given a route name (the path), retrieve the middleware associated with it.
     *
     * @param string $name
     * @return null|string|callable
     */
    private function getMiddlewareFromRoute($name)
    {
        if (! array_key_exists($name, $this->routes)) {
            return null;
        }

        $route = $this->routes[$name];
        return $route->getMiddleware();
    }

    /**
     * Get list of allowed methods for this route.
     *
     * @param name $string
     * @return int|string[]
     */
    private function getAllowedMethods($name)
    {
        if (! array_key_exists($name, $this->routes)) {
            return Route::HTTP_METHOD_ANY;
        }

        $route = $this->routes[$name];
        return $route->getAllowedMethods();
    }

    /**
     * Is the provided method in the list of allowed methods?
     *
     * @param string $method
     * @param int|string[] $allowedMethods
     * @return bool
     */
    private function methodIsAllowed($method, $allowedMethods)
    {
        if ($allowedMethods === Route::HTTP_METHOD_ANY) {
            return true;
        }

        return in_array(strtoupper($method), array_map('strtoupper', $allowedMethods), true);
    }
}
