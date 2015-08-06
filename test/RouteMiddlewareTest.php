<?php
/**
 * Zend Framework (http://framework.zend.com/)
 *
 * @see       https://github.com/zendframework/zend-diactoros for the canonical source repository
 * @copyright Copyright (c) 2015 Zend Technologies USA Inc. (http://www.zend.com)
 * @license   https://github.com/zendframework/zend-diactoros/blob/master/LICENSE.md New BSD License
 */

namespace ZendTest\Expressive;

use PHPUnit_Framework_TestCase as TestCase;
use Zend\Diactoros\ServerRequest;
use Zend\Diactoros\Response;
use Zend\Expressive\Application;
use Zend\Expressive\Router\RouteResult;

class RouteMiddlewareTest extends TestCase
{
    public function setUp()
    {
        $this->router    = $this->prophesize('Zend\Expressive\Router\RouterInterface');
        $this->container = $this->prophesize('Interop\Container\ContainerInterface');
    }

    public function getApplication()
    {
        return new Application(
            $this->router->reveal(),
            $this->container->reveal()
        );
    }

    public function testRoutingFailureDueToHttpMethodCallsNextWithNotAllowedResponse()
    {
        $request  = new ServerRequest();
        $response = new Response();
        $result   = RouteResult::fromRouteFailure(['GET', 'POST']);

        $this->router->match($request)->willReturn($result);

        $next = function ($request, $response) {
            return $response;
        };

        $app  = $this->getApplication();
        $test = $app->routeMiddleware($request, $response, $next);
        $this->assertInstanceOf('Psr\Http\Message\ResponseInterface', $test);
        $this->assertEquals(405, $test->getStatusCode());
        $allow = $test->getHeaderLine('Allow');
        $this->assertContains('GET', $allow);
        $this->assertContains('POST', $allow);
    }

    public function testGeneralRoutingFailureCallsNextWithSameRequestAndResponse()
    {
        $request  = new ServerRequest();
        $response = new Response();
        $result   = RouteResult::fromRouteFailure();

        $this->router->match($request)->willReturn($result);

        $called = false;
        $next = function ($req, $res) use (&$called, $request, $response) {
            $this->assertSame($request, $req);
            $this->assertSame($response, $res);
            $called = true;
        };

        $app = $this->getApplication();
        $app->routeMiddleware($request, $response, $next);
        $this->assertTrue($called);
    }

    public function testRoutingSuccessResolvingToCallableMiddlewareInvokesIt()
    {
        $request  = new ServerRequest();
        $response = new Response();

        $finalResponse = new Response();
        $middleware = function ($request, $response) use ($finalResponse) {
            return $finalResponse;
        };

        $result   = RouteResult::fromRouteMatch(
            '/foo',
            $middleware,
            []
        );

        $this->router->match($request)->willReturn($result);

        $next = function ($request, $response) {
            $this->fail('Should not enter $next');
        };

        $app  = $this->getApplication();
        $test = $app->routeMiddleware($request, $response, $next);
        $this->assertSame($finalResponse, $test);
    }

    public function testRoutingSuccessWithoutMiddlewareRaisesException()
    {
        $request  = new ServerRequest();
        $response = new Response();

        $middleware = (object) [];

        $result   = RouteResult::fromRouteMatch(
            '/foo',
            false,
            []
        );

        $this->router->match($request)->willReturn($result);

        $next = function ($request, $response) {
            $this->fail('Should not enter $next');
        };

        $app = $this->getApplication();
        $this->setExpectedException('Zend\Expressive\Exception\InvalidMiddlewareException', 'does not have');
        $app->routeMiddleware($request, $response, $next);
    }

    public function testRoutingSuccessResolvingToNonCallableNonStringMiddlewareRaisesException()
    {
        $request  = new ServerRequest();
        $response = new Response();

        $middleware = (object) [];

        $result   = RouteResult::fromRouteMatch(
            '/foo',
            $middleware,
            []
        );

        $this->router->match($request)->willReturn($result);

        $next = function ($request, $response) {
            $this->fail('Should not enter $next');
        };

        $app = $this->getApplication();
        $this->setExpectedException('Zend\Expressive\Exception\InvalidMiddlewareException', 'callable');
        $app->routeMiddleware($request, $response, $next);
    }

    public function testRoutingSuccessResolvingToUninvokableMiddlewareRaisesException()
    {
        $request  = new ServerRequest();
        $response = new Response();

        $middleware = (object) [];

        $result   = RouteResult::fromRouteMatch(
            '/foo',
            'not a class',
            []
        );

        $this->router->match($request)->willReturn($result);

        // No container for this one, to ensure we marshal only a potential object instance.
        $app = new Application($this->router->reveal());

        $next = function ($request, $response) {
            $this->fail('Should not enter $next');
        };

        $this->setExpectedException('Zend\Expressive\Exception\InvalidMiddlewareException', 'callable');
        $app->routeMiddleware($request, $response, $next);
    }

    public function testRoutingSuccessResolvingToInvokableMiddlewareCallsIt()
    {
        $request  = new ServerRequest();
        $response = new Response();
        $result   = RouteResult::fromRouteMatch(
            '/foo',
            __NAMESPACE__ . '\TestAsset\InvokableMiddleware',
            []
        );

        $this->router->match($request)->willReturn($result);

        // No container for this one, to ensure we marshal only a potential object instance.
        $app = new Application($this->router->reveal());

        $next = function ($request, $response) {
            $this->fail('Should not enter $next');
        };

        $test = $app->routeMiddleware($request, $response, $next);
        $this->assertInstanceOf('Psr\Http\Message\ResponseInterface', $test);
        $this->assertTrue($test->hasHeader('X-Invoked'));
        $this->assertEquals(__NAMESPACE__ . '\TestAsset\InvokableMiddleware', $test->getHeaderLine('X-Invoked'));
    }

    public function testRoutingSuccessResolvingToContainerMiddlewareCallsIt()
    {
        $request    = new ServerRequest();
        $response   = new Response();
        $middleware = function ($req, $res, $next) {
            return $res->withHeader('X-Middleware', 'Invoked');
        };

        $result   = RouteResult::fromRouteMatch(
            '/foo',
            'TestAsset\Middleware',
            []
        );

        $this->router->match($request)->willReturn($result);

        $this->container->has('TestAsset\Middleware')->willReturn(true);
        $this->container->get('TestAsset\Middleware')->willReturn($middleware);

        $app = $this->getApplication();

        $next = function ($request, $response) {
            $this->fail('Should not enter $next');
        };

        $test = $app->routeMiddleware($request, $response, $next);
        $this->assertInstanceOf('Psr\Http\Message\ResponseInterface', $test);
        $this->assertTrue($test->hasHeader('X-Middleware'));
        $this->assertEquals('Invoked', $test->getHeaderLine('X-Middleware'));
    }

    public function testRoutingSuccessResultingInContainerExceptionReRaisesException()
    {
        $request    = new ServerRequest();
        $response   = new Response();

        $result   = RouteResult::fromRouteMatch(
            '/foo',
            'TestAsset\Middleware',
            []
        );

        $this->router->match($request)->willReturn($result);

        $this->container->has('TestAsset\Middleware')->willReturn(true);
        $this->container->get('TestAsset\Middleware')->willThrow(new TestAsset\ContainerException());

        $app = $this->getApplication();

        $next = function ($request, $response) {
            $this->fail('Should not enter $next');
        };

        $this->setExpectedException('Zend\Expressive\Exception\InvalidMiddlewareException', 'retrieve');
        $app->routeMiddleware($request, $response, $next);
    }
}
