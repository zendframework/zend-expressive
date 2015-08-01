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
use Aura\Router\RouteCollection;
use Aura\Router\RouteFactory;
use Aura\Router\Router;

class Aura implements RouterInterface
{
    /**
     * Aura router
     *
     * @var Aura\Router\Router
     */
    protected $router;

    /**
     * Matched Aura route
     *
     * @var Aura\Router\Route
     */
    protected $route;

    /**
     * Router configuration
     *
     * @var array
     */
    protected $config;

    /**
     * Construct
     */
    public function __construct()
    {
        $this->createRouter();
    }

    /**
     * Create the Aura router instance
     */
    protected function createRouter()
    {
        $this->router = new Router(
            new RouteCollection(new RouteFactory()),
            new Generator()
        );
    }

    /**
     * Add a route to the underlying router.
     *
     * Adds the route to the Aura.Router, using the path as the name, and the
     * "action" value equivalent to the middleware in the Route instance.
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
    public function match($path, $params)
    {
        $this->route = $this->router->match($path, $params);
        return (false !== $this->route);
    }

    /**
     * @return array
     */
    public function getMatchedParams()
    {
        return $this->route->params;
    }

    /**
     * @return string
     */
    public function getMatchedRouteName()
    {
        return $this->route->name;
    }

    /**
     * @return mixed
     */
    public function getMatchedMiddleware()
    {
        return $this->route->params['action'];
    }
}
