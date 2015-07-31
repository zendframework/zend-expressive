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
     * Construct
     */
    public function __construct()
    {
        $this->router = new Router(
            new RouteCollection(new RouteFactory()),
            new Generator()
        );
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
    public function getMatchedName()
    {
        return $this->route->name;
    }

    /**
     * @return mixed
     */
    public function getMatchedCallable()
    {
        return $this->route->params['action'];
    }

    /**
     * Add a route
     *
     * @param string $name
     * @param string $path
     * @param callable $callable
     * @param array $options
     */
    public function addRoute($name, $path, $callable, $options = [])
    {
        $values = isset($options['values']) ? $options['values'] : [];
        $values['action'] = $callable;
        $tokens = isset($options['tokens']) ? $options['tokens'] : [];
        $this->router->add($name, $path)
                     ->addValues($values)
                     ->addTokens($tokens);
    }
}
