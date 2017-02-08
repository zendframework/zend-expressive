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
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Zend\Expressive\Middleware\RouteMiddleware;
use Zend\Expressive\Router\Route;
use Zend\Expressive\Router\RouteResult;
use Zend\Expressive\Router\RouterInterface;

class RouteMiddlewareTest extends TestCase
{
    public function setUp()
    {
        $this->router     = $this->prophesize(RouterInterface::class);
        $this->response   = $this->prophesize(ResponseInterface::class);
        $this->middleware = new RouteMiddleware(
            $this->router->reveal(),
            $this->response->reveal()
        );

        $this->request  = $this->prophesize(ServerRequestInterface::class);
        $this->delegate = $this->prophesize(DelegateInterface::class);
    }

    public function testRoutingFailureDueToHttpMethodCallsNextWithNotAllowedResponseAndError()
    {
        $result = RouteResult::fromRouteFailure(['GET', 'POST']);

        $this->router->match($this->request->reveal())->willReturn($result);
        $this->delegate->process()->shouldNotBeCalled();
        $this->request->withAttribute()->shouldNotBeCalled();
        $this->response->withStatus(405)->will([$this->response, 'reveal']);
        $this->response->withHeader('Allow', 'GET,POST')->will([$this->response, 'reveal']);

        $response = $this->middleware->process($this->request->reveal(), $this->delegate->reveal());
        $this->assertSame($response, $this->response->reveal());
    }

    public function testGeneralRoutingFailureInvokesDelegateWithSameRequest()
    {
        $result = RouteResult::fromRouteFailure();

        $this->router->match($this->request->reveal())->willReturn($result);
        $this->response->withStatus()->shouldNotBeCalled();
        $this->response->withHeader()->shouldNotBeCalled();
        $this->request->withAttribute()->shouldNotBeCalled();

        $expected = $this->prophesize(ResponseInterface::class)->reveal();
        $this->delegate->process($this->request->reveal())->willReturn($expected);

        $response = $this->middleware->process($this->request->reveal(), $this->delegate->reveal());
        $this->assertSame($expected, $response);
    }

    public function testRoutingSuccessDelegatesToNextAfterFirstInjectingRouteResultAndAttributesInRequest()
    {
        $middleware = $this->prophesize(ServerMiddlewareInterface::class)->reveal();
        $parameters = ['foo' => 'bar', 'baz' => 'bat'];
        $result = RouteResult::fromRoute(
            new Route('/foo', $middleware),
            $parameters
        );

        $this->router->match($this->request->reveal())->willReturn($result);

        $this->request
            ->withAttribute(RouteResult::class, $result)
            ->will([$this->request, 'reveal']);
        foreach ($parameters as $key => $value) {
            $this->request->withAttribute($key, $value)->will([$this->request, 'reveal']);
        }

        $this->response->withStatus()->shouldNotBeCalled();
        $this->response->withHeader()->shouldNotBeCalled();

        $expected = $this->prophesize(ResponseInterface::class)->reveal();
        $this->delegate
            ->process($this->request->reveal())
            ->willReturn($expected);

        $response = $this->middleware->process($this->request->reveal(), $this->delegate->reveal());
        $this->assertSame($expected, $response);
    }
}
