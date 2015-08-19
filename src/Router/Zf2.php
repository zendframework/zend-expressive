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
use Zend\Expressive\Exception;
use Zend\Mvc\Router\Http\Part as PartRoute;
use Zend\Mvc\Router\Http\TreeRouteStack;
use Zend\Mvc\Router\RouteMatch;
use Zend\Psr7Bridge\Psr7ServerRequest;

/**
 * Router implementation that consumes zend-mvc TreeRouteStack.
 *
 * This router implementation consumes the TreeRouteStack from zend-mvc (the
 * default router implementation in a ZF2 application). The addRoute() method
 * injects segment routes into the TreeRouteStack. To manage 405 (Method Not
 * Allowed) errors, we inject a METHOD_NOT_ALLOWED_ROUTE route as a child
 * route, at a priority lower than method-specific routes. If the request
 * matches with this special route, we can send the HTTP allowed methods stored
 * for that path.
 */
class Zf2 implements RouterInterface
{
    const METHOD_NOT_ALLOWED_ROUTE = 'method_not_allowed';

    /**
     * Store the HTTP methods allowed for each path.
     *
     * @var array
     */
    private $allowedMethodsByPath = [];

    /**
     * Map a named route to a ZF2 route name to use for URI generation.
     *
     * @var array
     */
    private $routeNameMap = [];

    /**
     * @var TreeRouteStack
     */
    private $zf2Router;

    /**
     * Constructor.
     *
     * Lazy instantiates a TreeRouteStack if none is provided.
     *
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
        $name    = $route->getName();
        $path    = $route->getPath();
        $options = $route->getOptions();
        $options = array_replace_recursive($options, [
            'route'    => $path,
            'defaults' => [
                'middleware' => $route->getMiddleware(),
            ],
        ]);

        $allowedMethods = $route->getAllowedMethods();
        if (Route::HTTP_METHOD_ANY === $allowedMethods) {
            $this->zf2Router->addRoute($name, [
                'type'    => 'segment',
                'options' => $options,
            ]);
            $this->routeNameMap[$name] = $name;
            return;
        }

        // Remove the middleware from the segment route in favor of method route
        unset($options['defaults']['middleware']);
        if (empty($options['defaults'])) {
            unset($options['defaults']);
        }

        $httpMethodRouteName   = implode(':', $allowedMethods);
        $httpMethodRoute       = $this->createHttpMethodRoute($route);
        $methodNotAllowedRoute = $this->createMethodNotAllowedRoute($path);

        $spec = [
            'type'          => 'segment',
            'options'       => $options,
            'may_terminate' => false,
            'child_routes'  => [
                $httpMethodRouteName           => $httpMethodRoute,
                self::METHOD_NOT_ALLOWED_ROUTE => $methodNotAllowedRoute,
            ]
        ];

        if (array_key_exists($path, $this->allowedMethodsByPath)) {
            $allowedMethods = array_merge($this->allowedMethodsByPath[$path], $allowedMethods);
            // Remove the method not allowed route as it is already present for the path
            unset($spec['child_routes'][self::METHOD_NOT_ALLOWED_ROUTE]);
        }

        $this->zf2Router->addRoute($name, $spec);
        $this->allowedMethodsByPath[$path] = $allowedMethods;
        $this->routeNameMap[$name] = sprintf('%s/%s', $name, $httpMethodRouteName);
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
     * {@inheritDoc}
     */
    public function generateUri($name, array $substitutions = [])
    {
        if (! $this->zf2Router->hasRoute($name)) {
            throw new Exception\RuntimeException(sprintf(
                'Cannot generate URI based on route "%s"; route not found',
                $name
            ));
        }

        $name = isset($this->routeNameMap[$name]) ? $this->routeNameMap[$name] : $name;

        $options = [
            'name'             => $name,
            'only_return_path' => true,
        ];

        return $this->zf2Router->assemble($substitutions, $options);
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

        if (array_key_exists(self::METHOD_NOT_ALLOWED_ROUTE, $params)) {
            return RouteResult::fromRouteFailure(
                $this->allowedMethodsByPath[$params[self::METHOD_NOT_ALLOWED_ROUTE]]
            );
        }

        return RouteResult::fromRouteMatch(
            $this->getMatchedRouteName($match->getMatchedRouteName()),
            $params['middleware'],
            $params
        );
    }

    /**
     * Create route configuration for matching one or more HTTP methods.
     *
     * @param Route $route
     * @return array
     */
    private function createHttpMethodRoute($route)
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
     * Create the configuration for the "method not allowed" route.
     *
     * The specification is used for routes that have HTTP method negotiation;
     * essentially, this is a route that will always match, but *after* the
     * HTTP method route has already failed. By checking for this route later,
     * we can return a 405 response with the allowed methods.
     *
     * @param string $path
     * @return array
     */
    private function createMethodNotAllowedRoute($path)
    {
        return [
            'type'     => 'regex',
            'priority' => -1,
            'options'  => [
                'regex'    => '/*$',
                'defaults' => [
                    self::METHOD_NOT_ALLOWED_ROUTE => $path,
                ],
                'spec' => '',
            ],
        ];
    }

    /**
     * Calculate the route name.
     *
     * Routes will generally match the child HTTP method routes, which will not
     * match the names they were registered with; this method strips the method
     * route name if present.
     *
     * @param string $name
     * @return string
     */
    private function getMatchedRouteName($name)
    {
        // Check for <name>/GET:POST style route names; if so, strip off the
        // child route matching the method.
        if (preg_match('/(?P<name>.+)\/([!#$%&\'*+.^_`\|~0-9a-z-]+:?)+$/i', $name, $matches)) {
            return $matches['name'];
        }

        // Otherwise, just use the name.
        return $name;
    }
}
