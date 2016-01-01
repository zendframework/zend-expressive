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
use ReflectionProperty;
use Zend\Diactoros\Response;
use Zend\Diactoros\Response\SapiEmitter;
use Zend\Diactoros\ServerRequest as Request;
use Zend\Diactoros\ServerRequest;
use Zend\Expressive\Application;
use Zend\Expressive\Emitter\EmitterStack;
use Zend\Expressive\Router\Route;
use Zend\Expressive\Router\RouteResult;
use Zend\Stratigility\Route as StratigilityRoute;
use ZendTest\Expressive\TestAsset\InvokableMiddleware;

/**
 * @covers Zend\Expressive\Application
 */
class ApplicationTest extends TestCase
{
    use ContainerTrait;

    public function setUp()
    {
        $this->noopMiddleware = function ($req, $res, $next) {
        };

        $this->router = $this->prophesize('Zend\Expressive\Router\RouterInterface');
    }

    public function getApp()
    {
        return new Application($this->router->reveal());
    }

    public function commonHttpMethods()
    {
        return [
            'GET'    => ['GET'],
            'POST'   => ['POST'],
            'PUT'    => ['PUT'],
            'PATCH'  => ['PATCH'],
            'DELETE' => ['DELETE'],
        ];
    }

    public function testConstructorAcceptsRouterAsAnArgument()
    {
        $app = $this->getApp();
        $this->assertInstanceOf('Zend\Expressive\Application', $app);
    }

    public function testApplicationIsAMiddlewarePipe()
    {
        $app = $this->getApp();
        $this->assertInstanceOf('Zend\Stratigility\MiddlewarePipe', $app);
    }

    public function testRouteMethodReturnsRouteInstance()
    {
        $this->router->addRoute(Argument::type(Route::class))->shouldBeCalled();
        $route = $this->getApp()->route('/foo', $this->noopMiddleware);
        $this->assertInstanceOf(Route::class, $route);
        $this->assertEquals('/foo', $route->getPath());
        $this->assertSame($this->noopMiddleware, $route->getMiddleware());
    }

    public function testAnyRouteMethod()
    {
        $this->router->addRoute(Argument::type(Route::class))->shouldBeCalled();
        $route = $this->getApp()->any('/foo', $this->noopMiddleware);
        $this->assertInstanceOf(Route::class, $route);
        $this->assertEquals('/foo', $route->getPath());
        $this->assertSame($this->noopMiddleware, $route->getMiddleware());
        $this->assertSame(Route::HTTP_METHOD_ANY, $route->getAllowedMethods());
    }

    /**
     * @dataProvider commonHttpMethods
     */
    public function testCanCallRouteWithHttpMethods($method)
    {
        $this->router->addRoute(Argument::type(Route::class))->shouldBeCalled();
        $route = $this->getApp()->route('/foo', $this->noopMiddleware, [$method]);
        $this->assertInstanceOf(Route::class, $route);
        $this->assertEquals('/foo', $route->getPath());
        $this->assertSame($this->noopMiddleware, $route->getMiddleware());
        $this->assertTrue($route->allowsMethod($method));
        $this->assertSame([$method], $route->getAllowedMethods());
    }

    public function testCanCallRouteWithMultipleHttpMethods()
    {
        $this->router->addRoute(Argument::type(Route::class))->shouldBeCalled();
        $methods = array_keys($this->commonHttpMethods());
        $route = $this->getApp()->route('/foo', $this->noopMiddleware, $methods);
        $this->assertInstanceOf(Route::class, $route);
        $this->assertEquals('/foo', $route->getPath());
        $this->assertSame($this->noopMiddleware, $route->getMiddleware());
        $this->assertSame($methods, $route->getAllowedMethods());
    }

    public function testCanCallRouteWithARoute()
    {
        $route = new Route('/foo', $this->noopMiddleware);
        $this->router->addRoute($route)->shouldBeCalled();
        $app   = $this->getApp();
        $test  = $app->route($route);
        $this->assertSame($route, $test);
    }

    public function testCallingRouteWithExistingPathAndOmittingMethodsArgumentRaisesException()
    {
        $this->router->addRoute(Argument::type(Route::class))->shouldBeCalledTimes(1);
        $app = $this->getApp();
        $route = $app->route('/foo', $this->noopMiddleware);
        $this->setExpectedException('DomainException');
        $app->route('/foo', function ($req, $res, $next) {
        });
    }

    public function testCallingRouteWithOnlyAPathRaisesAnException()
    {
        $app = $this->getApp();
        $this->setExpectedException('Zend\Expressive\Exception\InvalidArgumentException');
        $app->route('/path');
    }

