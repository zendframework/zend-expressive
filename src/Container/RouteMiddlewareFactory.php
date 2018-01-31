<?php
/**
 * @see       https://github.com/zendframework/zend-expressive for the canonical source repository
 * @copyright Copyright (c) 2018 Zend Technologies USA Inc. (https://www.zend.com)
 * @license   https://github.com/zendframework/zend-expressive/blob/master/LICENSE.md New BSD License
 */

declare(strict_types=1);

namespace Zend\Expressive\Container;

use Psr\Container\ContainerInterface;
use Psr\Http\Message\ResponseInterface;
use Zend\Expressive\Middleware\RouteMiddleware;
use Zend\Expressive\Router\RouterInterface;

class RouteMiddlewareFactory
{
    public function __invoke(ContainerInterface $container) : RouteMiddleware
    {
        return new RouteMiddleware(
            $container->get(RouterInterface::class),
            $container->get(ResponseInterface::class)
        );
    }
}
