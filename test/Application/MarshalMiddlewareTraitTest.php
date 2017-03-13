<?php
/**
 * @see       https://github.com/zendframework/zend-expressive for the canonical source repository
 * @copyright Copyright (c) 2017 Zend Technologies USA Inc. (http://www.zend.com)
 * @license   https://github.com/zendframework/zend-expressive/blob/master/LICENSE.md New BSD License
 */

namespace ZendTest\Expressive\Application;

use Interop\Http\ServerMiddleware\DelegateInterface;
use Interop\Http\ServerMiddleware\MiddlewareInterface as ServerMiddlewareInterface;
use PHPUnit\Framework\TestCase;
use Prophecy\Prophecy\ObjectProphecy;
use Psr\Container\ContainerInterface;
use Psr\Http\Message\ResponseInterface;
use ReflectionMethod;
use ReflectionProperty;
use stdClass;
use Zend\Expressive\Application;
use Zend\Expressive\Exception\InvalidMiddlewareException;
use Zend\Expressive\Middleware;
use Zend\Expressive\Router\RouterInterface;
use Zend\Stratigility\Middleware\CallableInteropMiddlewareWrapper;
use Zend\Stratigility\Middleware\CallableMiddlewareWrapper;
use Zend\Stratigility\MiddlewarePipe;

class MarshalMiddlewareTraitTest extends TestCase
{
    /** @var ContainerInterface|ObjectProphecy */
    private $container;

    /** @var RouterInterface|ObjectProphecy */
    private $router;

    /** @var ResponseInterface|ObjectProphecy */
    private $responsePrototype;

    /** @var Application */
    private $application;

    public function setUp()
    {
        $this->container = $this->prophesize(ContainerInterface::class);
        $this->router = $this->prophesize(RouterInterface::class);
        $this->responsePrototype = $this->prophesize(ResponseInterface::class);
        $this->application = new Application($this->router->reveal());
    }

    public function prepareMiddleware($middleware)
    {
        $r = new ReflectionMethod($this->application, 'prepareMiddleware');
        $r->setAccessible(true);
        return $r->invoke(
            $this->application,
            $middleware,
            $this->router->reveal(),
            $this->responsePrototype->reveal(),
            $this->container->reveal()
        );
    }

    public function prepareMiddlewareWithoutContainer($middleware)
    {
        $r = new ReflectionMethod($this->application, 'prepareMiddleware');
        $r->setAccessible(true);
        return $r->invoke(
            $this->application,
            $middleware,
            $this->router->reveal(),
            $this->responsePrototype->reveal()
        );
    }

    public function testPreparingRoutingMiddlewareReturnsRoutingMiddleware()
    {
        $middleware = $this->prepareMiddleware(Application::ROUTING_MIDDLEWARE);
        $this->assertInstanceOf(Middleware\RouteMiddleware::class, $middleware);
        $this->assertAttributeSame($this->router->reveal(), 'router', $middleware);
        $this->assertAttributeSame($this->responsePrototype->reveal(), 'responsePrototype', $middleware);
    }

    public function testPreparingRoutingMiddlewareWithoutContainerReturnsRoutingMiddleware()
    {
        $middleware = $this->prepareMiddlewareWithoutContainer(Application::ROUTING_MIDDLEWARE);
        $this->assertInstanceOf(Middleware\RouteMiddleware::class, $middleware);
        $this->assertAttributeSame($this->router->reveal(), 'router', $middleware);
        $this->assertAttributeSame($this->responsePrototype->reveal(), 'responsePrototype', $middleware);
    }

    public function testPreparingDispatchMiddlewareReturnsDispatchMiddleware()
    {
        $middleware = $this->prepareMiddleware(Application::DISPATCH_MIDDLEWARE);
        $this->assertInstanceOf(Middleware\DispatchMiddleware::class, $middleware);
        $this->assertAttributeSame($this->container->reveal(), 'container', $middleware);
        $this->assertAttributeSame($this->router->reveal(), 'router', $middleware);
        $this->assertAttributeSame($this->responsePrototype->reveal(), 'responsePrototype', $middleware);
    }

    public function testPreparingDispatchMiddlewareWithoutContainerReturnsDispatchMiddleware()
    {
        $middleware = $this->prepareMiddlewareWithoutContainer(Application::DISPATCH_MIDDLEWARE);
        $this->assertInstanceOf(Middleware\DispatchMiddleware::class, $middleware);
        $this->assertAttributeEmpty('container', $middleware);
        $this->assertAttributeSame($this->router->reveal(), 'router', $middleware);
        $this->assertAttributeSame($this->responsePrototype->reveal(), 'responsePrototype', $middleware);
    }

