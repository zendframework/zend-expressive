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
 * segment routes into the TreeRouteStack, and creates a route failure for HTTP
 * method negotiation (as the ZF2 "Method" router implementation will return
 * a result indistinguishable from a 404 otherwise).
 */
class Zf2 implements RouterInterface
{
    // Name of the method not allowed route
    const METHOD_NOT_ALLOWED_ROUTE = 'method_not_allowed';

    /**
     * Store the path and the HTTP methods allowed
     *
     * @var array
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
        $path    = $route->getPath();
        $options = $route->getOptions();
        $options = array_replace_recursive($options, [
            'route' => $path,
            'defaults' => [
                'middleware' => $route->getMiddleware()
            ]
        ]);

        $allowedMethods =  $route->getAllowedMethods();
        if (Route::HTTP_METHOD_ANY === $allowedMethods) {
            $this->zf2Router->addRoute($path, [
                'type'    => 'segment',
                'options' => $options
            ]);
            return;
        }

        // Remove the middleware from the segment route in favor of method route
        unset($options['defaults']['middleware']);
        if (empty($options['defaults'])) {
            unset($options['defaults']);
        }

        $childRouteName = implode('-', $allowedMethods);
        $childRoutes    = $this->getMethodRouteConfig($route);

        $spec = [
            'type'          => 'segment',
            'options'       => $options,
            'may_terminate' => false,
            'child_routes'  => [ $childRouteName => $childRoutes ]
        ];

        $routeFail = $path . '/' . self::METHOD_NOT_ALLOWED_ROUTE;
        if (array_key_exists($routeFail, $this->routes)) {
            $this->zf2Router->getRoute($path)->addRoute($childRouteName, $childRoutes);
        } else {
            $spec['child_routes'][self::METHOD_NOT_ALLOWED_ROUTE] = $this->getFailRouteConfig();
            $this->zf2Router->addRoute($path, $spec);
        }

        if (array_key_exists($routeFail, $this->routes)) {
            $allowedMethods = array_merge($this->routes[$routeFail], $allowedMethods);
        }
        $this->routes[$routeFail] = $allowedMethods;
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

        // Check to see if this is the method indicating method not allowed; if
        // so, return a routing failure.
        if (false !== strstr($routeName, self::METHOD_NOT_ALLOWED_ROUTE)) {
            // Retrieve the HTTP method allowed from stored routes
            return RouteResult::fromRouteFailure($this->routes[$routeName]);
        }

        return RouteResult::fromRouteMatch(
            $routeName,
            $params['middleware'],
            $params
        );
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
                'verb'     => implode(',', $route->getAllowedMethods()),
                'defaults' => [
                    'middleware' => $route->getMiddleware(),
                ],
            ],
        ];
    }

    /**
     * Get the configuration for the fail route.
     *
     * The specification is used for routes that have HTTP method negotiation;
     * essentially, this is a route that will always match, but *after* the
     * HTTP method route has already failed. By checking for this route later,
     * we can return a 405 response with the allowed methods.
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
                    'middleware' => null,
                ],
            ],
        ];
    }
}
