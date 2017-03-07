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
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Zend\Diactoros\Response;
use Zend\Expressive\Middleware\ImplicitOptionsMiddleware;
use Zend\Expressive\Router\Route;
use Zend\Expressive\Router\RouteResult;

class ImplicitOptionsMiddlewareTest extends TestCase
{
    public function testNonOptionsRequestInvokesNext()
    {
        $request = $this->prophesize(ServerRequestInterface::class);
        $request->getMethod()->willReturn(RequestMethod::METHOD_GET);
        $request->getAttribute(RouteResult::class, false)->shouldNotBeCalled();

        $response = $this->prophesize(ResponseInterface::class)->reveal();

        $delegate = $this->prophesize(DelegateInterface::class);
        $delegate->process($request->reveal())->willReturn($response);

        $middleware = new ImplicitOptionsMiddleware();
        $result = $middleware->process($request->reveal(), $delegate->reveal());
        $this->assertSame($response, $result);
    }

    public function testMissingRouteResultInvokesNext()
    {
        $request = $this->prophesize(ServerRequestInterface::class);
        $request->getMethod()->willReturn(RequestMethod::METHOD_OPTIONS);
        $request->getAttribute(RouteResult::class, false)->willReturn(false);

        $response = $this->prophesize(ResponseInterface::class)->reveal();

        $delegate = $this->prophesize(DelegateInterface::class);
        $delegate->process($request->reveal())->willReturn($response);

        $middleware = new ImplicitOptionsMiddleware();
        $result = $middleware->process($request->reveal(), $delegate->reveal());
        $this->assertSame($response, $result);
    }

    public function testMissingRouteInRouteResultInvokesNext()
    {
        $result = $this->prophesize(RouteResult::class);
        $result->getMatchedRoute()->willReturn(null);

        $request = $this->prophesize(ServerRequestInterface::class);
        $request->getMethod()->willReturn(RequestMethod::METHOD_OPTIONS);
        $request->getAttribute(RouteResult::class, false)->will([$result, 'reveal']);

        $response = $this->prophesize(ResponseInterface::class)->reveal();

        $delegate = $this->prophesize(DelegateInterface::class);
        $delegate->process($request->reveal())->willReturn($response);

        $middleware = new ImplicitOptionsMiddleware();
        $result = $middleware->process($request->reveal(), $delegate->reveal());
        $this->assertSame($response, $result);
    }

    public function testOptionsRequestWhenRouteDefinesOptionsInvokesNext()
    {
        $route = $this->prophesize(Route::class);
        $route->implicitOptions()->willReturn(false);

        $result = $this->prophesize(RouteResult::class);
        $result->getMatchedRoute()->will([$route, 'reveal']);

        $request = $this->prophesize(ServerRequestInterface::class);
        $request->getMethod()->willReturn(RequestMethod::METHOD_OPTIONS);
        $request->getAttribute(RouteResult::class, false)->will([$result, 'reveal']);

        $response = $this->prophesize(ResponseInterface::class)->reveal();

        $delegate = $this->prophesize(DelegateInterface::class);
        $delegate->process($request->reveal())->willReturn($response);

        $middleware = new ImplicitOptionsMiddleware();
        $result = $middleware->process($request->reveal(), $delegate->reveal());
        $this->assertSame($response, $result);
    }

    public function testWhenNoResponseProvidedToConstructorImplicitOptionsRequestCreatesResponse()
    {
        $allowedMethods = [RequestMethod::METHOD_GET, RequestMethod::METHOD_POST];

        $route = $this->prophesize(Route::class);
        $route->implicitOptions()->willReturn(true);
        $route->getAllowedMethods()->willReturn($allowedMethods);

        $result = $this->prophesize(RouteResult::class);
        $result->getMatchedRoute()->will([$route, 'reveal']);

        $request = $this->prophesize(ServerRequestInterface::class);
        $request->getMethod()->willReturn(RequestMethod::METHOD_OPTIONS);
        $request->getAttribute(RouteResult::class, false)->will([$result, 'reveal']);

        $response = $this->prophesize(ResponseInterface::class)->reveal();

        $delegate = $this->prophesize(DelegateInterface::class);
        $delegate->process($request->reveal())->shouldNotBeCalled();

        $middleware = new ImplicitOptionsMiddleware();
        $result = $middleware->process($request->reveal(), $delegate->reveal());
        $this->assertInstanceOf(Response::class, $result);
        $this->assertNotSame($response, $result);
        $this->assertEquals(StatusCode::STATUS_OK, $result->getStatusCode());
        $this->assertEquals(implode(',', $allowedMethods), $result->getHeaderLine('Allow'));
    }

    public function testInjectsAllowHeaderInResponseProvidedToConstructorDuringOptionsRequest()
    {
        $allowedMethods = [RequestMethod::METHOD_GET, RequestMethod::METHOD_POST];

        $route = $this->prophesize(Route::class);
        $route->implicitOptions()->willReturn(true);
        $route->getAllowedMethods()->willReturn($allowedMethods);

        $result = $this->prophesize(RouteResult::class);
        $result->getMatchedRoute()->will([$route, 'reveal']);

        $request = $this->prophesize(ServerRequestInterface::class);
        $request->getMethod()->willReturn(RequestMethod::METHOD_OPTIONS);
        $request->getAttribute(RouteResult::class, false)->will([$result, 'reveal']);

        $delegate = $this->prophesize(DelegateInterface::class);
        $delegate->process($request->reveal())->shouldNotBeCalled();

        $expected = $this->prophesize(ResponseInterface::class);
        $expected->withHeader('Allow', implode(',', $allowedMethods))->will([$expected, 'reveal']);

        $middleware = new ImplicitOptionsMiddleware($expected->reveal());
        $result = $middleware->process($request->reveal(), $delegate->reveal());
        $this->assertSame($expected->reveal(), $result);
    }
}
