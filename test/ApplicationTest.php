<?php
/**
 * @see       https://github.com/zendframework/zend-expressive for the canonical source repository
 * @copyright Copyright (c) 2015-2017 Zend Technologies USA Inc. (http://www.zend.com)
 * @license   https://github.com/zendframework/zend-expressive/blob/master/LICENSE.md New BSD License
 */

namespace ZendTest\Expressive;

use BadMethodCallException;
use DomainException;
use Fig\Http\Message\StatusCodeInterface as StatusCode;
use Interop\Http\ServerMiddleware\DelegateInterface;
use InvalidArgumentException;
use Mockery;
use PHPUnit\Framework\TestCase;
use Prophecy\Argument;
use Prophecy\Prophecy\ObjectProphecy;
use Psr\Container\ContainerInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use ReflectionProperty;
use RuntimeException;
use UnexpectedValueException;
use Zend\Diactoros\Response\EmitterInterface;
use Zend\Diactoros\Response\SapiEmitter;
use Zend\Diactoros\ServerRequest;
use Zend\Diactoros\ServerRequest as Request;
use Zend\Diactoros\ServerRequestFactory;
use Zend\Expressive\Application;
use Zend\Expressive\Delegate;
use Zend\Expressive\Emitter\EmitterStack;
use Zend\Expressive\Exception;
use Zend\Expressive\Exception\InvalidMiddlewareException;
use Zend\Expressive\Middleware;
use Zend\Expressive\Router\Exception as RouterException;
use Zend\Expressive\Router\Route;
use Zend\Expressive\Router\RouteResult;
use Zend\Expressive\Router\RouterInterface;
use Zend\Expressive\Template\TemplateRendererInterface;
use Zend\Stratigility\MiddlewarePipe;
use Zend\Stratigility\Route as StratigilityRoute;

/**
 * @covers Zend\Expressive\Application
 */
class ApplicationTest extends TestCase
{
    use ContainerTrait;
    use RouteResultTrait;

    /** @var TestAsset\InteropMiddleware */
    private $noopMiddleware;

    /** @var RouterInterface|ObjectProphecy */
    private $router;

    public function setUp()
    {
        $this->noopMiddleware = new TestAsset\InteropMiddleware();
        $this->router = $this->prophesize(RouterInterface::class);
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
        $this->assertInstanceOf(Application::class, $app);
    }

    public function testApplicationIsAMiddlewarePipe()
    {
        $app = $this->getApp();
        $this->assertInstanceOf(MiddlewarePipe::class, $app);
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
     *
     * @param string $method
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
        $this->router->addRoute(Argument::type(Route::class))->shouldBeCalledTimes(2);
        $app = $this->getApp();
        $app->route('/foo', $this->noopMiddleware);
        $app->route('/bar', $this->noopMiddleware);
        $this->expectException(DomainException::class);
        $app->route('/foo', function ($req, $res, $next) {
        });
    }