    public function testPreparingInteropMiddlewareReturnsMiddlewareVerbatim()
    {
        $base = $this->prophesize(ServerMiddlewareInterface::class)->reveal();
        $middleware = $this->prepareMiddleware($base);
        $this->assertSame($base, $middleware);
    }

    public function testPreparingInteropMiddlewareWithoutContainerReturnsMiddlewareVerbatim()
    {
        $base = $this->prophesize(ServerMiddlewareInterface::class)->reveal();
        $middleware = $this->prepareMiddlewareWithoutContainer($base);
        $this->assertSame($base, $middleware);
    }

    public function testPreparingDuckTypedInteropMiddlewareReturnsDecoratedInteropMiddleware()
    {
        $base = function ($request, DelegateInterface $delegate) {
        };
        $middleware = $this->prepareMiddleware($base);
        $this->assertInstanceOf(CallableInteropMiddlewareWrapper::class, $middleware);
        $this->assertAttributeSame($base, 'middleware', $middleware);
    }

    public function testPreparingDuckTypedInteropMiddlewareWithoutContainerReturnsDecoratedInteropMiddleware()
    {
        $base = function ($request, DelegateInterface $delegate) {
        };
        $middleware = $this->prepareMiddlewareWithoutContainer($base);
        $this->assertInstanceOf(CallableInteropMiddlewareWrapper::class, $middleware);
        $this->assertAttributeSame($base, 'middleware', $middleware);
    }

    public function testPreparingCallableMiddlewareReturnsDecoratedMiddleware()
    {
        $base = function ($request, $response, callable $next) {
        };
        $middleware = $this->prepareMiddleware($base);
        $this->assertInstanceOf(CallableMiddlewareWrapper::class, $middleware);
        $this->assertAttributeSame($base, 'middleware', $middleware);
        $this->assertAttributeSame($this->responsePrototype->reveal(), 'responsePrototype', $middleware);
    }

    public function testPreparingCallableMiddlewareWithoutContainerReturnsDecoratedMiddleware()
    {
        $base = function ($request, $response, callable $next) {
        };
        $middleware = $this->prepareMiddlewareWithoutContainer($base);
        $this->assertInstanceOf(CallableMiddlewareWrapper::class, $middleware);
        $this->assertAttributeSame($base, 'middleware', $middleware);
        $this->assertAttributeSame($this->responsePrototype->reveal(), 'responsePrototype', $middleware);
    }

    public function testPreparingArrayOfMiddlewareReturnsMiddlewarePipe()
    {
        $first  = $this->prophesize(ServerMiddlewareInterface::class)->reveal();
        $second = function ($request, DelegateInterface $delegate) {
        };
        $third  = function ($request, $response, callable $next) {
        };
        $fourth = 'fourth';
        $fifth  = TestAsset\CallableMiddleware::class;
        $sixth  = TestAsset\CallableInteropMiddleware::class;

        $this->container->has('fourth')->willReturn(true);
        $this->container->has(TestAsset\CallableMiddleware::class)->willReturn(false);
        $this->container->has(TestAsset\CallableInteropMiddleware::class)->willReturn(false);

        $base = [
            $first,
            $second,
            $third,
            $fourth,
            $fifth,
            $sixth,
        ];

        $middleware = $this->prepareMiddleware($base);
        $this->assertInstanceOf(MiddlewarePipe::class, $middleware);

        $r = new ReflectionProperty($middleware, 'pipeline');
        $r->setAccessible(true);
        $pipeline = $r->getValue($middleware);

        $this->assertCount(6, $pipeline);
    }

    public function testPreparingArrayOfMiddlewareWithoutContainerReturnsMiddlewarePipe()
    {
        $first  = $this->prophesize(ServerMiddlewareInterface::class)->reveal();
        $second = function ($request, DelegateInterface $delegate) {
        };
        $third  = function ($request, $response, callable $next) {
        };
        $fifth  = TestAsset\CallableMiddleware::class;
        $sixth  = TestAsset\CallableInteropMiddleware::class;

        $base = [
            $first,
            $second,
            $third,
            $fifth,
            $sixth,
        ];

        $middleware = $this->prepareMiddleware($base);
        $this->assertInstanceOf(MiddlewarePipe::class, $middleware);

        $r = new ReflectionProperty($middleware, 'pipeline');
        $r->setAccessible(true);
        $pipeline = $r->getValue($middleware);

        $this->assertCount(5, $pipeline);
    }

