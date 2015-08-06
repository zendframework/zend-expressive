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
use Prophecy\Argument;
use ReflectionProperty;
use Zend\Diactoros\ServerRequest as Request;
use Zend\Expressive\Application;
use Zend\Expressive\Router\Route;
use Zend\Expressive\Router\RouteResult;

class ApplicationTest extends TestCase
{
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
        $this->setExpectedException('Zend\Expressive\Exception\InvalidArgumentException');
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

    public function testComposesStratigilityFinalHandlerByDefault()
    {
        $app   = $this->getApp();
        $final = $app->getFinalHandler();
        $this->assertInstanceOf('Zend\Stratigility\FinalHandler', $final);
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

    public function testComposesSapiEmitterByDefault()
    {
        $app     = $this->getApp();
        $emitter = $app->getEmitter();
        $this->assertInstanceOf('Zend\Diactoros\Response\SapiEmitter', $emitter);
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
        $app = $this->getApp();
        $this->setExpectedException('RuntimeException');
        $app->getContainer();
    }
}