    public function invalidPathTypes()
    {
        return [
            'null'       => [null],
            'true'       => [true],
            'false'      => [false],
            'zero'       => [0],
            'int'        => [1],
            'zero-float' => [0.0],
            'float'      => [1.1],
            'array'      => [['path' => 'route']],
            'object'     => [(object) ['path' => 'route']],
        ];
    }

    /**
     * @dataProvider invalidPathTypes
     */
    public function testCallingRouteWithAnInvalidPathTypeRaisesAnException($path)
    {
        $app = $this->getApp();
        $this->setExpectedException('Zend\Expressive\Router\Exception\InvalidArgumentException');
        $app->route($path, 'middleware');
    }

    /**
     * @dataProvider commonHttpMethods
     */
    public function testCommonHttpMethodsAreExposedAsClassMethodsAndReturnRoutes($method)
    {
        $app = $this->getApp();
        $route = $app->{$method}('/foo', $this->noopMiddleware);
        $this->assertInstanceOf(Route::class, $route);
        $this->assertEquals('/foo', $route->getPath());
        $this->assertSame($this->noopMiddleware, $route->getMiddleware());
        $this->assertEquals([$method], $route->getAllowedMethods());
    }

    public function testCreatingHttpRouteMethodWithExistingPathButDifferentMethodCreatesNewRouteInstance()
    {
        $this->router->addRoute(Argument::type(Route::class))->shouldBeCalledTimes(2);
        $app = $this->getApp();
        $route = $app->route('/foo', $this->noopMiddleware, []);

        $middleware = function ($req, $res, $next) {
        };
        $test = $app->get('/foo', $middleware);
        $this->assertNotSame($route, $test);
        $this->assertSame($route->getPath(), $test->getPath());
        $this->assertSame(['GET'], $test->getAllowedMethods());
        $this->assertSame($middleware, $test->getMiddleware());
    }

    public function testCreatingHttpRouteWithExistingPathAndMethodRaisesException()
    {
        $this->router->addRoute(Argument::type(Route::class))->shouldBeCalledTimes(1);
        $app   = $this->getApp();
        $route = $app->get('/foo', $this->noopMiddleware);

        $this->setExpectedException('DomainException');
        $test = $app->get('/foo', function ($req, $res, $next) {
        });
    }

    public function testRouteMiddlewareIsNotPipedAtInstantation()
    {
        $app = $this->getApp();

        $r = new ReflectionProperty($app, 'pipeline');
        $r->setAccessible(true);
        $pipeline = $r->getValue($app);

        $this->assertCount(0, $pipeline);
    }

    public function testRouteMiddlewareIsPipedAtFirstCallToRoute()
    {
        $this->router->addRoute(Argument::type(Route::class))->shouldBeCalled();

        $app = $this->getApp();
        $app->route('/foo', 'bar');

        $r = new ReflectionProperty($app, 'pipeline');
        $r->setAccessible(true);
        $pipeline = $r->getValue($app);

        $this->assertCount(1, $pipeline);
        $route = $pipeline->dequeue();
        $this->assertInstanceOf('Zend\Stratigility\Route', $route);
        $test  = $route->handler;

        $routeMiddleware = [$app, 'routeMiddleware'];
        $this->assertSame($routeMiddleware, $test);
    }

    public function testRouteMiddlewareCanRouteArrayOfMiddlewareAsMiddlewarePipe()
    {
        $middleware = [
            function () {
            },
            'FooBar',
            [InvokableMiddleware::class, 'staticallyCallableMiddleware'],
            InvokableMiddleware::class,
        ];

        $request = new ServerRequest([], [], '/', 'GET');
        $routeResult = RouteResult::fromRouteMatch(__METHOD__, $middleware, []);
        $this->router->match($request)->willReturn($routeResult);

        $container = $this->mockContainerInterface();
        $this->injectServiceInContainer($container, 'FooBar', function () {
        });

        $app = new Application($this->router->reveal(), $container->reveal());
        $app->routeMiddleware($request, new Response(), function () {
        });
    }

    public function uncallableMiddleware()
    {
        return [
            ['foo'],
            [['foo']]
        ];
    }

    /**
     * @dataProvider uncallableMiddleware
     * @expectedException \Zend\Expressive\Exception\InvalidMiddlewareException
     */
    public function testThrowsExceptionWhenRoutingUncallableMiddleware($middleware)
    {
        $request = new ServerRequest([], [], '/', 'GET');
        $routeResult = RouteResult::fromRouteMatch(__METHOD__, $middleware, []);
        $this->router->match($request)->willReturn($routeResult);

        $this->getApp()->routeMiddleware($request, new Response(), function () {
        });
    }

