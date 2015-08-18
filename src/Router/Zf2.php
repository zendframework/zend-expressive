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
 * segment routes into the TreeRouteStack, and create a fail route for HTTP
 * method negotiation (as the ZF2 "Method" router implementation will return
 * a result indistinguishable from a 404 otherwise).
 */
class Zf2 implements RouterInterface
{
    // Name of the route fail
    const ROUTE_FAIL = 'fail';

    /**
     * @var TreeRouteStack
     */
    private $zf2Router;

    /**
     * Store the path and the HTTP methods allowed
     *
     * @var array
     */
    private $routes = [];

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
        $path    = $route->getPath();
        $options = $route->getOptions();
        $options = array_replace_recursive($options, [
            'route' => $path
        ]);
        $childRouteName = implode('-', $route->getAllowedMethods());
        $childRoutes    = $this->getMethodRouteConfig($route);
        $spec = [
            'type'          => 'segment',
            'options'       => $options,
            'may_terminate' => false,
            'child_routes'  => [ $childRouteName => $childRoutes ]
        ];
        $routeFail = $path . '/' . self::ROUTE_FAIL;
        if (array_key_exists($routeFail, $this->routes)) {
            $this->zf2Router->getRoute($path)->addRoute($childRouteName, $childRoutes);
        } else {
            $spec['child_routes'][self::ROUTE_FAIL] = $this->getFailRouteConfig();
            $this->zf2Router->addRoute($path, $spec);
        }

        $allowedMethods = (array) $route->getAllowedMethods();
        if (array_key_exists($routeFail, $this->routes)) {
            $allowedMethods = array_merge($this->routes[$routeFail], $allowedMethods);
        }
        $this->routes[$routeFail] = $allowedMethods;
    }

    /**
     * Get the method route configuration
     *
     * @param Route $route
     * @return array
     */
    private function getMethodRouteConfig($route)
    {
        return [
            'type'    => 'method',
            'options' => [
                'verb'     => implode(',', (array) $route->getAllowedMethods()),
                'defaults' => [
                    'middleware' => $route->getMiddleware()
                ]
            ]
        ];
    }

    /**
     * Get the configuration for the fail route
     *
     * @return array
     */
    private function getFailRouteConfig()
    {
        return [
            'type'     => 'segment',
            'priority' => -1,
            'options'  => [
                'route'    => '[/]',
                'defaults' => [
                    'middleware' => null
                ]
            ]
        ];
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
        $params    = $match->getParams();
        $routeName = $match->getMatchedRouteName();

        if (null === $params['middleware']) {
            return RouteResult::fromRouteFailure($this->routes[$routeName]);
        }

        return RouteResult::fromRouteMatch(
            $routeName,
            $params['middleware'],
            $params
        );
    }
}
