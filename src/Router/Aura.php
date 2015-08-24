<?php
/**
 * Zend Framework (http://framework.zend.com/)
 *
 * @see       https://github.com/zendframework/zend-expressive for the canonical source repository
 * @copyright Copyright (c) 2015 Zend Technologies USA Inc. (http://www.zend.com)
 * @license   https://github.com/zendframework/zend-expressive/blob/master/LICENSE.md New BSD License
 */

namespace Zend\Expressive\Router;

use Aura\Router\Generator;
use Aura\Router\Route as AuraRoute;
use Aura\Router\RouteCollection;
use Aura\Router\RouteFactory;
use Aura\Router\Router;
use Psr\Http\Message\ServerRequestInterface as Request;

/**
 * Router implementation bridging the Aura.Router.
 */
class Aura implements RouterInterface
{
    /**
     * Aura router
     *
     * @var Router
     */
    private $router;

    /**
     * Store the path and the HTTP methods allowed
     *
     * @var array
     */
    private $routes = [];

    /**
     * Constructor
     *
     * If no Aura.Router instance is provided, the constructor will lazy-load
     * an instance. If you need to customize the Aura.Router instance in any
     * way, you MUST inject it yourself.
     *
     * @param null|Router $router
     */
    public function __construct(Router $router = null)
    {
        if (null === $router) {
            $router = $this->createRouter();
        }

        $this->router = $router;
    }

    /**
     * Create a default Aura router instance
     *
     * @return Router
     */
    private function createRouter()
    {
        return new Router(
            new RouteCollection(new RouteFactory()),
            new Generator()
        );
    }

    /**
     * Add a route to the underlying router.
     *
     * Adds the route to the Aura.Router, using the path as the name, and a
     * middleware value equivalent to the middleware in the Route instance.
     *
     * If HTTP methods are defined (and not the wildcard), they are imploded
     * with a pipe symbol and added as server REQUEST_METHOD criteria.
     *
     * If tokens or values are present in the options array, they are also
     * added to the router.
     *
     * @param Route $route
     */
    public function addRoute(Route $route)
    {
        $path      = $route->getPath();
        $auraRoute = $this->router->add(
            $route->getName(),
            $path,
            $route->getMiddleware()
        );

        foreach ($route->getOptions() as $key => $value) {
            switch ($key) {
                case 'tokens':
                    $auraRoute->addTokens($value);
                    break;
                case 'values':
                    $auraRoute->addValues($value);
                    break;
            }
        }

        $allowedMethods = $route->getAllowedMethods();
        if (Route::HTTP_METHOD_ANY === $allowedMethods) {
            return;
        }

        $auraRoute->setServer([
            'REQUEST_METHOD' => implode('|', $allowedMethods)
        ]);

        if (array_key_exists($path, $this->routes)) {
            $allowedMethods = array_merge($this->routes[$path], $allowedMethods);
        }
        $this->routes[$path] = $allowedMethods;
    }

    /**
     * @param Request $request
     * @return RouteResult
     */
    public function match(Request $request)
    {
        $path   = $request->getUri()->getPath();
        $params = $request->getServerParams();
        $route  = $this->router->match($path, $params);

        if (false === $route) {
            return $this->marshalFailedRoute($request);
        }

        return $this->marshalMatchedRoute($route);
    }

    /**
     * {@inheritDoc}
     */
    public function generateUri($name, array $substitutions = [])
    {
        return $this->router->generate($name, $substitutions);
    }

    /**
     * Marshal a RouteResult representing a route failure.
     *
     * If the route failure is due to the HTTP method, passes the allowed
     * methods when creating the result.
     *
     * @param Request $request
     * @return RouteResult
     */
    private function marshalFailedRoute(Request $request)
    {
        $failedRoute = $this->router->getFailedRoute();
        if ($failedRoute->failedMethod()) {
            return RouteResult::fromRouteFailure($failedRoute->method);
        }

        // Check to see if the route regex matched; if so, and we have an entry
        // for the path, register a 405.
        list($path) = explode('^', $failedRoute->name);
        if (isset($failedRoute->failed)
            && $failedRoute->failed !== AuraRoute::FAILED_REGEX
            && array_key_exists($path, $this->routes)
        ) {
            return RouteResult::fromRouteFailure($this->routes[$path]);
        }

        return RouteResult::fromRouteFailure();
    }

    /**
     * Marshals a route result based on the matched AuraRoute.
     *
     * Note: no actual typehint is provided here; Aura Route instances provide
     * property overloading, which is difficult to mock for testing; we simply
     * assume an object at this point.
     *
     * @param AuraRoute $route
     * @return RouteResult
     */
    private function marshalMatchedRoute($route)
    {
        return RouteResult::fromRouteMatch(
            $route->name,
            $route->params['action'],
            $route->params
        );
    }
}
