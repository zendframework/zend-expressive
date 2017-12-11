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
use Zend\Expressive\Middleware\DispatchMiddleware;
use Zend\Expressive\Router\Route;
use Zend\Expressive\Router\RouteResult;
use Zend\Expressive\Router\RouterInterface;

class DispatchMiddlewareTest extends TestCase
{
    /** @var ContainerInterface|ObjectProphecy */
    private $container;

    /** @var DelegateInterface|ObjectProphecy */
    private $delegate;

    /** @var DispatchMiddleware */
    private $middleware;

    /** @var ServerRequestInterface|ObjectProphecy */
    private $request;

    /** @var ResponseInterface|ObjectProphecy */
    private $responsePrototype;

    public function setUp()
    {
        $this->container         = $this->prophesize(ContainerInterface::class);
        $this->responsePrototype = $this->prophesize(ResponseInterface::class);
        $this->router            = $this->prophesize(RouterInterface::class);
        $this->request           = $this->prophesize(ServerRequestInterface::class);
        $this->delegate          = $this->prophesize(DelegateInterface::class);
        $this->middleware        = new DispatchMiddleware(
            $this->router->reveal(),
            $this->responsePrototype->reveal(),
            $this->container->reveal()
        );
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

    /**
     * @group 453
     */
    public function testCanDispatchCallableDoublePassMiddleware()
    {
        $this->delegate->process()->shouldNotBeCalled();

        $expected = $this->prophesize(ResponseInterface::class)->reveal();
        $routedMiddleware = function ($request, $response, $next) use ($expected) {
            return $expected;
        };

        $routeResult = RouteResult::fromRoute(new Route('/', $routedMiddleware));

        $this->request->getAttribute(RouteResult::class, false)->willReturn($routeResult);

        $response = $this->middleware->process($this->request->reveal(), $this->delegate->reveal());

        $this->assertSame($expected, $response);
    }

    /**
     * @group 453
     */
    public function testCanDispatchMiddlewareServices()
    {
        $this->delegate->process()->shouldNotBeCalled();

        $expected = $this->prophesize(ResponseInterface::class)->reveal();
        $routedMiddleware = function ($request, $response, $next) use ($expected) {
            return $expected;
        };

        $this->container->has('RoutedMiddleware')->willReturn(true);
        $this->container->get('RoutedMiddleware')->willReturn($routedMiddleware);

        $routeResult = RouteResult::fromRoute(new Route('/', 'RoutedMiddleware'));

        $this->request->getAttribute(RouteResult::class, false)->willReturn($routeResult);

        $response = $this->middleware->process($this->request->reveal(), $this->delegate->reveal());

        $this->assertSame($expected, $response);
    }
}
