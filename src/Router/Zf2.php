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
 * segment routes into the TreeRouteStack. We store the HTTP allowed methods
 * for each route in a private array and we use it to find a 404 error in
 * case of match failure.
 */
class Zf2 implements RouterInterface
{
    /**
     * Store the HTTP methods allowed for each route
     *
     * @var array
     */
    private $allowedMethods = [];

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
        $name       = $route->getName();
        $path       = $route->getPath();
        $options    = $route->getOptions();
        $options    = array_replace_recursive($options, [
            'route' => $path,
            'defaults' => [
                'middleware' => $route->getMiddleware(),
            ]
        ]);

        $allowedMethods = $route->getAllowedMethods();
        if (Route::HTTP_METHOD_ANY === $allowedMethods) {
            $this->zf2Router->addRoute($name, [
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

        $this->zf2Router->addRoute($name, $spec);

        if (array_key_exists($path, $this->allowedMethods)) {
            $allowedMethods = array_merge($this->allowedMethods[$path], $allowedMethods);
        }
        $this->allowedMethods[$path] = $allowedMethods;
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
            $path = $request->getUri()->getPath();
            if (array_key_exists($path, $this->allowedMethods)) {
                return RouteResult::fromRouteFailure($this->allowedMethods[$path]);
            }
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
        $params = $match->getParams();

        return RouteResult::fromRouteMatch(
            $match->getMatchedRouteName(),
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
}