    public function testCannotPipeRouteMiddlewareMoreThanOnce()
    {
        $app             = $this->getApp();
        $routeMiddleware = [$app, 'routeMiddleware'];

        $app->pipe($routeMiddleware);
        $app->pipe($routeMiddleware);

        $r = new ReflectionProperty($app, 'pipeline');
        $r->setAccessible(true);
        $pipeline = $r->getValue($app);

        $this->assertCount(1, $pipeline);
        $route = $pipeline->dequeue();
        $this->assertInstanceOf('Zend\Stratigility\Route', $route);
        $test  = $route->handler;

        $this->assertSame($routeMiddleware, $test);
    }

    public function testCanInjectFinalHandlerViaConstructor()
    {
        $finalHandler = function ($req, $res, $err = null) {
        };
        $app  = new Application($this->router->reveal(), null, $finalHandler);
        $test = $app->getFinalHandler();
        $this->assertSame($finalHandler, $test);
    }

    public function testFinalHandlerIsUsedAtInvocationIfNoOutArgumentIsSupplied()
    {
        $routeResult = RouteResult::fromRouteFailure();
        $this->router->match()->willReturn($routeResult);

        $finalResponse = $this->prophesize('Psr\Http\Message\ResponseInterface')->reveal();
        $finalHandler = function ($req, $res, $err = null) use ($finalResponse) {
            return $finalResponse;
        };

        $app = new Application($this->router->reveal(), null, $finalHandler);

        $request  = new Request([], [], 'http://example.com/');
        $response = $this->prophesize('Psr\Http\Message\ResponseInterface');

        $test = $app($request, $response->reveal());
        $this->assertSame($finalResponse, $test);
    }

    public function testComposesEmitterStackWithSapiEmitterByDefault()
    {
        $app   = $this->getApp();
        $stack = $app->getEmitter();
        $this->assertInstanceOf(EmitterStack::class, $stack);

        $this->assertCount(1, $stack);
        $test = $stack->pop();
        $this->assertInstanceOf(SapiEmitter::class, $test);
    }

    public function testAllowsInjectingEmitterAtInstantiation()
    {
        $emitter = $this->prophesize('Zend\Diactoros\Response\EmitterInterface');
        $app     = new Application(
            $this->router->reveal(),
            null,
            null,
            $emitter->reveal()
        );
        $test = $app->getEmitter();
        $this->assertSame($emitter->reveal(), $test);
    }

    public function testComposedEmitterIsCalledByRun()
    {
        $routeResult = RouteResult::fromRouteFailure();
        $this->router->match()->willReturn($routeResult);

        $finalResponse = $this->prophesize('Psr\Http\Message\ResponseInterface')->reveal();
        $finalHandler = function ($req, $res, $err = null) use ($finalResponse) {
            return $finalResponse;
        };

        $emitter = $this->prophesize('Zend\Diactoros\Response\EmitterInterface');
        $emitter->emit(
            Argument::type('Psr\Http\Message\ResponseInterface')
        )->shouldBeCalled();

        $app = new Application($this->router->reveal(), null, $finalHandler, $emitter->reveal());

        $request  = new Request([], [], 'http://example.com/');
        $response = $this->prophesize('Psr\Http\Message\ResponseInterface');

        $app->run($request, $response->reveal());
    }

    public function testCallingGetContainerWhenNoContainerComposedWillRaiseException()
    {
        $app = new Application($this->router->reveal());
        $this->setExpectedException('RuntimeException');
        $app->getContainer();
    }

    public function testUnsupportedMethodCall()
    {
        $app = $this->getApp();
        $this->setExpectedException('BadMethodCallException');
        $app->foo();
    }

    public function testCallMethodWithCountOfArgsNotEqualsWith2()
    {
        $app = $this->getApp();
        $this->setExpectedException('BadMethodCallException');
        $app->post('/foo');
    }

    /**
     * @group 64
     */
    public function testCanTriggerPipingOfRouteMiddleware()
    {
        $app = $this->getApp();
        $app->pipeRoutingMiddleware();

        $r = new ReflectionProperty($app, 'pipeline');
        $r->setAccessible(true);
        $pipeline = $r->getValue($app);

        $this->assertCount(1, $pipeline);

        $route = $pipeline->dequeue();
        $this->assertInstanceOf('Zend\Stratigility\Route', $route);
        $this->assertSame([$app, 'routeMiddleware'], $route->handler);
        $this->assertEquals('/', $route->path);
    }

