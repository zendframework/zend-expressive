<?php
/**
 * Zend Framework (http://framework.zend.com/)
 *
 * @see       http://github.com/zendframework/zend-diactoros for the canonical source repository
 * @copyright Copyright (c) 2015 Zend Technologies USA Inc. (http://www.zend.com)
 * @license   https://github.com/zendframework/zend-diactoros/blob/master/LICENSE.md New BSD License
 */
namespace Zend\Stratigility\Dispatch\Router;

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
     * Set config
     *
     * @param array $config
     */
    public function setConfig(array $config)
    {
        if (!empty($this->config)) {
            $this->createRouter();
        }
        foreach ($config['routes'] as $name => $data) {
            $this->router->add($name, $data['url']);
            if (!isset($data['values'])) {
                $data['values'] = [];
            }
            $data['values']['action'] = $data['action'];
            if (isset($data['methods']) && is_array($data['methods'])) {
                $methods = implode('|', $data['methods']);
            } else {
                $methods = 'GET';
            }
            $this->router->setServer(['REQUEST_METHOD' => $methods]);
            if (!isset($data['tokens'])) {
                $this->router->add($name, $data['url'])
                             ->addValues($data['values']);
            } else {
                $this->router->add($name, $data['url'])
                             ->addValues($data['values'])
                             ->addTokens($data['tokens']);
            }
        }
        $this->config = $config;
    }

    /**
     * Get config
     *
     * @return array
     */
    public function getConfig()
    {
        return $this->config;
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
    public function getMatchedAction()
    {
        return $this->route->params['action'];
    }
}