    public function testCallingRouteWithOnlyAPathRaisesAnException()
    {
        $app = $this->getApp();
        $this->expectException(Exception\InvalidArgumentException::class);
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
     *
     * @param mixed $path
     */
    public function testCallingRouteWithAnInvalidPathTypeRaisesAnException($path)
    {
        $app = $this->getApp();
        $this->expectException(RouterException\InvalidArgumentException::class);
        $app->route($path, new TestAsset\InteropMiddleware());
    }

    /**
     * @dataProvider commonHttpMethods
     *
     * @param mixed $method
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

        $middleware = new TestAsset\InteropMiddleware();
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
        $app->get('/foo', $this->noopMiddleware);

        $this->expectException(DomainException::class);
        $app->get('/foo', function ($req, $res, $next) {
        });
    }

    public function testRouteAndDispatchMiddlewareAreNotPipedAtInstantation()
    {
        $app = $this->getApp();

        $r = new ReflectionProperty($app, 'pipeline');
        $r->setAccessible(true);
        $pipeline = $r->getValue($app);

        $this->assertCount(0, $pipeline);
    }

    public function testCannotPipeRouteMiddlewareMoreThanOnce()
    {
        $app             = $this->getApp();
        $routeMiddleware = Application::ROUTING_MIDDLEWARE;

        $app->pipe($routeMiddleware);
        $app->pipe($routeMiddleware);

        $r = new ReflectionProperty($app, 'pipeline');
        $r->setAccessible(true);
        $pipeline = $r->getValue($app);

        $this->assertCount(1, $pipeline);
        $route = $pipeline->dequeue();
        $this->assertInstanceOf(StratigilityRoute::class, $route);
        $test  = $route->handler;

        $this->assertInstanceOf(Middleware\RouteMiddleware::class, $test);
    }

    public function testCannotPipeDispatchMiddlewareMoreThanOnce()
    {
        $app                = $this->getApp();
        $dispatchMiddleware = Application::DISPATCH_MIDDLEWARE;

        $app->pipe($dispatchMiddleware);
        $app->pipe($dispatchMiddleware);

        $r = new ReflectionProperty($app, 'pipeline');
        $r->setAccessible(true);
        $pipeline = $r->getValue($app);

        $this->assertCount(1, $pipeline);
        $route = $pipeline->dequeue();
        $this->assertInstanceOf(StratigilityRoute::class, $route);
        $test  = $route->handler;

        $this->assertInstanceOf(Middleware\DispatchMiddleware::class, $test);
    }

    public function testCanInjectDefaultDelegateViaConstructor()
    {
        $defaultDelegate = $this->prophesize(DelegateInterface::class)->reveal();
        $app  = new Application($this->router->reveal(), null, $defaultDelegate);
        $test = $app->getDefaultDelegate();
        $this->assertSame($defaultDelegate, $test);
    }

    public function testDefaultDelegateIsUsedAtInvocationIfNoOutArgumentIsSupplied()
    {
        $routeResult = RouteResult::fromRouteFailure();
        $this->router->match()->willReturn($routeResult);

        $finalResponse = $this->prophesize(ResponseInterface::class)->reveal();
        $defaultDelegate = $this->prophesize(DelegateInterface::class);
        $defaultDelegate->process(Argument::type(ServerRequestInterface::class))
            ->willReturn($finalResponse);

        $emitter = $this->prophesize(EmitterInterface::class);
        $emitter->emit($finalResponse)->shouldBeCalled();

        $app = new Application($this->router->reveal(), null, $defaultDelegate->reveal(), $emitter->reveal());

        $request  = new Request([], [], 'http://example.com/');

        $app->run($request);
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
        $emitter = $this->prophesize(EmitterInterface::class);
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

        $finalResponse = $this->prophesize(ResponseInterface::class)->reveal();
        $defaultDelegate = $this->prophesize(DelegateInterface::class);
        $defaultDelegate->process(Argument::type(ServerRequestInterface::class))
            ->willReturn($finalResponse);

        $emitter = $this->prophesize(EmitterInterface::class);
        $emitter->emit(
            Argument::type(ResponseInterface::class)
        )->shouldBeCalled();

        $app = new Application($this->router->reveal(), null, $defaultDelegate->reveal(), $emitter->reveal());

        $request  = new Request([], [], 'http://example.com/');
        $response = $this->prophesize(ResponseInterface::class);
        $response->withStatus(StatusCode::STATUS_NOT_FOUND)->will([$response, 'reveal']);

        $app->run($request, $response->reveal());
    }

    public function testCallingGetContainerReturnsComposedInstance()
    {
        $container = $this->prophesize(ContainerInterface::class);
        $app = new Application($this->router->reveal(), $container->reveal());
        $this->assertSame($container->reveal(), $app->getContainer());
    }

    public function testCallingGetContainerWhenNoContainerComposedWillRaiseException()
    {
        $app = new Application($this->router->reveal());
        $this->expectException(RuntimeException::class);
        $app->getContainer();
    }

    public function testUnsupportedMethodCall()
    {
        $app = $this->getApp();
        $this->expectException(BadMethodCallException::class);
        $app->foo();
    }

    public function testCallMethodWithCountOfArgsNotEqualsWith2()
    {
        $app = $this->getApp();
        $this->expectException(BadMethodCallException::class);
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
        $this->assertInstanceOf(StratigilityRoute::class, $route);
        $this->assertInstanceOf(Middleware\RouteMiddleware::class, $route->handler);
        $this->assertEquals('/', $route->path);
    }

    public function testCanTriggerPipingOfDispatchMiddleware()
    {
        $app = $this->getApp();
        $app->pipeDispatchMiddleware();

        $r = new ReflectionProperty($app, 'pipeline');
        $r->setAccessible(true);
        $pipeline = $r->getValue($app);

        $this->assertCount(1, $pipeline);

        $route = $pipeline->dequeue();
        $this->assertInstanceOf(StratigilityRoute::class, $route);
        $this->assertInstanceOf(Middleware\DispatchMiddleware::class, $route->handler);
        $this->assertEquals('/', $route->path);
    }

    /**
     * @group lazy-piping
     */
    public function testPipingAllowsPassingMiddlewareServiceNameAsSoleArgument()
    {
        $middleware = new TestAsset\InteropMiddleware();

        $container = $this->mockContainerInterface();
        $this->injectServiceInContainer($container, 'foo', $middleware);

        $app = new Application($this->router->reveal(), $container->reveal());
        $app->pipe('foo');

        $r = new ReflectionProperty($app, 'pipeline');
        $r->setAccessible(true);
        $pipeline = $r->getValue($app);

        $route = $pipeline->dequeue();
        $this->assertInstanceOf(StratigilityRoute::class, $route);
        $handler = $route->handler;
        $this->assertInstanceOf(Middleware\LazyLoadingMiddleware::class, $handler);
        $this->assertAttributeEquals('foo', 'middlewareName', $handler);
    }

    /**
     * @group lazy-piping
     */
    public function testAllowsPipingMiddlewareAsServiceNameWithPath()
    {
        $middleware = new TestAsset\InteropMiddleware();

        $container = $this->mockContainerInterface();
        $this->injectServiceInContainer($container, 'foo', $middleware);

        $app = new Application($this->router->reveal(), $container->reveal());
        $app->pipe('/foo', 'foo');

        $r = new ReflectionProperty($app, 'pipeline');
        $r->setAccessible(true);
        $pipeline = $r->getValue($app);

        $route = $pipeline->dequeue();
        $this->assertInstanceOf(StratigilityRoute::class, $route);
        $handler = $route->handler;
        $this->assertInstanceOf(Middleware\LazyLoadingMiddleware::class, $handler);
        $this->assertAttributeEquals('foo', 'middlewareName', $handler);
    }

    /**
     * @group lazy-piping
     */
    public function testPipingNotInvokableMiddlewareRaisesExceptionWhenInvokingRoute()
    {
        $middleware = 'not callable';

        $container = $this->mockContainerInterface();
        $this->injectServiceInContainer($container, 'foo', $middleware);

        $app = new Application($this->router->reveal(), $container->reveal());
        $app->pipe('/foo', 'foo');

        $r = new ReflectionProperty($app, 'pipeline');
        $r->setAccessible(true);
        $pipeline = $r->getValue($app);

        $route = $pipeline->dequeue();
        $this->assertInstanceOf(StratigilityRoute::class, $route);
        $handler = $route->handler;

        $request = $this->prophesize(ServerRequest::class)->reveal();
        $delegate = $this->prophesize(DelegateInterface::class)->reveal();

        $this->expectException(InvalidMiddlewareException::class);
        $handler->process($request, $delegate);
    }

    public function invalidRequestExceptions()
    {
        return [
            'invalid file'             => [
                InvalidArgumentException::class,
                'Invalid value in files specification',
            ],
            'invalid protocol version' => [
                UnexpectedValueException::class,
                'Unrecognized protocol version (foo-bar)',
            ],
        ];
    }

    /**
     * @runInSeparateProcess
     * @preserveGlobalState disabled
     *
     * @dataProvider invalidRequestExceptions
     *
     * @param string $expectedException
     * @param string $message
     */
    public function testRunReturnsResponseWithBadRequestStatusWhenServerRequestFactoryRaisesException(
        $expectedException,
        $message
    ) {
        // try/catch is necessary in the case that the test fails.
        // Assertion exceptions raised inside prophecy expectations normally
        // are fine, but in the context of runInSeparateProcess, these
        // lead to closure serialization errors. try/catch allows us to
        // catch those and provide failure assertions.
        try {
            Mockery::mock('alias:' . ServerRequestFactory::class)
                ->shouldReceive('fromGlobals')
                ->withNoArgs()
                ->andThrow($expectedException, $message)
                ->once()
                ->getMock();

            $emitter = $this->prophesize(EmitterInterface::class);
            $emitter->emit(Argument::that(function ($response) {
                $this->assertInstanceOf(ResponseInterface::class, $response, 'Emitter did not receive a response');
                $this->assertEquals(StatusCode::STATUS_BAD_REQUEST, $response->getStatusCode());
                return true;
            }))->shouldBeCalled();

            $app = new Application($this->router->reveal(), null, null, $emitter->reveal());

            $app->run();
        } catch (\Throwable $e) {
            $this->fail($e->getMessage());
        } catch (\Exception $e) {
            $this->fail($e->getMessage());
        }
    }

    public function testRetrieveRegisteredRoutes()
    {
        $route = new Route('/foo', $this->noopMiddleware);
        $this->router->addRoute($route)->shouldBeCalled();
        $app = $this->getApp();
        $test = $app->route($route);
        $this->assertSame($route, $test);
        $routes = $app->getRoutes();
        $this->assertCount(1, $routes);
        $this->assertInstanceOf(Route::class, $routes[0]);
    }

    /**
     * This test verifies that if the ErrorResponseGenerator service is available,
     * it will be used to generate a response related to exceptions raised when
     * creating the server request.
     *
     * @runInSeparateProcess
     * @preserveGlobalState disabled
     *
     * @dataProvider invalidRequestExceptions
     *
     * @param string $expectedException
     * @param string $message
     */
    public function testRunReturnsResponseGeneratedByErrorResponseGeneratorWhenServerRequestFactoryRaisesException(
        $expectedException,
        $message
    ) {
        // try/catch is necessary in the case that the test fails.
        // Assertion exceptions raised inside prophecy expectations normally
        // are fine, but in the context of runInSeparateProcess, these
        // lead to closure serialization errors. try/catch allows us to
        // catch those and provide failure assertions.
        try {
            $generator = $this->prophesize(Middleware\ErrorResponseGenerator::class);
            $generator
                ->__invoke(
                    Argument::type($expectedException),
                    Argument::type(ServerRequestInterface::class),
                    Argument::type(ResponseInterface::class)
                )->will(function ($args) {
                    return $args[2];
                });

            $container = $this->mockContainerInterface();
            $this->injectServiceInContainer($container, Middleware\ErrorResponseGenerator::class, $generator);

            Mockery::mock('alias:' . ServerRequestFactory::class)
                ->shouldReceive('fromGlobals')
                ->withNoArgs()
                ->andThrow($expectedException, $message)
                ->once()
                ->getMock();

            $expectedResponse = $this->prophesize(ResponseInterface::class)->reveal();

            $emitter = $this->prophesize(EmitterInterface::class);
            $emitter->emit(Argument::that(function ($response) use ($expectedResponse) {
                $this->assertSame($expectedResponse, $response, 'Unexpected response provided to emitter');
                return true;
            }))->shouldBeCalled();

            $app = new Application($this->router->reveal(), $container->reveal(), null, $emitter->reveal());
            $app->setResponsePrototype($expectedResponse);

            $app->run();
        } catch (\Throwable $e) {
            $this->fail(sprintf("(%d) %s:\n%s", $e->getCode(), $e->getMessage(), $e->getTraceAsString()));
        } catch (\Exception $e) {
            $this->fail(sprintf("(%d) %s:\n%s", $e->getCode(), $e->getMessage(), $e->getTraceAsString()));
        }
    }

    public function testGetDefaultDelegateWillPullFromContainerIfServiceRegistered()
    {
        $delegate = $this->prophesize(DelegateInterface::class)->reveal();
        $container = $this->mockContainerInterface();
        $this->injectServiceInContainer($container, 'Zend\Expressive\Delegate\DefaultDelegate', $delegate);

        $app = new Application($this->router->reveal(), $container->reveal());

        $test = $app->getDefaultDelegate();

        $this->assertSame($delegate, $test);
    }

    public function testWillCreateAndConsumeNotFoundDelegateFactoryToCreateDelegateIfNoDelegateInContainer()
    {
        $container = $this->mockContainerInterface();
        $container->has('Zend\Expressive\Delegate\DefaultDelegate')->willReturn(false);
        $container->has(TemplateRendererInterface::class)->willReturn(false);
        $app = new Application($this->router->reveal(), $container->reveal());

        $delegate = $app->getDefaultDelegate();

        $this->assertInstanceOf(Delegate\NotFoundDelegate::class, $delegate);

        $r = new ReflectionProperty($app, 'responsePrototype');
        $r->setAccessible(true);
        $appResponsePrototype = $r->getValue($app);

        $this->assertAttributeNotSame($appResponsePrototype, 'responsePrototype', $delegate);
        $this->assertAttributeEmpty('renderer', $delegate);
    }

    public function testWillUseConfiguredTemplateRendererWhenCreatingDelegateFromNotFoundDelegateFactory()
    {
        $container = $this->mockContainerInterface();
        $container->has('Zend\Expressive\Delegate\DefaultDelegate')->willReturn(false);

        $renderer = $this->prophesize(TemplateRendererInterface::class)->reveal();
        $this->injectServiceInContainer($container, TemplateRendererInterface::class, $renderer);

        $app = new Application($this->router->reveal(), $container->reveal());

        $delegate = $app->getDefaultDelegate();

        $this->assertInstanceOf(Delegate\NotFoundDelegate::class, $delegate);

        $r = new ReflectionProperty($app, 'responsePrototype');
        $r->setAccessible(true);
        $appResponsePrototype = $r->getValue($app);

        $this->assertAttributeNotSame($appResponsePrototype, 'responsePrototype', $delegate);
        $this->assertAttributeSame($renderer, 'renderer', $delegate);
    }
}
