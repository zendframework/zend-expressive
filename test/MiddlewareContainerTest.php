<?php
/**
 * @see       https://github.com/zendframework/zend-expressive for the canonical source repository
 * @copyright Copyright (c) 2018 Zend Technologies USA Inc. (https://www.zend.com)
 * @license   https://github.com/zendframework/zend-expressive/blob/master/LICENSE.md New BSD License
 */

declare(strict_types=1);

namespace ZendTest\Expressive;

use PHPUnit\Framework\TestCase;
use Psr\Container\ContainerInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Zend\Expressive\Exception;
use Zend\Expressive\MiddlewareContainer;
use Zend\Expressive\Router\Middleware\DispatchMiddleware;
use Zend\Stratigility\Middleware\RequestHandlerMiddleware;

class MiddlewareContainerTest extends TestCase
{
    public function setUp()
    {
        $this->originContainer = $this->prophesize(ContainerInterface::class);
        $this->container = new MiddlewareContainer($this->originContainer->reveal());
    }

    public function testHasReturnsTrueIfOriginContainerHasService()
    {
        $this->originContainer->has('foo')->willReturn(true);
        $this->assertTrue($this->container->has('foo'));
    }

    public function testHasReturnsFalseIfOriginContainerDoesNotHaveServiceAndClassDoesNotExist()
    {
        $this->originContainer->has('not-a-class')->willReturn(false);
        $this->assertFalse($this->container->has('not-a-class'));
    }

    public function testGetRaisesExceptionIfServiceIsUnknown()
    {
        $this->originContainer->has('not-a-service')->willReturn(false);

        $this->expectException(Exception\MissingDependencyException::class);
        $this->container->get('not-a-service');
    }

    public function testGetRaisesExceptionIfServiceSpecifiedDoesNotImplementMiddlewareInterface()
    {
        $this->originContainer->has(__CLASS__)->willReturn(true);
        $this->originContainer->get(__CLASS__)->willReturn($this);

        $this->expectException(Exception\InvalidMiddlewareException::class);
        $this->container->get(__CLASS__);
    }

    public function testGetRaisesExceptionIfClassSpecifiedDoesNotImplementMiddlewareInterface()
    {
        $this->originContainer->has(__CLASS__)->willReturn(false);
        $this->originContainer->get(__CLASS__)->shouldNotBeCalled();

        $this->expectException(Exception\InvalidMiddlewareException::class);
        $this->container->get(__CLASS__);
    }

    public function testGetReturnsServiceFromOriginContainer()
    {
        $middleware = $this->prophesize(MiddlewareInterface::class)->reveal();
        $this->originContainer->has('middleware-service')->willReturn(true);
        $this->originContainer->get('middleware-service')->willReturn($middleware);

        $this->assertSame($middleware, $this->container->get('middleware-service'));
    }

    public function testGetWillDecorateARequestHandlerAsMiddleware()
    {
        $handler = $this->prophesize(RequestHandlerInterface::class)->reveal();

        $this->originContainer->has('AHandlerNotMiddleware')->willReturn(true);
        $this->originContainer->get('AHandlerNotMiddleware')->willReturn($handler);

        $middleware = $this->container->get('AHandlerNotMiddleware');

        // Test that we get back middleware decorating the handler
        $this->assertInstanceOf(RequestHandlerMiddleware::class, $middleware);
        $this->assertAttributeSame($handler, 'handler', $middleware);
    }
}
