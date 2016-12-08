<?php
/**
 * @see       https://github.com/zendframework/zend-expressive for the canonical source repository
 * @copyright Copyright (c) 2016 Zend Technologies USA Inc. (http://www.zend.com)
 * @license   https://github.com/zendframework/zend-expressive/blob/master/LICENSE.md New BSD License
 */

namespace ZendTest\Expressive;

use Interop\Container\ContainerInterface;
use Interop\Http\Middleware\DelegateInterface;
use Interop\Http\Middleware\ServerMiddlewareInterface;
use PHPUnit_Framework_TestCase as TestCase;
use Prophecy\Argument;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Zend\Diactoros\Response;
use Zend\Diactoros\ServerRequest;
use Zend\Expressive\Application;
use Zend\Expressive\Router\RouteResult;
use Zend\Expressive\Router\RouterInterface;

/**
 * @group http-interop
 */
class ApplicationMarshalLazyInteropServiceTest extends TestCase
{
    public function setUp()
    {
        $this->container = $this->prophesize(ContainerInterface::class);
        $this->router = $this->prophesize(RouterInterface::class);
    }

    public function createApplication()
    {
        return new Application(
            $this->router->reveal(),
            $this->container->reveal()
        );
    }

    public function testCanPipeAndDispatchHttpInteropMiddleware()
    {
        $expected = $this->prophesize(ResponseInterface::class);

        $middleware = $this->prophesize(ServerMiddlewareInterface::class);
        $middleware
            ->process(
                Argument::type(ServerRequestInterface::class),
                Argument::type(DelegateInterface::class)
            )
            ->will([$expected, 'reveal']);

        $this->container->has('TestMiddleware')->willReturn(true);
        $this->container->get('TestMiddleware')->will([$middleware, 'reveal']);

        $application = $this->createApplication();
        $application->pipe('TestMiddleware');

        $response = $application(
            new ServerRequest([], [], 'https://example.com/', 'GET'),
            new Response(),
            function () {
                $this->fail('Final handler invoked, and should not have been');
            }
        );

        $this->assertSame($expected->reveal(), $response);
    }

    public function testCanPipeAndDispatchCallableHttpInteropMiddleware()
    {
        $expected = $this->prophesize(ResponseInterface::class);

        $middleware = function (ServerRequestInterface $request, DelegateInterface $delegate) use ($expected) {
            return $expected->reveal();
        };

        $this->container->has('TestMiddleware')->willReturn(true);
        $this->container->get('TestMiddleware')->willReturn($middleware);

        $application = $this->createApplication();
        $application->pipe('TestMiddleware');

        $response = $application(
            new ServerRequest([], [], 'https://example.com/', 'GET'),
            new Response(),
            function () {
                $this->fail('Final handler invoked, and should not have been');
            }
        );

        $this->assertSame($expected->reveal(), $response);
    }

    public function testCanRouteHttpInteropMiddleware()
    {
        $expected = $this->prophesize(ResponseInterface::class);

        $middleware = $this->prophesize(ServerMiddlewareInterface::class);
        $middleware
            ->process(
                Argument::type(ServerRequestInterface::class),
                Argument::type(DelegateInterface::class)
            )
            ->will([$expected, 'reveal']);

        $this->container->has('TestMiddleware')->willReturn(true);
        $this->container->get('TestMiddleware')->will([$middleware, 'reveal']);

        $application = $this->createApplication();
        $application->get('/foo', 'TestMiddleware');

        $routeResult = $this->prophesize(RouteResult::class);
        $routeResult->getMatchedMiddleware()->willReturn('TestMiddleware');

        $request = (new ServerRequest([], [], 'https://example.com/foo', 'GET'))
            ->withAttribute(RouteResult::class, $routeResult->reveal());

        $response = $application->dispatchMiddleware(
            $request,
            new Response(),
            function () {
                $this->fail('Final handler invoked, and should not have been');
            }
        );

        $this->assertSame($expected->reveal(), $response);
    }

    public function testCanRouteCallableHttpInteropMiddleware()
    {
        $expected = $this->prophesize(ResponseInterface::class);

        $middleware = function (ServerRequestInterface $request, DelegateInterface $delegate) use ($expected) {
            return $expected->reveal();
        };

        $this->container->has('TestMiddleware')->willReturn(true);
        $this->container->get('TestMiddleware')->willReturn($middleware);

        $application = $this->createApplication();
        $application->get('/foo', 'TestMiddleware');

        $routeResult = $this->prophesize(RouteResult::class);
        $routeResult->getMatchedMiddleware()->willReturn('TestMiddleware');

        $request = (new ServerRequest([], [], 'https://example.com/foo', 'GET'))
            ->withAttribute(RouteResult::class, $routeResult->reveal());

        $response = $application->dispatchMiddleware(
            $request,
            new Response(),
            function () {
                $this->fail('Final handler invoked, and should not have been');
            }
        );

        $this->assertSame($expected->reveal(), $response);
    }
}
