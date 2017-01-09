<?php
/**
 * Zend Framework (http://framework.zend.com/)
 *
 * @see       https://github.com/zendframework/zend-expressive for the canonical source repository
 * @copyright Copyright (c) 2015-2016 Zend Technologies USA Inc. (http://www.zend.com)
 * @license   https://github.com/zendframework/zend-expressive/blob/master/LICENSE.md New BSD License
 */

namespace ZendTest\Expressive;

use PHPUnit_Framework_TestCase as TestCase;
use Prophecy\Argument;
use Prophecy\Prophecy\ObjectProphecy;
use Psr\Http\Message\ResponseInterface;
use SplQueue;
use Zend\Diactoros\Response;
use Zend\Diactoros\ServerRequest;
use Zend\Expressive\Application;
use Zend\Expressive\Exception\InvalidMiddlewareException;
use Zend\Expressive\Router\AuraRouter;
use Zend\Expressive\Router\FastRouteRouter;
use Zend\Expressive\Router\RouteResult;
use Zend\Expressive\Router\RouteResultObserverInterface;
use Zend\Expressive\Router\RouterInterface;
use Zend\Expressive\Router\ZendRouter;
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

    /**
     * @todo Remove for 1.1.0. In that version, you either raise throwables, or
     *     you opt in to the legacy error handling, and understand you will receive
     *     deprecation notices.
     * @group 419
     */
    public function testRoutingFailureDueToHttpMethodCallsNextWithoutEmittingDeprecationNotice()
    {
        $request  = new ServerRequest();
        $response = new Response();
        $result   = RouteResult::fromRouteFailure(['GET', 'POST']);

        $this->router->match($request)->willReturn($result);

        $route = new Route('/', function ($error, $request, $response, $next) {
            $this->assertEquals(405, $error);
            $this->assertEquals(405, $response->getStatusCode());
            return $response;
        });

        $queue = new SplQueue();
        $queue->enqueue($route);

        $done = function ($request, $response, $error = false) {
            $this->fail('Should not reach final handler, but did');
        };

        $next = new Next($queue, $done);

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

        $result = RouteResult::fromRouteMatch(
            '/foo',
            $middleware,
            []
        );

        $this->router->match($request)->willReturn($result);
        $request = $request->withAttribute(RouteResult::class, $result);

        $next = function ($request, $response) {
            $this->fail('Should not enter $next');
        };

        $app = $this->getApplication();
        $test = $app->dispatchMiddleware($request, $response, $next);
        $this->assertSame($finalResponse, $test);
    }

    public function testRoutingSuccessWithoutMiddlewareRaisesExceptionInDispatch()
    {
        $request  = new ServerRequest();
        $response = new Response();

        $middleware = (object) [];

        $result = RouteResult::fromRouteMatch(
            '/foo',
            false,
            []
        );

        $this->router->match($request)->willReturn($result);
        $request = $request->withAttribute(RouteResult::class, $result);

        $next = function ($request, $response) {
            $this->fail('Should not enter $next');
        };

        $app = $this->getApplication();
        $this->setExpectedException(InvalidMiddlewareException::class, 'does not have');
        $app->dispatchMiddleware($request, $response, $next);
    }

    public function testRoutingSuccessResolvingToNonCallableNonStringMiddlewareRaisesExceptionAtDispatch()
    {
        $request  = new ServerRequest();
        $response = new Response();

        $middleware = (object) [];

        $result = RouteResult::fromRouteMatch(
            '/foo',
            $middleware,
            []
        );

        $this->router->match($request)->willReturn($result);
        $request = $request->withAttribute(RouteResult::class, $result);

        $next = function ($request, $response) {
            $this->fail('Should not enter $next');
        };

        $app = $this->getApplication();
        $this->setExpectedException(InvalidMiddlewareException::class, 'callable');
        $app->dispatchMiddleware($request, $response, $next);
    }

    public function testRoutingSuccessResolvingToUninvokableMiddlewareRaisesExceptionAtDispatch()
    {
        $request  = new ServerRequest();
        $response = new Response();

        $middleware = (object) [];

        $result = RouteResult::fromRouteMatch(
            '/foo',
            'not a class',
            []
        );

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
        $result   = RouteResult::fromRouteMatch(
            '/foo',
            __NAMESPACE__ . '\TestAsset\InvokableMiddleware',
            []
        );

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

        $result = RouteResult::fromRouteMatch(
            '/foo',
            'TestAsset\Middleware',
            []
        );

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
        $result   = $app->routeMiddleware($request, $response, function ($request, $response) use ($app, $next) {
            return $app->dispatchMiddleware($request, $response, $next);
        });
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
        $result   = RouteResult::fromRouteMatch('resource', $middleware, $matches);

        $this->router->match($request)->willReturn($result);

        $app  = $this->getApplication();
        $test = $app->routeMiddleware($request, $response, function ($request, $response) use ($app, $next) {
            return $app->dispatchMiddleware($request, $response, $next);
        });
        $this->assertSame($response, $test);
        $this->assertTrue($triggered);
    }

    public function testMiddlewareTriggersObserversWithSuccessfulRouteResult()
    {
        $matches    = ['id' => 'IDENTIFIER'];
        $triggered  = false;
        $middleware = function ($request, $response, $next) {
            return $response;
        };
        $next = function ($request, $response, $err = null) {
            $this->fail('Should not hit next');
        };

        $request  = new ServerRequest();
        $response = new Response();
        $result   = RouteResult::fromRouteMatch('resource', $middleware, $matches);

        $routeResultObserver = $this->prophesize(RouteResultObserverInterface::class);
        $routeResultObserver->update($result)->shouldBeCalled();
        $this->router->match($request)->willReturn($result);

        $app  = $this->getApplication();

        $app->attachRouteResultObserver($routeResultObserver->reveal());

        $test = $app->routeMiddleware($request, $response, function ($request, $response) use ($app, $next) {
            return $app->routeResultObserverMiddleware(
                $request,
                $response,
                function ($request, $response) use ($app, $next) {
                    return $app->dispatchMiddleware($request, $response, $next);
                }
            );
        });
        $this->assertSame($response, $test);
    }

    public function testCanDetachRouteResultObservers()
    {
        $routeResultObserver = $this->prophesize(RouteResultObserverInterface::class);
        $routeResultObserver->update(Argument::any())->shouldNotBeCalled();

        $app = $this->getApplication();
        $app->attachRouteResultObserver($routeResultObserver->reveal());

        $app->detachRouteResultObserver($routeResultObserver->reveal());
        $this->assertAttributeNotContains($routeResultObserver->reveal(), 'routeResultObservers', $app);
    }

    public function testDetachedRouteResultObserverIsNotTriggered()
    {
        $matches    = ['id' => 'IDENTIFIER'];
        $triggered  = false;
        $middleware = function ($request, $response, $next) {
            return $response;
        };
        $next = function ($request, $response, $err = null) {
            $this->fail('Should not hit next');
        };

        $request  = new ServerRequest();
        $response = new Response();
        $result   = RouteResult::fromRouteMatch('resource', $middleware, $matches);

        $routeResultObserver = $this->prophesize(RouteResultObserverInterface::class);
        $routeResultObserver->update($result)->shouldNotBeCalled();
        $this->router->match($request)->willReturn($result);

        $app  = $this->getApplication();

        $app->attachRouteResultObserver($routeResultObserver->reveal());
        $this->assertAttributeContains($routeResultObserver->reveal(), 'routeResultObservers', $app);
        $app->detachRouteResultObserver($routeResultObserver->reveal());
        $this->assertAttributeNotContains($routeResultObserver->reveal(), 'routeResultObservers', $app);

        $test = $app->routeMiddleware($request, $response, function ($request, $response) use ($app, $next) {
            return $app->routeResultObserverMiddleware(
                $request,
                $response,
                function ($request, $response) use ($app, $next) {
                    return $app->dispatchMiddleware($request, $response, $next);
                }
            );
        });
        $this->assertSame($response, $test);
    }

    public function testDetachingUnrecognizedRouteResultObserverDoesNothing()
    {
        $routeResultObserver = $this->prophesize(RouteResultObserverInterface::class);
        $routeResultObserver->update(Argument::any())->shouldNotBeCalled();

        $app = $this->getApplication();
        $this->assertAttributeNotContains($routeResultObserver->reveal(), 'routeResultObservers', $app);

        $app->detachRouteResultObserver($routeResultObserver->reveal());
        $this->assertAttributeNotContains($routeResultObserver->reveal(), 'routeResultObservers', $app);
    }
}
