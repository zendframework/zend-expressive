<?php
/**
 * Zend Framework (http://framework.zend.com/)
 *
 * @see       https://github.com/zendframework/zend-expressive for the canonical source repository
 * @copyright Copyright (c) 2015 Zend Technologies USA Inc. (http://www.zend.com)
 * @license   https://github.com/zendframework/zend-expressive/blob/master/LICENSE.md New BSD License
 */

namespace ZendTest\Expressive;

use PHPUnit_Framework_TestCase as TestCase;
use Zend\Diactoros\ServerRequest;
use Zend\Diactoros\Response;
use Zend\Expressive\Application;
use Zend\Expressive\Router\RouteResult;
use Zend\Expressive\Router\RouterInterface;

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

    public function testRoutingFailureDueToHttpMethodCallsNextWithNotAllowedResponseAndError()
    {
        $request  = new ServerRequest();
        $response = new Response();
        $result   = RouteResult::fromRouteFailure(['GET', 'POST']);

        $this->router->match($request)->willReturn($result);

        $next = function ($request, $response, $error = false) {
            $this->assertEquals(405, $error);
            $this->assertEquals(405, $response->getStatusCode());
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
        $next = function ($req, $res, $error = null) use (&$called, $request, $response) {
            $this->assertNull($error);
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

    /**
     * Get the router adapters to test
     */
    public function routerAdapters()
    {
        return [
          'aura'       => [ 'Zend\Expressive\Router\Aura' ],
          'fast-route' => [ 'Zend\Expressive\Router\FastRoute' ],
          'zf2'        => [ 'Zend\Expressive\Router\Zf2' ],
        ];
    }

    /**
     * Create an Application object with 2 routes, a GET and a POST
     * using Application::get() and Application::post()
     *
     * @param string $adapter
     * @param string $getName
     * @param string $postName
     * @return Application
     */
    private function createApplicationWithGetPost($adapter, $getName = null, $postName = null)
    {
        $app = new Application(new $adapter);
        $app->get('/foo', function ($req, $res, $next) {
            $res->getBody()->write('Middleware GET');
            return $res;
        }, $getName);
        $app->post('/foo', function ($req, $res, $next) {
            $res->getBody()->write('Middleware POST');
            return $res;
        }, $postName);

        return $app;
    }

    /**
     * Create an Application object with 2 routes, a GET and a POST
     * using Application::route()
     *
     * @param string $adapter
     * @param string $getName
     * @param string $postName
     * @return Application
     */
    private function createApplicationWithRouteGetPost($adapter, $getName = null, $postName = null)
    {
        $app = new Application(new $adapter);
        $app->route('/foo', function ($req, $res, $next) {
            $res->getBody()->write('Middleware GET');
            return $res;
        }, ['GET'], $getName);
        $app->route('/foo', function ($req, $res, $next) {
            $res->getBody()->write('Middleware POST');
            return $res;
        }, ['POST'], $postName);

        return $app;
    }

    /**
     * @dataProvider routerAdapters
     */
    public function testRoutingNoMatch($adapter)
    {
        $app  = $this->createApplicationWithGetPost($adapter);
        $next = function ($req, $res) {
            return $res;
        };

        $request  = new ServerRequest([ 'REQUEST_METHOD' => 'DELETE' ], [], '/foo', 'DELETE');
        $response = new Response();
        $result   = $app->routeMiddleware($request, $response, $next);
        $this->assertSame(405, $result->getStatusCode());
        $headers = $result->getHeaders();
        $this->assertSame([ 'GET,POST' ], $headers['Allow']);
    }


    /**
     * @see https://github.com/zendframework/zend-expressive/issues/40
     * @group 40
     * @dataProvider routerAdapters
     */
    public function testRoutingWithSamePathWithoutName($adapter)
    {
        $app  = $this->createApplicationWithGetPost($adapter);
        $next = function ($req, $res) {
            return $res;
        };

        $request  = new ServerRequest([ 'REQUEST_METHOD' => 'GET' ], [], '/foo', 'GET');
        $response = new Response();
        $result   = $app->routeMiddleware($request, $response, $next);

        $this->assertEquals('Middleware GET', (string) $result->getBody());

        $request  = new ServerRequest([ 'REQUEST_METHOD' => 'POST' ], [], '/foo', 'POST');
        $response = new Response();
        $result   = $app->routeMiddleware($request, $response, $next);

        $this->assertEquals('Middleware POST', (string) $result->getBody());
    }

    /**
     * @see https://github.com/zendframework/zend-expressive/issues/40
     * @group 40
     * @dataProvider routerAdapters
     */
    public function testRoutingWithSamePathWithName($adapter)
    {
        $app  = $this->createApplicationWithGetPost($adapter, 'foo-get', 'foo-post');
        $next = function ($req, $res) {
            return $res;
        };

        $request  = new ServerRequest([ 'REQUEST_METHOD' => 'GET' ], [], '/foo', 'GET');
        $response = new Response();
        $result   = $app->routeMiddleware($request, $response, $next);

        $this->assertEquals('Middleware GET', (string) $result->getBody());

        $request  = new ServerRequest([ 'REQUEST_METHOD' => 'POST' ], [], '/foo', 'POST');
        $response = new Response();
        $result   = $app->routeMiddleware($request, $response, $next);

        $this->assertEquals('Middleware POST', (string) $result->getBody());
    }

    /**
     * @see https://github.com/zendframework/zend-expressive/issues/40
     * @group 40
     * @dataProvider routerAdapters
     */
    public function testRoutingWithSamePathWithRouteWithoutName($adapter)
    {
        $app  = $this->createApplicationWithRouteGetPost($adapter);
        $next = function ($req, $res) {
            return $res;
        };

        $request  = new ServerRequest([ 'REQUEST_METHOD' => 'GET' ], [], '/foo', 'GET');
        $response = new Response();
        $result   = $app->routeMiddleware($request, $response, $next);

        $this->assertEquals('Middleware GET', (string) $result->getBody());

        $request  = new ServerRequest([ 'REQUEST_METHOD' => 'POST' ], [], '/foo', 'POST');
        $response = new Response();
        $result   = $app->routeMiddleware($request, $response, $next);

        $this->assertEquals('Middleware POST', (string) $result->getBody());
    }

    /**
     * @see https://github.com/zendframework/zend-expressive/issues/40
     * @dataProvider routerAdapters
     */
    public function testRoutingWithSamePathWithRouteWithName($adapter)
    {
        $app  = $this->createApplicationWithRouteGetPost($adapter, 'foo-get', 'foo-post');
        $next = function ($req, $res) {
            return $res;
        };

        $request  = new ServerRequest([ 'REQUEST_METHOD' => 'GET' ], [], '/foo', 'GET');
        $response = new Response();
        $result   = $app->routeMiddleware($request, $response, $next);

        $this->assertEquals('Middleware GET', (string) $result->getBody());

        $request  = new ServerRequest([ 'REQUEST_METHOD' => 'POST' ], [], '/foo', 'POST');
        $response = new Response();
        $result   = $app->routeMiddleware($request, $response, $next);

        $this->assertEquals('Middleware POST', (string) $result->getBody());
    }

    /**
     * @see https://github.com/zendframework/zend-expressive/issues/40
     * @group 40
     * @dataProvider routerAdapters
     */
    public function testRoutingWithSamePathWithRouteWithMultipleMethods($adapter)
    {
        $app = new Application(new $adapter);
        $app->route('/foo', function ($req, $res, $next) {
            $res->getBody()->write('Middleware GET, POST');
            return $res;
        }, [ 'GET', 'POST' ]);
        $app->route('/foo', function ($req, $res, $next) {
            $res->getBody()->write('Middleware DELETE');
            return $res;
        }, [ 'DELETE' ]);
        $next = function ($req, $res) {
            return $res;
        };

        $request  = new ServerRequest([ 'REQUEST_METHOD' => 'GET' ], [], '/foo', 'GET');
        $response = new Response();
        $result   = $app->routeMiddleware($request, $response, $next);
        $this->assertEquals('Middleware GET, POST', (string) $result->getBody());

        $request  = new ServerRequest([ 'REQUEST_METHOD' => 'POST' ], [], '/foo', 'POST');
        $response = new Response();
        $result   = $app->routeMiddleware($request, $response, $next);
        $this->assertEquals('Middleware GET, POST', (string) $result->getBody());

        $request  = new ServerRequest([ 'REQUEST_METHOD' => 'DELETE' ], [], '/foo', 'DELETE');
        $response = new Response();
        $result   = $app->routeMiddleware($request, $response, $next);
        $this->assertEquals('Middleware DELETE', (string) $result->getBody());
    }

    public function routerAdaptersForHttpMethods()
    {
        foreach ($this->routerAdapters() as $adapter) {
            $adapter = array_pop($adapter);
            foreach (['GET', 'POST', 'PUT', 'DELETE', 'PATCH', 'HEAD', 'OPTIONS'] as $method) {
                yield [$adapter, $method];
            }
        }
    }

    /**
     * @dataProvider routerAdaptersForHttpMethods
     */
    public function testMatchWithAllHttpMethods($adapter, $method)
    {
        $app = new Application(new $adapter);

        // Add a route with Zend\Expressive\Router\Route::HTTP_METHOD_ANY
        $app->route('/foo', function ($req, $res, $next) {
            $res->getBody()->write('Middleware');
            return $res;
        });

        $next = function ($req, $res) {
            return $res;
        };

        $request  = new ServerRequest([ 'REQUEST_METHOD' => $method ], [], '/foo', $method);
        $response = new Response();
        $result   = $app->routeMiddleware($request, $response, $next);
        $this->assertEquals('Middleware', (string) $result->getBody());
    }

    /**
     * @dataProvider routerAdapters
     * @group 74
     */
    public function testWithOnlyRootPathRouteDefinedRoutingToSubPathsShouldReturn404($adapter)
    {
        $app = new Application(new $adapter);

        $app->route('/', function ($req, $res, $next) {
            $res->getBody()->write('Middleware');
            return $res;
        }, ['GET']);

        $next = function ($req, $res) {
            return $res->withStatus(404);
        };

        $request  = new ServerRequest([ 'REQUEST_METHOD' => 'GET' ], [], '/foo', 'GET');
        $response = new Response();
        $result   = $app->routeMiddleware($request, $response, $next);
        $this->assertInstanceOf(Response::class, $result);
        $this->assertNotSame($response, $result);
        $this->assertEquals(404, $result->getStatusCode());
    }
}
