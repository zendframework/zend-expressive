<?php
/**
 * @see       https://github.com/zendframework/zend-expressive for the canonical source repository
 * @copyright Copyright (c) 2017 Zend Technologies USA Inc. (https://www.zend.com)
 * @license   https://github.com/zendframework/zend-expressive/blob/master/LICENSE.md New BSD License
 */

declare(strict_types=1);

namespace ZendTest\Expressive\Middleware;

use PHPUnit\Framework\TestCase;
use Prophecy\Prophecy\ObjectProphecy;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Zend\Expressive\Exception\InvalidMiddlewareException;
use Zend\Expressive\Middleware\LazyLoadingMiddleware;
use Zend\Expressive\MiddlewareContainer;

class LazyLoadingMiddlewareTest extends TestCase
{
    /** @var MiddlewareContainer|ObjectProphecy */
    private $container;

    /** @var ServerRequestInterface|ObjectProphecy */
    private $request;

    /** @var RequestHandlerInterface|ObjectProphecy */
    private $handler;

    protected function setUp()
    {
        $this->container = $this->prophesize(MiddlewareContainer::class);
        $this->request   = $this->prophesize(ServerRequestInterface::class);
        $this->handler   = $this->prophesize(RequestHandlerInterface::class);
    }

    private function buildLazyLoadingMiddleware(string $middlewareName) : LazyLoadingMiddleware
    {
        return new LazyLoadingMiddleware(
            $this->container->reveal(),
            $middlewareName
        );
    }

    public function testProcessesMiddlewarePulledFromContainer()
    {
        $response = $this->prophesize(ResponseInterface::class)->reveal();
        $middleware = $this->prophesize(MiddlewareInterface::class);
        $middleware
            ->process(
                $this->request->reveal(),
                $this->handler->reveal()
            )
            ->willReturn($response);

        $this->container->get('foo')->will([$middleware, 'reveal']);

        $lazyloader = $this->buildLazyLoadingMiddleware('foo');
        $this->assertSame(
            $response,
            $lazyloader->process($this->request->reveal(), $this->handler->reveal())
        );
    }

    public function testDoesNotCatchContainerExceptions()
    {
        $exception = new InvalidMiddlewareException();
        $this->container->get('foo')->willThrow($exception);

        $lazyloader = $this->buildLazyLoadingMiddleware('foo');
        $this->expectException(InvalidMiddlewareException::class);
        $lazyloader->process($this->request->reveal(), $this->handler->reveal());
    }
}
