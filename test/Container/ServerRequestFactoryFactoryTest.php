<?php
/**
 * @see       https://github.com/zendframework/zend-expressive for the canonical source repository
 * @copyright Copyright (c) 2018 Zend Technologies USA Inc. (https://www.zend.com)
 * @license   https://github.com/zendframework/zend-expressive/blob/master/LICENSE.md New BSD License
 */

declare(strict_types=1);

namespace ZendTest\Expressive\Container;

use Closure;
use PHPUnit\Framework\TestCase;
use Psr\Container\ContainerInterface;
use Zend\Diactoros\ServerRequestFactory;
use Zend\Expressive\Container\ServerRequestFactoryFactory;

class ServerRequestFactoryFactoryTest extends TestCase
{
    public function testFactoryReturnsCallable()
    {
        $container = $this->prophesize(ContainerInterface::class)->reveal();
        $factory = new ServerRequestFactoryFactory();

        $generatedFactory = $factory($container);

        $this->assertInternalType('callable', $generatedFactory);

        return $generatedFactory;
    }

    /**
     * Some containers do not allow returning generic PHP callables, and will
     * error when one is returned; one example is Auryn. As such, the factory
     * cannot simply return a callable referencing the
     * ServerRequestFactory::fromGlobals method, but must be decorated as a
     * closure.
     *
     * @depends testFactoryReturnsCallable
     */
    public function testFactoryIsAClosure(callable $factory)
    {
        $this->assertNotSame([ServerRequestFactory::class, 'fromGlobals'], $factory);
        $this->assertNotSame(ServerRequestFactory::class . '::fromGlobals', $factory);
        $this->assertInstanceOf(Closure::class, $factory);
    }
}