    public function testPreparingArrayOfMiddlewareRaisesExceptionWhenContainerMissingAndServiceInvalid()
    {
        $first  = $this->prophesize(ServerMiddlewareInterface::class)->reveal();
        $second = 'second-middleware';
        $third  = 'third-middleware';

        // No container is passed to the method, so this will not matter
        $this->container->has('second-middleware')->willReturn(true);
        $this->container->has('third-middleware')->willReturn(true);

        $base = [$first, $second, $third];

        $this->expectException(InvalidMiddlewareException::class);
        $this->expectExceptionMessage('second-middleware');
        $this->prepareMiddlewareWithoutContainer($base);
    }

    public function testPreparingServiceBasedMiddlewareReturnsLazyLoadingMiddleware()
    {
        $middlewareName = 'middleware';
        $this->container->has($middlewareName)->willReturn(true);

        $middleware = $this->prepareMiddleware($middlewareName);
        $this->assertInstanceOf(Middleware\LazyLoadingMiddleware::class, $middleware);

        $this->assertAttributeSame($this->container->reveal(), 'container', $middleware);
        $this->assertAttributeSame($this->responsePrototype->reveal(), 'responsePrototype', $middleware);
        $this->assertAttributeEquals($middlewareName, 'middlewareName', $middleware);
    }

    public function testPreparingInvokableInteropMiddlewareReturnsDecoratedInteropMiddleware()
    {
        $base = TestAsset\CallableInteropMiddleware::class;
        $this->container->has(TestAsset\CallableInteropMiddleware::class)->willReturn(false);

        $middleware = $this->prepareMiddleware($base);

        $this->assertInstanceOf(CallableInteropMiddlewareWrapper::class, $middleware);
        $this->assertAttributeInstanceOf(TestAsset\CallableInteropMiddleware::class, 'middleware', $middleware);
    }

    public function testPreparingInvokableCallableMiddlewareReturnsDecoratedMiddleware()
    {
        $base = TestAsset\CallableMiddleware::class;
        $this->container->has(TestAsset\CallableMiddleware::class)->willReturn(false);
        $middleware = $this->prepareMiddleware($base);
        $this->assertInstanceOf(CallableMiddlewareWrapper::class, $middleware);
        $this->assertAttributeInstanceOf(TestAsset\CallableMiddleware::class, 'middleware', $middleware);
    }

    public function testPreparingInvalidInvokableMiddlewareRaisesException()
    {
        $base = stdClass::class;
        $this->container->has(stdClass::class)->willReturn(false);

        $this->expectException(InvalidMiddlewareException::class);
        $this->expectExceptionMessage('invalid; neither invokable');
        $this->prepareMiddleware($base);
    }

    public function testPreparingInvokableInteropMiddlewareThatIsRegisteredInContainerReturnsLazyMiddleware()
    {
        $base = TestAsset\CallableMiddleware::class;
        $this->container->has(TestAsset\CallableMiddleware::class)->willReturn(true);
        $middleware = $this->prepareMiddleware($base);

        $this->assertInstanceOf(Middleware\LazyLoadingMiddleware::class, $middleware);
        $this->assertAttributeEquals($base, 'middlewareName', $middleware);
    }

    public function invalidMiddlewareTypes()
    {
        $defaultExpectedMessage = 'Unable to resolve middleware';
        return [
            'null'                    => [null, $defaultExpectedMessage],
            'true'                    => [true, $defaultExpectedMessage],
            'false'                   => [false, $defaultExpectedMessage],
            'zero'                    => [0, $defaultExpectedMessage],
            'int'                     => [1, $defaultExpectedMessage],
            'zero-float'              => [0.0, $defaultExpectedMessage],
            'float'                   => [1.1, $defaultExpectedMessage],
            'non-class-name-string'   => ['not-a-class-name', 'not a valid class or service name'],
            'non-callable-class-name' => [stdClass::class, 'invalid; neither invokable'],
            'non-callable-object'     => [new stdClass(), $defaultExpectedMessage],
        ];
    }

    /**
     * @dataProvider invalidMiddlewareTypes
     *
     * @param mixed $invalid
     * @param string $expectedMessage
     */
    public function testPreparingUnknownMiddlewareTypeRaisesException($invalid, $expectedMessage)
    {
        $this->expectException(InvalidMiddlewareException::class);
        $this->expectExceptionMessage($expectedMessage);
        $this->prepareMiddlewareWithoutContainer($invalid);
    }
}
