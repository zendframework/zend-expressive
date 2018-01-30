<?php
/**
 * @see       https://github.com/zendframework/zend-expressive for the canonical source repository
 * @copyright Copyright (c) 2018 Zend Technologies USA Inc. (https://www.zend.com)
 * @license   https://github.com/zendframework/zend-expressive/blob/master/LICENSE.md New BSD License
 */

declare(strict_types=1);

namespace Zend\Expressive\Container;

use Psr\Container\ContainerInterface;
use Zend\Expressive\Application;
use Zend\Expressive\ApplicationRunner;
use Zend\Expressive\MiddlewareFactory;
use Zend\Expressive\Middleware\RouteMiddleware;
use Zend\Stratigility\MiddlewarePipe;

class ApplicationFactory
{
    public function __invoke(ContainerInterface $container) : Application
    {
        return new Application(
            $container->get(MiddlewareFactory::class),
            new MiddlewarePipe(),
            $container->get(RouteMiddleware::class),
            $container->get(ApplicationRunner::class)
        );
    }
}
