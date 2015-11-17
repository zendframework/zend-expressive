<?php
/**
 * Zend Framework (http://framework.zend.com/)
 *
 * @see       https://github.com/zendframework/zend-expressive for the canonical source repository
 * @copyright Copyright (c) 2015 Zend Technologies USA Inc. (http://www.zend.com)
 * @license   https://github.com/zendframework/zend-expressive/blob/master/LICENSE.md New BSD License
 */

namespace Zend\Expressive\Router;

/**
 * Generic implementation of a router factory.
 */
abstract class AbstractRouterFactory implements RouterFactoryInterface
{
    /**
     * @var Route[]
     */
    protected $routes;

    /**
     * {@inheritdoc}
     */
    public function addRoute(Route $route)
    {
        $this->routes[] = $route;
    }
}
