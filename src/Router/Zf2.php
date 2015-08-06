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
    private $zf2Router;

    /**
     * @param null|TreeRouteStack $router
     */
    public function __construct(TreeRouteStack $router = null)
    {
        if (null === $router) {
            $router = $this->createRouter();
        }

        $this->zf2Router = $router;
    }

    /**
     * @param Route $route
     */
    public function addRoute(Route $route)
    {
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

        $this->zf2Router->addRoute($route->getPath(), $spec);
        $this->routes[] = $route;
    }

    /**
     * Attempt to match an incoming request to a registered route.
     *
     * @param PsrRequest $request
     * @return RouteResult
     */
    public function match(PsrRequest $request)
    {
        $match = $this->zf2Router->match(Psr7ServerRequest::toZend($request, true));

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
        return array_reduce($this->routes, function ($carry, $route) use ($name) {
            if ($carry) {
                return $carry;
            }

            if ($route->getPath() === $name) {
                return $route->getMiddleware();
            }

            return null;
        }, null);
    }

    /**
     * Get list of allowed methods for this route.
     *
     * @param name $string
     * @return int|string[]
     */
    private function getAllowedMethods($name)
    {
        $allowedMethods = array_reduce($this->routes, function ($carry, $route) use ($name) {
            if (null !== $carry) {
                return $carry;
            }

            if ($route->getPath() === $name) {
                return $route->getAllowedMethods();
            }

            return null;
        }, null);

        return ($allowedMethods === null)
            ? Route::HTTP_METHOD_ANY
            : $allowedMethods;
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
