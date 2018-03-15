<?php
/**
 * @see       https://github.com/zendframework/zend-expressive for the canonical source repository
 * @copyright Copyright (c) 2018 Zend Technologies USA Inc. (https://www.zend.com)
 * @license   https://github.com/zendframework/zend-expressive/blob/master/LICENSE.md New BSD License
 */

declare(strict_types=1);

namespace ZendTest\Expressive\Container;

use PHPUnit\Framework\TestCase;
use Psr\Container\ContainerInterface;
use Zend\Expressive\Container\MiddlewareFactoryFactory;
use Zend\Expressive\MiddlewareContainer;
use Zend\Expressive\MiddlewareFactory;

class MiddlewareFactoryFactoryTest extends TestCase
{
    public function testFactoryProducesMiddlewareFactoryComposingMiddlewareContainerInstance()
    {
        $middlewareContainer = $this->prophesize(MiddlewareContainer::class)->reveal();

        $container = $this->prophesize(ContainerInterface::class);
        $container->get(MiddlewareContainer::class)->willReturn($middlewareContainer);

        $factory = new MiddlewareFactoryFactory();

        $middlewareFactory = $factory($container->reveal());

        $this->assertInstanceOf(MiddlewareFactory::class, $middlewareFactory);
        $this->assertAttributeSame($middlewareContainer, 'container', $middlewareFactory);
    }
}
