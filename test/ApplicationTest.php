<?php
namespace ZendTest\Expressive;

use PHPUnit_Framework_TestCase as TestCase;
use Prophecy\Argument;
use ReflectionProperty;
use Zend\Diactoros\Response;
use Zend\Diactoros\ServerRequest as Request;
use Zend\Expressive\Application;
use Zend\Expressive\Route;

class ApplicationTest extends TestCase
{
    public function setUp()
    {
        $this->noopMiddleware = function ($req, $res, $next) {
        };

        $this->dispatcher = $this->prophesize('Zend\Stratigility\Dispatch\Dispatcher');
    }

    public function getApp()
    {
        return new Application($this->dispatcher->reveal());
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

    public function testApplicationCreatesDispatcherIfNoneProvidedAtInstantiation()
    {
        $this->markTestIncomplete();
    }

    public function testApplicationIsAMiddlewarePipe()
    {
        $app = $this->getApp();
        $this->assertInstanceOf('Zend\Stratigility\MiddlewarePipe', $app);
    }

    public function testRouteMethodReturnsRouteInstance()
    {
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
        $route = $this->getApp()->route('/foo', $this->noopMiddleware, [$method]);
        $this->assertInstanceOf(Route::class, $route);
        $this->assertEquals('/foo', $route->getPath());
        $this->assertSame($this->noopMiddleware, $route->getMiddleware());
        $this->assertTrue($route->allowsMethod($method));
        $this->assertSame([$method], $route->getAllowedMethods());
    }

    public function testCanCallRouteWithMultipleHttpMethods()
    {
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
        $app   = $this->getApp();
        $test  = $app->route($route);
        $this->assertSame($route, $test);
    }

    public function testCallingRouteWithExistingPathAndOmittingMethodsArgumentRaisesException()
    {
        $app = $this->getApp();
        $route = $app->route('/foo', $this->noopMiddleware);
        $this->setExpectedException('DomainException');
        $app->route('/foo', function ($req, $res, $next) {
        });
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
        $app = $this->getApp();
        $route = $app->route('/foo', $this->noopMiddleware);
        $route->setAllowedMethods([]);

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
        $app   = $this->getApp();
        $route = $app->get('/foo', $this->noopMiddleware);

        $this->setExpectedException('DomainException');
        $test = $app->get('/foo', function ($req, $res, $next) {
        });
    }

    public function testDispatcherIsNotPipedPriorToInvocation()
    {
        $app = $this->getApp();
        $r = new ReflectionProperty($app, 'dispatcher');
        $r->setAccessible(true);
        $dispatcher = $r->getValue($app);

        $r = new ReflectionProperty($app, 'pipeline');
        $r->setAccessible(true);
        $pipeline = $r->getValue($app);
        $middleware = iterator_to_array($pipeline);
        $this->assertNotContains($dispatcher, $middleware);
        return $app;
    }

    /**
     * @depends testDispatcherIsNotPipedPriorToInvocation
     */
    public function testDispatcherIsPipedAfterFirstRouteCreated($app)
    {
        $route = $app->get('/foo', $this->noopMiddleware);

        $r = new ReflectionProperty($app, 'dispatcher');
        $r->setAccessible(true);
        $dispatcher = $r->getValue($app);

        $r = new ReflectionProperty($app, 'pipeline');
        $r->setAccessible(true);
        $pipeline = $r->getValue($app);

        $this->assertCount(1, $pipeline);

        $test = $pipeline->dequeue();
        $this->assertInstanceOf('Zend\Stratigility\Route', $test);
        $this->assertSame($dispatcher, $test->handler);
    }

    public function testInvokeInjectsRoutesIntoRouterComposedByDispatcher()
    {
        $get  = new Route('/foo', $this->noopMiddleware);
        $get->setAllowedMethods(['GET']);
        $post = new Route('/foo', clone $this->noopMiddleware);
        $post->setAllowedMethods(['POST']);

        $expected = [ $get, $post ];

        $request  = new Request([], [], 'http://example.com/', 'GET', 'php://temp', []);
        $response = new Response();

        $router = $this->prophesize('Zend\Expressive\RouterInterface');
        $router->injectRoutes($expected)->shouldBeCalled();

        $this->dispatcher->getRouter()->willReturn($router->reveal());
        $this->dispatcher->__invoke(
            Argument::type('Psr\Http\Message\ServerRequestInterface'),
            Argument::type('Psr\Http\Message\ResponseInterface'),
            Argument::type('callable')
        )->willReturn($response);

        $app = $this->getApp();
        $app->route($get);
        $app->route($post);

        // invoke and test
        // Will need mocks for request, response
        // Will need a final handler
        $finalHandler = function ($req, $res) {
            return $res;
        };
        $app($request, $response, $finalHandler);
    }
}
