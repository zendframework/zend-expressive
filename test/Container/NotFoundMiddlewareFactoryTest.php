<?php
/**
 * @see       https://github.com/zendframework/zend-expressive for the canonical source repository
 * @copyright Copyright (c) 2016-2017 Zend Technologies USA Inc. (https://www.zend.com)
 * @license   https://github.com/zendframework/zend-expressive/blob/master/LICENSE.md New BSD License
 */

declare(strict_types=1);

namespace ZendTest\Expressive\Container;

use PHPUnit\Framework\TestCase;
use Psr\Container\ContainerInterface;
use Zend\Expressive\Container\NotFoundMiddlewareFactory;
use Zend\Expressive\Handler\NotFoundHandler;
use Zend\Expressive\Middleware\NotFoundMiddleware;

class NotFoundMiddlewareFactoryTest extends TestCase
{
    public function testUsesComposedNotFoundHandlerServiceToCreateNotFoundHandlerMiddleware()
    {
        $handler   = $this->prophesize(NotFoundHandler::class)->reveal();
        $container = $this->prophesize(ContainerInterface::class);
        $container->get(NotFoundHandler::class)->willReturn($handler);
        $factory = new NotFoundMiddlewareFactory();

        $middleware = $factory($container->reveal());

        $this->assertInstanceOf(NotFoundMiddleware::class, $middleware);
        $this->assertAttributeSame($handler, 'internalHandler', $middleware);
    }
}
