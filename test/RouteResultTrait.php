<?php
/**
 * @see       https://github.com/zendframework/zend-expressive for the canonical source repository
 * @copyright Copyright (c) 2017 Zend Technologies USA Inc. (http://www.zend.com)
 * @license   https://github.com/zendframework/zend-expressive/blob/master/LICENSE.md New BSD License
 */

namespace ZendTest\Expressive;

use Zend\Expressive\Router\Route;
use Zend\Expressive\Router\RouteResult;

trait RouteResultTrait
{
    private function getRouteResult($name, $middleware, array $params)
    {
        if (method_exists(RouteResult::class, 'fromRouteMatch')) {
            return RouteResult::fromRouteMatch($name, $middleware, $params);
        }

        $route = $this->prophesize(Route::class);
        $route->getMiddleware()->willReturn($middleware);
        $route->getPath()->willReturn($name);
        $route->getName()->willReturn(null);

        return RouteResult::fromRoute($route->reveal(), $params);
    }
}