    /**
     * @group 64
     */
    public function testInvocationWillPipeRoutingMiddlewareIfNotAlreadyPiped()
    {
        $request  = new Request([], [], 'http://example.com/');
        $response = $this->prophesize('Psr\Http\Message\ResponseInterface');

        $middleware = function ($req, $res, $next = null) {
            return $res;
        };

        $this->router->match($request)->willReturn(RouteResult::fromRouteMatch('foo', 'foo', []));

        $container = $this->mockContainerInterface();
        $this->injectServiceInContainer($container, 'foo', $middleware);

        $app = new Application($this->router->reveal(), $container->reveal());

        $pipeline = $this->prophesize('SplQueue');

        // Test that the route middleware is enqueued
        $pipeline->enqueue(Argument::that(function ($route) use ($app) {
            if (! $route instanceof StratigilityRoute) {
                return false;
            }

            if ($route->path !== '/') {
                return false;
            }

            return ($route->handler === [$app, 'routeMiddleware']);
        }))->shouldBeCalled();

        // Prevent dequeueing
        $pipeline->isEmpty()->willReturn(true);

        $r = new ReflectionProperty($app, 'pipeline');
        $r->setAccessible(true);
        $r->setValue($app, $pipeline->reveal());

        $app($request, $response->reveal(), $middleware);
    }

    /**
     * @group lazy-piping
     */
    public function testPipingAllowsPassingMiddlewareServiceNameAsSoleArgument()
    {
        $middleware = function ($req, $res, $next = null) {
            return 'invoked';
        };

        $container = $this->mockContainerInterface();
        $this->injectServiceInContainer($container, 'foo', $middleware);

        $app = new Application($this->router->reveal(), $container->reveal());
        $app->pipe('foo');

        $r = new ReflectionProperty($app, 'pipeline');
        $r->setAccessible(true);
        $pipeline = $r->getValue($app);

        $route = $pipeline->dequeue();
        $this->assertInstanceOf('Zend\Stratigility\Route', $route);
        $handler = $route->handler;

        $this->assertEquals('invoked', $handler('foo', 'bar'));
    }

    /**
     * @group lazy-piping
     */
    public function testAllowsPipingErrorMiddlewareUsingServiceNameAsSoleArgument()
    {
        $middleware = function ($error, $req, $res, $next) {
            return 'invoked';
        };

        $container = $this->mockContainerInterface();
        $this->injectServiceInContainer($container, 'foo', $middleware);

        $app = new Application($this->router->reveal(), $container->reveal());
        $app->pipeErrorHandler('foo');

        $r = new ReflectionProperty($app, 'pipeline');
        $r->setAccessible(true);
        $pipeline = $r->getValue($app);

        $route = $pipeline->dequeue();
        $this->assertInstanceOf('Zend\Stratigility\Route', $route);
        $handler = $route->handler;

        $this->assertEquals('invoked', $handler('foo', 'bar', 'baz', 'bat'));
    }

    /**
     * @group lazy-piping
     */
    public function testAllowsPipingMiddlewareAsServiceNameWithPath()
    {
        $middleware = function ($req, $res, $next = null) {
            return 'invoked';
        };

        $container = $this->mockContainerInterface();
        $this->injectServiceInContainer($container, 'foo', $middleware);

        $app = new Application($this->router->reveal(), $container->reveal());
        $app->pipe('/foo', 'foo');

        $r = new ReflectionProperty($app, 'pipeline');
        $r->setAccessible(true);
        $pipeline = $r->getValue($app);

        $route = $pipeline->dequeue();
        $this->assertInstanceOf('Zend\Stratigility\Route', $route);
        $handler = $route->handler;

        $this->assertEquals('invoked', $handler('foo', 'bar'));
    }

    /**
     * @group lazy-piping
     */
    public function testAllowsPipingErrorMiddlewareAsServiceNameWithPath()
    {
        $middleware = function ($error, $req, $res, $next) {
            return 'invoked';
        };

        $container = $this->mockContainerInterface();
        $this->injectServiceInContainer($container, 'foo', $middleware);

        $app = new Application($this->router->reveal(), $container->reveal());
        $app->pipeErrorHandler('/foo', 'foo');

        $r = new ReflectionProperty($app, 'pipeline');
        $r->setAccessible(true);
        $pipeline = $r->getValue($app);

        $route = $pipeline->dequeue();
        $this->assertInstanceOf('Zend\Stratigility\Route', $route);
        $handler = $route->handler;

        $this->assertEquals('invoked', $handler('foo', 'bar', 'baz', 'bat'));
    }
}
