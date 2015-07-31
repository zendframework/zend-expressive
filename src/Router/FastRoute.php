<?php
/**
 * Zend Framework (http://framework.zend.com/)
 *
 * @see       http://github.com/zendframework/zend-diactoros for the canonical source repository
 * @copyright Copyright (c) 2015 Zend Technologies USA Inc. (http://www.zend.com)
 * @license   https://github.com/zendframework/zend-diactoros/blob/master/LICENSE.md New BSD License
 */
namespace Zend\Expressive\Router;

use FastRoute\DataGenerator\GroupCountBased as RouteGenerator;
use FastRoute\Dispatcher\GroupCountBased as Dispatcher;
use FastRoute\RouteCollector;
use FastRoute\RouteParser\Std as RouteParser;

class FastRoute implements RouterInterface
{
    /**
     * FastRoute router
     *
     * @var FastRoute\RouteCollector
     */
    protected $router;

    /**
     * Matched route data
     *
     * @var array
     */
    protected $routeInfo;

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
     * Create the FastRoute Collector instance
     */
    protected function createRouter()
    {
        $this->router = new RouteCollector(new RouteParser, new RouteGenerator);
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
            if (isset($data['methods']) && is_array($data['methods'])) {
                $methods = $data['methods'];
            } else {
                $methods = ['GET'];
            }
            $this->router->addRoute($methods, $data['url'], $name);
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
        $dispatcher = new Dispatcher($this->router->getData());
        $result     = $dispatcher->dispatch($params['REQUEST_METHOD'], $path);
        if ($result[0] != Dispatcher::FOUND) {
            return false;
        }
        $this->routeInfo = $result;
        return true;
    }

    /**
     * @return array
     */
    public function getMatchedParams()
    {
        $params = isset($this->routeInfo[2]) ? $this->routeInfo[2] : [];
        return $params;
    }

    /**
     * @return string
     */
    public function getMatchedRouteName()
    {
        $name = isset($this->routeInfo[1]) ? $this->routeInfo[1] : [];
        return $name;
    }

    /**
     * @return mixed
     */
    public function getMatchedAction()
    {
        $action = isset($this->routeInfo[1]) ? $this->config['routes'][$this->routeInfo[1]]['action'] : null;
        return $action;
    }
}
