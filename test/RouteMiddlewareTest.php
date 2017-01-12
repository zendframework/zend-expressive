<?php
/**
 * @see       https://github.com/zendframework/zend-expressive for the canonical source repository
 * @copyright Copyright (c) 2015-2017 Zend Technologies USA Inc. (http://www.zend.com)
 * @license   https://github.com/zendframework/zend-expressive/blob/master/LICENSE.md New BSD License
 */

namespace ZendTest\Expressive;

use Fig\Http\Message\RequestMethodInterface as RequestMethod;
use Fig\Http\Message\StatusCodeInterface as StatusCode;
use PHPUnit_Framework_TestCase as TestCase;
use Prophecy\Argument;
use Prophecy\Prophecy\ObjectProphecy;
use Psr\Http\Message\ResponseInterface;
use SplQueue;
use Zend\Diactoros\Response;
use Zend\Diactoros\ServerRequest;
use Zend\Expressive\Application;
use Zend\Expressive\Exception\InvalidMiddlewareException;
use Zend\Expressive\Middleware;
use Zend\Expressive\Router\AuraRouter;
use Zend\Expressive\Router\FastRouteRouter;
use Zend\Expressive\Router\Route as ExpressiveRoute;
use Zend\Expressive\Router\RouteResult;
use Zend\Expressive\Router\RouteResultObserverInterface;
use Zend\Expressive\Router\RouterInterface;
use Zend\Expressive\Router\ZendRouter;
use Zend\Stratigility\Http\Request as StratigilityRequest;
use Zend\Stratigility\Http\Response as StratigilityResponse;
use Zend\Stratigility\Next;
use Zend\Stratigility\Route;

class RouteMiddlewareTest extends TestCase
{
    use ContainerTrait;

    /** @var ObjectProphecy */
    protected $container;

