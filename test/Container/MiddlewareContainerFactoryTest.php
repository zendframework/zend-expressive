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
use Zend\Expressive\Container\MiddlewareContainerFactory;
use Zend\Expressive\MiddlewareContainer;

class MiddlewareContainerFactoryTest extends TestCase
{
    public function testFactoryCreatesMiddlewareContainerUsingProvidedContainer()
    {
        $container = $this->prophesize(ContainerInterface::class)->reveal();
        $factory = new MiddlewareContainerFactory();

        $middlewareContainer = $factory($container);

        $this->assertInstanceOf(MiddlewareContainer::class, $middlewareContainer);
        $this->assertAttributeSame($container, 'container', $middlewareContainer);
    }
}
