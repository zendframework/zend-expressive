<?php
/**
 * Zend Framework (http://framework.zend.com/)
 *
 * @see       http://github.com/zendframework/zend-diactoros for the canonical source repository
 * @copyright Copyright (c) 2015 Zend Technologies USA Inc. (http://www.zend.com)
 * @license   https://github.com/zendframework/zend-diactoros/blob/master/LICENSE.md New BSD License
 */
namespace Zend\Expressive\Router;

use Aura\Router\Generator;
use Aura\Router\Route as AuraRoute;
use Aura\Router\RouteCollection;
use Aura\Router\RouteFactory;
use Aura\Router\Router;
use Psr\Http\Message\ServerRequestInterface as Request;

class Aura implements RouterInterface
{
    /**
     * Aura router
     *
     * @var Aura\Router\Router
     */
    protected $router;

    /**
     * Construct
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
    protected function createRouter()
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
        $auraRoute = $this->router->add(
            $route->getPath(),
            $route->getPath(),
            $route->getMiddleware()
        );

        $httpMethods = $route->getMethods();
        if (is_array($httpMethods)) {
            $auraRoute->setServer([
                'REQUEST_METHOD' => implode('|', $httpMethods),
            ]);
        }

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
    }

    /**
     * @param  string $patch
     * @param  array $params
     * @return boolean
     */
    public function match(Request $request)
    {
        $path   = $request->getUri()->getPath();
        $params = $request->getServerParams();
        $route  = $this->router->match($path, $params);

        if (false === $route) {
            return $this->marshalFailedRoute();
        }

        return $this->marshalMatchedRoute($route);
    }

    /**
     * Marshal a RouteResult representing a route failure.
     *
     * If the route failure is due to the HTTP method, passes the allowed
     * methods when creating the result.
     *
     * @return RouteResult
     */
    private function marshalFailedRoute()
    {
        $failedRoute = $this->router->getFailedRoute();
        if (! $failedRoute->failedMethod()) {
            return RouteResult::fromRouteFailure();
        }

        return RouteResult::fromRouteFailure($failedRoute->method);
    }

    /**
     * Marshals a route result based on the matched AuraRoute.
     *
     * @return RouteResult
     */
    private function marshalMatchedRoute(AuraRoute $route)
    {
        return RouteResult::fromRouteMatch(
            $route->name,
            $route->params['action'],
            $route->params
        );
    }
}