    public function setUp()
    {
        $this->router    = $this->prophesize(RouterInterface::class);
        $this->container = $this->mockContainerInterface();
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
        $this->assertInstanceOf(ResponseInterface::class, $test);
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

    public function testRoutingSuccessResolvingToCallableMiddlewareCanBeDispatched()
    {
        $request  = new ServerRequest();
        $response = new Response();

        $finalResponse = new Response();
        $middleware = function ($request, $response) use ($finalResponse) {
            return $finalResponse;
        };

        $result = RouteResult::fromRoute(new ExpressiveRoute(
            '/foo',
            $middleware
        ));

        $this->router->match($request)->willReturn($result);
        $request = $request->withAttribute(RouteResult::class, $result);

        $next = function ($request, $response) {
            $this->fail('Should not enter $next');
        };

        $app = $this->getApplication();
        $test = $app->dispatchMiddleware($request, $response, $next);
        $this->assertSame($finalResponse, $test);
    }

    public function testRoutingSuccessResolvingToUninvokableMiddlewareRaisesExceptionAtDispatch()
    {
        $request  = new ServerRequest();
        $response = new Response();

        $middleware = (object) [];

        $result = RouteResult::fromRoute(new ExpressiveRoute(
            '/foo',
            'not a class'
        ));

        $this->router->match($request)->willReturn($result);
        $request = $request->withAttribute(RouteResult::class, $result);

        // No container for this one, to ensure we marshal only a potential object instance.
        $app = new Application($this->router->reveal());

        $next = function ($request, $response) {
            $this->fail('Should not enter $next');
        };

        $this->setExpectedException(InvalidMiddlewareException::class, 'callable');
        $app->dispatchMiddleware($request, $response, $next);
    }

    public function testRoutingSuccessResolvingToInvokableMiddlewareCallsItAtDispatch()
    {
        $request  = new ServerRequest();
        $response = new Response();
        $result   = RouteResult::fromRoute(new ExpressiveRoute(
            '/foo',
            __NAMESPACE__ . '\TestAsset\InvokableMiddleware'
        ));

        $this->router->match($request)->willReturn($result);
        $request = $request->withAttribute(RouteResult::class, $result);

        // No container for this one, to ensure we marshal only a potential object instance.
        $app = new Application($this->router->reveal());

        $next = function ($request, $response) {
            $this->fail('Should not enter $next');
        };

        $test = $app->dispatchMiddleware($request, $response, $next);
        $this->assertInstanceOf(ResponseInterface::class, $test);
        $this->assertTrue($test->hasHeader('X-Invoked'));
        $this->assertEquals(__NAMESPACE__ . '\TestAsset\InvokableMiddleware', $test->getHeaderLine('X-Invoked'));
    }

    public function testRoutingSuccessResolvingToContainerMiddlewareCallsItAtDispatch()
    {
        $request    = new ServerRequest();
        $response   = new Response();
        $middleware = function ($req, $res, $next) {
            return $res->withHeader('X-Middleware', 'Invoked');
        };

        $result = RouteResult::fromRoute(new ExpressiveRoute(
            '/foo',
            'TestAsset\Middleware'
        ));

        $this->router->match($request)->willReturn($result);
        $request = $request->withAttribute(RouteResult::class, $result);

        $this->injectServiceInContainer($this->container, 'TestAsset\Middleware', $middleware);

        $app = $this->getApplication();

        $next = function ($request, $response) {
            $this->fail('Should not enter $next');
        };

        $test = $app->dispatchMiddleware($request, $response, $next);
        $this->assertInstanceOf(ResponseInterface::class, $test);
        $this->assertTrue($test->hasHeader('X-Middleware'));
        $this->assertEquals('Invoked', $test->getHeaderLine('X-Middleware'));
    }

    /**
     * Get the router adapters to test
     */
    public function routerAdapters()
    {
        return [
          'aura'       => [ AuraRouter::class ],
          'fast-route' => [ FastRouteRouter::class ],
          'zf2'        => [ ZendRouter::class ],
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
        $result   = $app->routeMiddleware($request, $response, function ($request, $response) use ($app, $next) {
            return $app->dispatchMiddleware($request, $response, $next);
        });

        $this->assertEquals('Middleware GET', (string) $result->getBody());

        $request  = new ServerRequest([ 'REQUEST_METHOD' => 'POST' ], [], '/foo', 'POST');
        $response = new Response();
        $result   = $app->routeMiddleware($request, $response, function ($request, $response) use ($app, $next) {
            return $app->dispatchMiddleware($request, $response, $next);
        });

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
        $result   = $app->routeMiddleware($request, $response, function ($request, $response) use ($app, $next) {
            return $app->dispatchMiddleware($request, $response, $next);
        });

        $this->assertEquals('Middleware GET', (string) $result->getBody());

        $request  = new ServerRequest([ 'REQUEST_METHOD' => 'POST' ], [], '/foo', 'POST');
        $response = new Response();
        $result   = $app->routeMiddleware($request, $response, function ($request, $response) use ($app, $next) {
            return $app->dispatchMiddleware($request, $response, $next);
        });

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
        $result   = $app->routeMiddleware($request, $response, function ($request, $response) use ($app, $next) {
            return $app->dispatchMiddleware($request, $response, $next);
        });

        $this->assertEquals('Middleware GET', (string) $result->getBody());

        $request  = new ServerRequest([ 'REQUEST_METHOD' => 'POST' ], [], '/foo', 'POST');
        $response = new Response();
        $result   = $app->routeMiddleware($request, $response, function ($request, $response) use ($app, $next) {
            return $app->dispatchMiddleware($request, $response, $next);
        });

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
        $result   = $app->routeMiddleware($request, $response, function ($request, $response) use ($app, $next) {
            return $app->dispatchMiddleware($request, $response, $next);
        });

        $this->assertEquals('Middleware GET', (string) $result->getBody());

        $request  = new ServerRequest([ 'REQUEST_METHOD' => 'POST' ], [], '/foo', 'POST');
        $response = new Response();
        $result   = $app->routeMiddleware($request, $response, function ($request, $response) use ($app, $next) {
            return $app->dispatchMiddleware($request, $response, $next);
        });

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
        $result   = $app->routeMiddleware($request, $response, function ($request, $response) use ($app, $next) {
            return $app->dispatchMiddleware($request, $response, $next);
        });
        $this->assertEquals('Middleware GET, POST', (string) $result->getBody());

        $request  = new ServerRequest([ 'REQUEST_METHOD' => 'POST' ], [], '/foo', 'POST');
        $response = new Response();
        $result   = $app->routeMiddleware($request, $response, function ($request, $response) use ($app, $next) {
            return $app->dispatchMiddleware($request, $response, $next);
        });
        $this->assertEquals('Middleware GET, POST', (string) $result->getBody());

        $request  = new ServerRequest([ 'REQUEST_METHOD' => 'DELETE' ], [], '/foo', 'DELETE');
        $response = new Response();
        $result   = $app->routeMiddleware($request, $response, function ($request, $response) use ($app, $next) {
            return $app->dispatchMiddleware($request, $response, $next);
        });
        $this->assertEquals('Middleware DELETE', (string) $result->getBody());
    }

    public function routerAdaptersForHttpMethods()
    {
        $allMethods = [
            RequestMethod::METHOD_GET,
            RequestMethod::METHOD_POST,
            RequestMethod::METHOD_PUT,
            RequestMethod::METHOD_DELETE,
            RequestMethod::METHOD_PATCH,
            RequestMethod::METHOD_HEAD,
            RequestMethod::METHOD_OPTIONS,
        ];
        foreach ($this->routerAdapters() as $adapterName => $adapter) {
            $adapter = array_pop($adapter);
            foreach ($allMethods as $method) {
                $name = sprintf('%s-%s', $adapterName, $method);
                yield $name => [$adapter, $method];
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
        $result   = $app->routeMiddleware($request, $response, function ($request, $response) use ($app, $next) {
            return $app->dispatchMiddleware($request, $response, $next);
        });
        $this->assertEquals('Middleware', (string) $result->getBody());
    }

    public function allowedMethod()
    {
        return [
            'aura-head'          => [AuraRouter::class, RequestMethod::METHOD_HEAD],
            'aura-options'       => [AuraRouter::class, RequestMethod::METHOD_OPTIONS],
            'fast-route-head'    => [FastRouteRouter::class, RequestMethod::METHOD_HEAD],
            'fast-route-options' => [FastRouteRouter::class, RequestMethod::METHOD_OPTIONS],
            'zf2-head'           => [ZendRouter::class, RequestMethod::METHOD_HEAD],
            'zf2-options'        => [ZendRouter::class, RequestMethod::METHOD_OPTIONS],
        ];
    }

    public function notAllowedMethod()
    {
        return [
            'aura-get'          => [AuraRouter::class, RequestMethod::METHOD_GET],
            'aura-post'         => [AuraRouter::class, RequestMethod::METHOD_POST],
            'aura-put'          => [AuraRouter::class, RequestMethod::METHOD_PUT],
            'aura-delete'       => [AuraRouter::class, RequestMethod::METHOD_DELETE],
            'aura-patch'        => [AuraRouter::class, RequestMethod::METHOD_PATCH],
            'fast-route-post'   => [FastRouteRouter::class, RequestMethod::METHOD_POST],
            'fast-route-put'    => [FastRouteRouter::class, RequestMethod::METHOD_PUT],
            'fast-route-delete' => [FastRouteRouter::class, RequestMethod::METHOD_DELETE],
            'fast-route-patch'  => [FastRouteRouter::class, RequestMethod::METHOD_PATCH],
            'zf2-get'           => [ZendRouter::class, RequestMethod::METHOD_GET],
            'zf2-post'          => [ZendRouter::class, RequestMethod::METHOD_POST],
            'zf2-put'           => [ZendRouter::class, RequestMethod::METHOD_PUT],
            'zf2-delete'        => [ZendRouter::class, RequestMethod::METHOD_DELETE],
            'zf2-patch'         => [ZendRouter::class, RequestMethod::METHOD_PATCH],
        ];
    }

    /**
     * @dataProvider allowedMethod
     *
     * @param string $adapter
     * @param string $method
     */
    public function testAllowedMethodsWhenOnlyPutMethodSet($adapter, $method)
    {
        $app = new Application(new $adapter);
        $app->pipeRoutingMiddleware();
        $app->pipe(new Middleware\ImplicitHeadMiddleware());
        $app->pipe(new Middleware\ImplicitOptionsMiddleware());
        $app->pipeDispatchMiddleware();

        // Add a PUT route
        $app->put('/foo', function ($req, $res, $next) {
            $res->getBody()->write('Middleware');
            return $res;
        });

        $next = function ($req, $res) {
            return $res;
        };

        $request  = new ServerRequest(['REQUEST_METHOD' => $method], [], '/foo', $method);
        $response = new Response();
        $result   = $app($request, $response, $next);

        $this->assertEquals(StatusCode::STATUS_OK, $result->getStatusCode());
        $this->assertEquals('', (string) $result->getBody());
    }

    /**
     * @dataProvider allowedMethod
     *
     * @param string $adapter
     * @param string $method
     */
    public function testAllowedMethodsWhenNoHttpMethodsSet($adapter, $method)
    {
        $app = new Application(new $adapter);
        $app->pipeRoutingMiddleware();
        $app->pipe(new Middleware\ImplicitHeadMiddleware());
        $app->pipe(new Middleware\ImplicitOptionsMiddleware());
        $app->pipeDispatchMiddleware();

        // Add a route with empty array - NO HTTP methods
        $app->route('/foo', function ($req, $res, $next) {
            $res->getBody()->write('Middleware');
            return $res;
        }, []);

        $next = function ($req, $res) {
            return $res;
        };

        $request  = new ServerRequest(['REQUEST_METHOD' => $method], [], '/foo', $method);
        $response = new Response();
        $result   = $app($request, $response, $next);

        $this->assertEquals(StatusCode::STATUS_OK, $result->getStatusCode());
        $this->assertEquals('', (string) $result->getBody());
    }

    /**
     * @dataProvider notAllowedMethod
     *
     * @param string $adapter
     * @param string $method
     */
    public function testNotAllowedMethodWhenNoHttpMethodsSet($adapter, $method)
    {
        $app = new Application(new $adapter);

        // Add a route with empty array - NO HTTP methods
        $app->route('/foo', function ($req, $res, $next) {
            $res->getBody()->write('Middleware');
            return $res;
        }, []);

        $next = function ($req, $res) {
            return $res;
        };

        $request  = new ServerRequest(['REQUEST_METHOD' => $method], [], '/foo', $method);
        $response = new Response();
        $result   = $app->routeMiddleware($request, $response, function ($request, $response) use ($app, $next) {
            return $app->dispatchMiddleware($request, $response, $next);
        });
        $this->assertEquals(StatusCode::STATUS_METHOD_NOT_ALLOWED, $result->getStatusCode());
        $this->assertNotContains('Middleware', (string) $result->getBody());
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
            return $res;
        };

        $request  = new ServerRequest([ 'REQUEST_METHOD' => 'GET' ], [], '/foo', 'GET');
        $response = new Response();
        $result   = $app->routeMiddleware($request, $response, $next);
        $this->assertInstanceOf(Response::class, $result);
        $this->assertNotEquals(405, $result->getStatusCode());
    }

    /**
     * @group 186
     */
    public function testInjectsRouteResultAsAttribute()
    {
        $matches    = ['id' => 'IDENTIFIER'];
        $triggered  = false;
        $middleware = function ($request, $response, $next) use ($matches, &$triggered) {
            $routeResult = $request->getAttribute(RouteResult::class, false);
            $this->assertInstanceOf(RouteResult::class, $routeResult);
            $this->assertTrue($routeResult->isSuccess());
            $this->assertSame($matches, $routeResult->getMatchedParams());
            $triggered = true;
            return $response;
        };
        $next = function ($request, $response, $err = null) {
            $this->fail('Should not hit next');
        };

        $request  = new ServerRequest();
        $response = new Response();
        $result   = RouteResult::fromRoute(new ExpressiveRoute('resource', $middleware), $matches);

        $this->router->match($request)->willReturn($result);

        $app  = $this->getApplication();
        $test = $app->routeMiddleware($request, $response, function ($request, $response) use ($app, $next) {
            return $app->dispatchMiddleware($request, $response, $next);
        });
        $this->assertSame($response, $test);
        $this->assertTrue($triggered);
    }

    /**
     * @error-handling
     */
    public function testRoutingMiddlewareShouldReturn405ResponseDirectlyWhenRaiseThrowablesFlagEnabled()
    {
        $request  = new ServerRequest();
        $response = new Response();
        $result   = RouteResult::fromRouteFailure(['GET', 'POST']);

        $this->router->match($request)->willReturn($result);

        $next = function ($request, $response) {
            $this->fail('Next called when it should not have been');
        };

        $app = $this->getApplication();
        $app->raiseThrowables();

        $test = $app->routeMiddleware($request, $response, $next);
        $this->assertInstanceOf(ResponseInterface::class, $test);
        $this->assertEquals(405, $test->getStatusCode());
        $allow = $test->getHeaderLine('Allow');
        $this->assertContains('GET', $allow);
        $this->assertContains('POST', $allow);
    }
}
