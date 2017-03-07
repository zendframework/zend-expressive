<?php
/**
 * @see       https://github.com/zendframework/zend-expressive for the canonical source repository
 * @copyright Copyright (c) 2016-2017 Zend Technologies USA Inc. (http://www.zend.com)
 * @license   https://github.com/zendframework/zend-expressive/blob/master/LICENSE.md New BSD License
 */

namespace ZendTest\Expressive\Container;

use PHPUnit\Framework\TestCase;
use Prophecy\Prophecy\ObjectProphecy;
use Psr\Container\ContainerInterface;
use Psr\Http\Message\ResponseInterface;
use Zend\Expressive\Container\ErrorHandlerFactory;
use Zend\Expressive\Middleware\ErrorResponseGenerator;
use Zend\Stratigility\Middleware\ErrorHandler;
use Zend\Stratigility\Middleware\ErrorResponseGenerator as StratigilityGenerator;

class ErrorHandlerFactoryTest extends TestCase
{
    /** @var ContainerInterface|ObjectProphecy */
    private $container;

    public function setUp()
    {
        $this->container = $this->prophesize(ContainerInterface::class);
    }

    public function testFactoryCreatesHandlerWithStratigilityGeneratorIfNoGeneratorServiceAvailable()
    {
        $this->container->has(ErrorResponseGenerator::class)->willReturn(false);

        $factory = new ErrorHandlerFactory();
        $handler = $factory($this->container->reveal());

        $this->assertInstanceOf(ErrorHandler::class, $handler);
        $this->assertAttributeInstanceOf(ResponseInterface::class, 'responsePrototype', $handler);
        $this->assertAttributeInstanceOf(StratigilityGenerator::class, 'responseGenerator', $handler);
    }

    public function testFactoryCreatesHandlerWithGeneratorIfGeneratorServiceAvailable()
    {
        $generator = $this->prophesize(ErrorResponseGenerator::class)->reveal();
        $this->container->has(ErrorResponseGenerator::class)->willReturn(true);
        $this->container->get(ErrorResponseGenerator::class)->willReturn($generator);

        $factory = new ErrorHandlerFactory();
        $handler = $factory($this->container->reveal());

        $this->assertInstanceOf(ErrorHandler::class, $handler);
        $this->assertAttributeInstanceOf(ResponseInterface::class, 'responsePrototype', $handler);
        $this->assertAttributeSame($generator, 'responseGenerator', $handler);
    }
}
