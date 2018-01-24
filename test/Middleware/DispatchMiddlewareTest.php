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
use Zend\Expressive\Middleware\DispatchMiddleware;
use Zend\Expressive\Router\Route;
use Zend\Expressive\Router\RouteResult;

class DispatchMiddlewareTest extends TestCase
{
    /** @var RequestHandlerInterface|ObjectProphecy */
    private $handler;

    /** @var DispatchMiddleware */
    private $middleware;

    /** @var ServerRequestInterface|ObjectProphecy */
    private $request;

    /** @var ResponseInterface|ObjectProphecy */
    private $responsePrototype;

    public function setUp()
    {
        $this->response   = $this->prophesize(ResponseInterface::class)->reveal();
        $this->request    = $this->prophesize(ServerRequestInterface::class);
        $this->handler    = $this->prophesize(RequestHandlerInterface::class);
        $this->middleware = new DispatchMiddleware();
    }

    public function testInvokesHandlerIfRequestDoesNotContainRouteResult()
    {
        $this->request->getAttribute(RouteResult::class, false)->willReturn(false);
        $this->handler->handle($this->request->reveal())->willReturn($this->response);

        $response = $this->middleware->process($this->request->reveal(), $this->handler->reveal());

        $this->assertSame($this->response, $response);
    }

    public function testInvokesMatchedMiddlewareWhenRouteResult()
    {
        $this->handler->handle()->shouldNotBeCalled();

        $routedMiddleware = $this->prophesize(MiddlewareInterface::class);
        $routedMiddleware
            ->process($this->request->reveal(), $this->handler->reveal())
            ->willReturn($this->response);

        $routeResult = RouteResult::fromRoute(new Route('/', $routedMiddleware->reveal()));

        $this->request->getAttribute(RouteResult::class, false)->willReturn($routeResult);

        $response = $this->middleware->process($this->request->reveal(), $this->handler->reveal());

        $this->assertSame($this->response, $response);
    }
}
