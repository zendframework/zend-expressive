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
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Zend\Expressive\Middleware\DispatchMiddleware;
use Zend\Expressive\Router\Route;
use Zend\Expressive\Router\RouteResult;

class DispatchMiddlewareTest extends TestCase
{
    /** @var DispatchMiddleware */
    private $middleware;

    /** @var ServerRequestInterface|ObjectProphecy */
    private $request;

    /** @var DelegateInterface|ObjectProphecy */
    private $delegate;

    public function setUp()
    {
        $this->middleware = new DispatchMiddleware();
        $this->request    = $this->prophesize(ServerRequestInterface::class);
        $this->delegate   = $this->prophesize(DelegateInterface::class);
    }

    public function testInvokesDelegateIfRequestDoesNotContainRouteResult()
    {
        $expected = $this->prophesize(ResponseInterface::class)->reveal();
        $this->request->getAttribute(RouteResult::class, false)->willReturn(false);
        $this->delegate->process($this->request->reveal())->willReturn($expected);

        $response = $this->middleware->process($this->request->reveal(), $this->delegate->reveal());

        $this->assertSame($expected, $response);
    }

    public function testInvokesMatchedMiddlewareWhenRouteResult()
    {
        $this->delegate->process()->shouldNotBeCalled();

        $expected = $this->prophesize(ResponseInterface::class)->reveal();
        $routedMiddleware = $this->prophesize(ServerMiddlewareInterface::class);
        $routedMiddleware
            ->process($this->request->reveal(), $this->delegate->reveal())
            ->willReturn($expected);

        $routeResult = RouteResult::fromRoute(new Route('/', $routedMiddleware->reveal()));

        $this->request->getAttribute(RouteResult::class, false)->willReturn($routeResult);

        $response = $this->middleware->process($this->request->reveal(), $this->delegate->reveal());

        $this->assertSame($expected, $response);
    }
}
