<?php
/**
 * @see       https://github.com/zendframework/zend-expressive for the canonical source repository
 * @copyright Copyright (c) 2017 Zend Technologies USA Inc. (http://www.zend.com)
 * @license   https://github.com/zendframework/zend-expressive/blob/master/LICENSE.md New BSD License
 */

namespace ZendTest\Expressive\Middleware;

use Interop\Http\ServerMiddleware\DelegateInterface;
use Interop\Http\ServerMiddleware\MiddlewareInterface as ServerMiddlewareInterface;
use PHPUnit\Framework\TestCase;
use Prophecy\Prophecy\ObjectProphecy;
use Psr\Container\ContainerInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Zend\Expressive\Exception\InvalidMiddlewareException;
use Zend\Expressive\Middleware\LazyLoadingMiddleware;

class LazyLoadingMiddlewareTest extends TestCase
{
    /** @var ContainerInterface|ObjectProphecy */
    private $container;

    /** @var ResponseInterface|ObjectProphecy */
    private $response;

    /** @var ServerRequestInterface|ObjectProphecy */
    private $request;

    /** @var DelegateInterface|ObjectProphecy */
    private $delegate;

    public function setUp()
    {
        $this->container = $this->prophesize(ContainerInterface::class);
        $this->response  = $this->prophesize(ResponseInterface::class);
        $this->request   = $this->prophesize(ServerRequestInterface::class);
        $this->delegate  = $this->prophesize(DelegateInterface::class);
    }

    public function buildLazyLoadingMiddleware($middlewareName)
    {
        return new LazyLoadingMiddleware(
            $this->container->reveal(),
            $this->response->reveal(),
            $middlewareName
        );
    }

    public function testInvokesInteropMiddlewarePulledFromContainer()
    {
        $expected   = $this->prophesize(ResponseInterface::class)->reveal();

        $middleware = $this->prophesize(ServerMiddlewareInterface::class);
        $middleware
            ->process(
                $this->request->reveal(),
                $this->delegate->reveal()
            )
            ->willReturn($expected);

        $this->container->get('middleware')->will([$middleware, 'reveal']);

        $lazyLoadingMiddleware = $this->buildLazyLoadingMiddleware('middleware');
        $this->assertSame(
            $expected,
            $lazyLoadingMiddleware->process($this->request->reveal(), $this->delegate->reveal())
        );
    }

    public function testInvokesDuckTypedInteropMiddlewarePulledFromContainer()
    {
        $expected   = $this->prophesize(ResponseInterface::class)->reveal();

        $middleware = function ($request, DelegateInterface $delegate) use ($expected) {
            return $expected;
        };

        $this->container->get('middleware')->willReturn($middleware);

        $lazyLoadingMiddleware = $this->buildLazyLoadingMiddleware('middleware');
        $this->assertSame(
            $expected,
            $lazyLoadingMiddleware->process($this->request->reveal(), $this->delegate->reveal())
        );
    }

    public function testInvokesDoublePassMiddlewarePulledFromContainerUsingResponsePrototype()
    {
        $expected   = $this->prophesize(ResponseInterface::class)->reveal();

        $middleware = function ($request, $response, callable $next) use ($expected) {
            return $expected;
        };

        $this->container->get('middleware')->willReturn($middleware);

        $lazyLoadingMiddleware = $this->buildLazyLoadingMiddleware('middleware');
        $this->assertSame(
            $expected,
            $lazyLoadingMiddleware->process($this->request->reveal(), $this->delegate->reveal())
        );
    }

    public function invalidMiddleware()
    {
        return [
            'null'                 => [null],
            'true'                 => [true],
            'false'                => [false],
            'zero'                 => [0],
            'int'                  => [1],
            'zero-float'           => [0.0],
            'float'                => [1.1],
            'non-invokable-string' => ['not-real-middleware'],
            'non-invokable-array'  => [['not', 'real', 'middleware']],
            'non-invokable-object' => [(object) ['middleware' => false]],
        ];
    }

    /**
     * @dataProvider invalidMiddleware
     *
     * @param mixed $middleware
     */
    public function testRaisesExceptionIfMiddlewarePulledFromContainerIsInvalid($middleware)
    {
        $this->container->get('middleware')->willReturn($middleware);
        $lazyLoadingMiddleware = $this->buildLazyLoadingMiddleware('middleware');

        $this->expectException(InvalidMiddlewareException::class);
        $lazyLoadingMiddleware->process($this->request->reveal(), $this->delegate->reveal());
    }
}
