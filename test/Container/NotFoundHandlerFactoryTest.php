<?php
/**
 * @see       https://github.com/zendframework/zend-expressive for the canonical source repository
 * @copyright Copyright (c) 2016-2017 Zend Technologies USA Inc. (http://www.zend.com)
 * @license   https://github.com/zendframework/zend-expressive/blob/master/LICENSE.md New BSD License
 */

namespace ZendTest\Expressive\Container;

use PHPUnit\Framework\TestCase;
use Psr\Container\ContainerInterface;
use Zend\Expressive\Container\NotFoundHandlerFactory;
use Zend\Expressive\Delegate\NotFoundDelegate;
use Zend\Expressive\Middleware\NotFoundHandler;

class NotFoundHandlerFactoryTest extends TestCase
{
    public function testUsesComposedNotFoundDelegateServiceToCreateNotFoundHandler()
    {
        $delegate  = $this->prophesize(NotFoundDelegate::class)->reveal();
        $container = $this->prophesize(ContainerInterface::class);
        $container->get(NotFoundDelegate::class)->willReturn($delegate);
        $factory = new NotFoundHandlerFactory();

        $handler = $factory($container->reveal());

        $this->assertInstanceOf(NotFoundHandler::class, $handler);
        $this->assertAttributeSame($delegate, 'internalDelegate', $handler);
    }
}
