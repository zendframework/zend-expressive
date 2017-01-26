<?php
/**
 * @see       https://github.com/zendframework/zend-expressive for the canonical source repository
 * @copyright Copyright (c) 2016-2017 Zend Technologies USA Inc. (http://www.zend.com)
 * @license   https://github.com/zendframework/zend-expressive/blob/master/LICENSE.md New BSD License
 */

namespace ZendTest\Expressive\Middleware;

use Fig\Http\Message\RequestMethodInterface as RequestMethod;
use Fig\Http\Message\StatusCodeInterface as StatusCode;
use Interop\Http\ServerMiddleware\DelegateInterface;
use PHPUnit\Framework\TestCase;
use Prophecy\Argument;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\StreamInterface;
use Zend\Diactoros\Response;
use Zend\Expressive\Middleware\ImplicitHeadMiddleware;
use Zend\Expressive\Router\Route;
use Zend\Expressive\Router\RouteResult;

class ImplicitHeadMiddlewareTest extends TestCase
{
    public function testReturnsResultOfNextOnNonHeadRequests()
    {
        $request = $this->prophesize(ServerRequestInterface::class);
        $request->getMethod()->willReturn(RequestMethod::METHOD_GET);

        $response = $this->prophesize(ResponseInterface::class)->reveal();

        $delegate = $this->prophesize(DelegateInterface::class);
        $delegate->process($request->reveal())
            ->willReturn($response);

        $middleware = new ImplicitHeadMiddleware();
        $result = $middleware->process($request->reveal(), $delegate->reveal());

        $this->assertSame($response, $result);
    }

    public function testReturnsResultOfNextWhenNoRouteResultPresentInRequest()
    {
        $request = $this->prophesize(ServerRequestInterface::class);
        $request->getMethod()->willReturn(RequestMethod::METHOD_HEAD);
        $request->getAttribute(RouteResult::class, false)->willReturn(false);

        $response = $this->prophesize(ResponseInterface::class)->reveal();

        $delegate = $this->prophesize(DelegateInterface::class);
        $delegate->process($request->reveal())
            ->willReturn($response);

        $middleware = new ImplicitHeadMiddleware();
        $result = $middleware->process($request->reveal(), $delegate->reveal());

        $this->assertSame($response, $result);
    }

    public function testReturnsResultOfNextWhenRouteResultDoesNotComposeRoute()
    {
        $result = $this->prophesize(RouteResult::class);
        $result->getMatchedRoute()->willReturn(null);

        $request = $this->prophesize(ServerRequestInterface::class);
        $request->getMethod()->willReturn(RequestMethod::METHOD_HEAD);
        $request->getAttribute(RouteResult::class, false)->will([$result, 'reveal']);

        $response = $this->prophesize(ResponseInterface::class)->reveal();

        $delegate = $this->prophesize(DelegateInterface::class);
        $delegate->process($request->reveal())
            ->willReturn($response);

        $middleware = new ImplicitHeadMiddleware();
        $result = $middleware->process($request->reveal(), $delegate->reveal());

        $this->assertSame($response, $result);
    }

    public function testReturnsResultOfNextWhenRouteSupportsHeadExplicitly()
    {
        $route = $this->prophesize(Route::class);
        $route->implicitHead()->willReturn(false);

        $result = $this->prophesize(RouteResult::class);
        $result->getMatchedRoute()->will([$route, 'reveal']);

        $request = $this->prophesize(ServerRequestInterface::class);
        $request->getMethod()->willReturn(RequestMethod::METHOD_HEAD);
        $request->getAttribute(RouteResult::class, false)->will([$result, 'reveal']);

        $response = $this->prophesize(ResponseInterface::class)->reveal();

        $delegate = $this->prophesize(DelegateInterface::class);
        $delegate->process($request->reveal())
            ->willReturn($response);

        $middleware = new ImplicitHeadMiddleware();
        $result = $middleware->process($request->reveal(), $delegate->reveal());

        $this->assertSame($response, $result);
    }

    public function testReturnsNewResponseWhenRouteImplicitlySupportsHeadAndDoesNotSupportGet()
    {
        $route = $this->prophesize(Route::class);
        $route->implicitHead()->willReturn(true);
        $route->allowsMethod(RequestMethod::METHOD_GET)->willReturn(false);

        $result = $this->prophesize(RouteResult::class);
        $result->getMatchedRoute()->will([$route, 'reveal']);

        $request = $this->prophesize(ServerRequestInterface::class);
        $request->getMethod()->willReturn(RequestMethod::METHOD_HEAD);
        $request->getAttribute(RouteResult::class, false)->will([$result, 'reveal']);

        $response = $this->prophesize(ResponseInterface::class)->reveal();

        $delegate = $this->prophesize(DelegateInterface::class);
        $delegate->process($request->reveal())->shouldNotBeCalled($response);

        $middleware = new ImplicitHeadMiddleware();
        $result = $middleware->process($request->reveal(), $delegate->reveal());

        $this->assertInstanceOf(Response::class, $result);
        $this->assertEquals(StatusCode::STATUS_OK, $result->getStatusCode());
        $this->assertEquals('', (string) $result->getBody());
    }

    public function testReturnsComposedResponseWhenPresentWhenRouteImplicitlySupportsHeadAndDoesNotSupportGet()
    {
        $route = $this->prophesize(Route::class);
        $route->implicitHead()->willReturn(true);
        $route->allowsMethod(RequestMethod::METHOD_GET)->willReturn(false);

        $result = $this->prophesize(RouteResult::class);
        $result->getMatchedRoute()->will([$route, 'reveal']);

        $request = $this->prophesize(ServerRequestInterface::class);
        $request->getMethod()->willReturn(RequestMethod::METHOD_HEAD);
        $request->getAttribute(RouteResult::class, false)->will([$result, 'reveal']);

        $response = $this->prophesize(ResponseInterface::class)->reveal();

        $delegate = $this->prophesize(DelegateInterface::class);
        $delegate->process($request->reveal())->shouldNotBeCalled($response);

        $expected   = new Response();
        $middleware = new ImplicitHeadMiddleware($expected);
        $result     = $middleware->process($request->reveal(), $delegate->reveal());

        $this->assertSame($expected, $result);
    }

    public function testInvokesNextWhenRouteImplicitlySupportsHeadAndSupportsGet()
    {
        $route = $this->prophesize(Route::class);
        $route->implicitHead()->willReturn(true);
        $route->allowsMethod(RequestMethod::METHOD_GET)->willReturn(true);

        $result = $this->prophesize(RouteResult::class);
        $result->getMatchedRoute()->will([$route, 'reveal']);

        $request = $this->prophesize(ServerRequestInterface::class);
        $request->getMethod()->willReturn(RequestMethod::METHOD_HEAD);
        $request->getAttribute(RouteResult::class, false)->will([$result, 'reveal']);
        $request->withMethod(RequestMethod::METHOD_GET)->will([$request, 'reveal']);

        $response = $this->prophesize(ResponseInterface::class);
        $response
            ->withBody(Argument::that(function ($body) {
                $this->assertInstanceOf(StreamInterface::class, $body);
                $this->assertEquals('', (string) $body);
                return true;
            }))
            ->will([$response, 'reveal']);

        $delegate = $this->prophesize(DelegateInterface::class);
        $delegate->process($request->reveal())->will([$response, 'reveal']);

        $middleware = new ImplicitHeadMiddleware();
        $result = $middleware->process($request->reveal(), $delegate->reveal());

        $this->assertSame($response->reveal(), $result);
    }
}
