<?php
/**
 * @see       https://github.com/zendframework/zend-expressive for the canonical source repository
 * @copyright Copyright (c) 2018 Zend Technologies USA Inc. (https://www.zend.com)
 * @license   https://github.com/zendframework/zend-expressive/blob/master/LICENSE.md New BSD License
 */

declare(strict_types=1);

namespace ZendTest\Expressive;

use PHPUnit\Framework\TestCase;
use Psr\Http\Server\MiddlewareInterface;
use ReflectionProperty;
use Zend\Expressive\Exception;
use Zend\Expressive\MiddlewareContainer;
use Zend\Expressive\MiddlewareFactory;
use Zend\Expressive\Middleware\DispatchMiddleware;
use Zend\Expressive\Middleware\LazyLoadingMiddleware;
use Zend\Stratigility\MiddlewarePipe;
use Zend\Stratigility\Middleware\CallableMiddlewareDecorator;
use Zend\Stratigility\Middleware\DoublePassMiddlewareDecorator;
use Zend\Stratigility\Middleware\PathMiddlewareDecorator;

class MiddlewareFactoryTest extends TestCase
{
    public function setUp()
    {
        $this->container = $this->prophesize(MiddlewareContainer::class);
        $this->factory = new MiddlewareFactory($this->container->reveal());
    }

    public function assertLazyLoadingMiddleware(string $expectedMiddlewareName, MiddlewareInterface $middleware)
    {
        $this->assertInstanceOf(LazyLoadingMiddleware::class, $middleware);
        $this->assertAttributeSame($this->container->reveal(), 'container', $middleware);
        $this->assertAttributeSame($expectedMiddlewareName, 'middlewareName', $middleware);
    }

    public function assertCallableMiddleware(callable $expectedCallable, MiddlewareInterface $middleware)
    {
        $this->assertInstanceOf(CallableMiddlewareDecorator::class, $middleware);
        $this->assertAttributeSame($expectedCallable, 'middleware', $middleware);
    }

    public function assertPipeline(array $expectedPipeline, MiddlewareInterface $middleware)
    {
        $this->assertInstanceOf(MiddlewarePipe::class, $middleware);
        $pipeline = $this->reflectPipeline($middleware);
        $this->assertSame($expectedPipeline, $pipeline);
    }

    public function reflectPipeline(MiddlewarePipe $pipeline) : array
    {
        $r = new ReflectionProperty($pipeline, 'pipeline');
        $r->setAccessible(true);
        return iterator_to_array($r->getValue($pipeline));
    }

    public function testCallableDecoratesCallableMiddleware()
    {
        $callable = function ($request, $handler) {
        };

        $middleware = $this->factory->callable($callable);
        $this->assertCallableMiddleware($callable, $middleware);
    }

    public function testLazyLoadingMiddlewareDecoratesMiddlewareServiceName()
    {
        $middleware = $this->factory->lazy('service');
        $this->assertLazyLoadingMiddleware('service', $middleware);
    }

    public function testPrepareReturnsMiddlewareImplementationsVerbatim()
    {
        $middleware = $this->prophesize(MiddlewareInterface::class)->reveal();
        $this->assertSame($middleware, $this->factory->prepare($middleware));
    }

    public function testPrepareDecoratesCallables()
    {
        $callable = function ($request, $handler) {
        };

        $middleware = $this->factory->prepare($callable);
        $this->assertInstanceOf(CallableMiddlewareDecorator::class, $middleware);
        $this->assertAttributeSame($callable, 'middleware', $middleware);
    }

    public function testPrepareDecoratesServiceNamesAsLazyLoadingMiddleware()
    {
        $middleware = $this->factory->prepare('service');
        $this->assertInstanceOf(LazyLoadingMiddleware::class, $middleware);
        $this->assertAttributeSame('service', 'middlewareName', $middleware);
        $this->assertAttributeSame($this->container->reveal(), 'container', $middleware);
    }

    public function testPrepareDecoratesArraysAsMiddlewarePipes()
    {
        $middleware1 = $this->prophesize(MiddlewareInterface::class)->reveal();
        $middleware2 = $this->prophesize(MiddlewareInterface::class)->reveal();
        $middleware3 = $this->prophesize(MiddlewareInterface::class)->reveal();

        $middleware = $this->factory->prepare([$middleware1, $middleware2, $middleware3]);
        $this->assertPipeline([$middleware1, $middleware2, $middleware3], $middleware);
    }

    public function invalidMiddlewareTypes() : iterable
    {
        yield 'null' => [null];
        yield 'false' => [false];
        yield 'true' => [true];
        yield 'zero' => [0];
        yield 'int' => [1];
        yield 'zero-float' => [0.0];
        yield 'float' => [1.1];
        yield 'object' => [(object) ['foo' => 'bar']];
    }

    /**
     * @dataProvider invalidMiddlewareTypes
     */
    public function testPrepareRaisesExceptionForTypesItDoesNotUnderstand($middleware)
    {
        $this->expectException(Exception\InvalidMiddlewareException::class);
        $this->factory->prepare($middleware);
    }

    public function testPipelineAcceptsMultipleArguments()
    {
        $middleware1 = $this->prophesize(MiddlewareInterface::class)->reveal();
        $middleware2 = $this->prophesize(MiddlewareInterface::class)->reveal();
        $middleware3 = $this->prophesize(MiddlewareInterface::class)->reveal();

        $middleware = $this->factory->pipeline($middleware1, $middleware2, $middleware3);
        $this->assertPipeline([$middleware1, $middleware2, $middleware3], $middleware);
    }

    public function testPipelineAcceptsASingleArrayArgument()
    {
        $middleware1 = $this->prophesize(MiddlewareInterface::class)->reveal();
        $middleware2 = $this->prophesize(MiddlewareInterface::class)->reveal();
        $middleware3 = $this->prophesize(MiddlewareInterface::class)->reveal();

        $middleware = $this->factory->pipeline([$middleware1, $middleware2, $middleware3]);
        $this->assertPipeline([$middleware1, $middleware2, $middleware3], $middleware);
    }

    public function validPrepareTypes()
    {
        yield 'service' => ['service', 'assertLazyLoadingMiddleware', 'service'];

        $callable = function ($request, $handler) {
        };
        yield 'callable' => [$callable, 'assertCallableMiddleware', $callable];

        $middleware = new DispatchMiddleware();
        yield 'instance' => [$middleware, 'assertSame', $middleware];
    }

    /**
     * @dataProvider validPrepareTypes
     * @param string|callable|MiddlewareInterface $middleware
     * @param mixed $expected Expected type or value for use with assertion
     */
    public function testPipelineAllowsAnyTypeSupportedByPrepare(
        $middleware,
        string $assertion,
        $expected
    ) {
        $pipeline = $this->factory->pipeline($middleware);
        $this->assertInstanceOf(MiddlewarePipe::class, $pipeline);

        $r = new ReflectionProperty($pipeline, 'pipeline');
        $r->setAccessible(true);
        $values = iterator_to_array($r->getValue($pipeline));
        $received = array_shift($values);

        $this->{$assertion}($expected, $received);
    }

    public function testPipelineAllowsPipingArraysOfMiddlewareAndCastsThemToInternalPipelines()
    {
        $callable = function ($request, $handler) {
        };
        $middleware = new DispatchMiddleware();

        $internalPipeline = [$callable, $middleware];

        $pipeline = $this->factory->pipeline($internalPipeline);

        $this->assertInstanceOf(MiddlewarePipe::class, $pipeline);
        $received = $this->reflectPipeline($pipeline);
        $this->assertCount(2, $received);
        $this->assertCallableMiddleware($callable, $received[0]);
        $this->assertSame($middleware, $received[1]);
    }

    /**
     * @dataProvider validPrepareTypes
     * @param string|callable|MiddlewareInterface $middleware
     * @param mixed $expected Expected type or value for use with assertion
     */
    public function testPathCreatesPathMiddlewareDecoratorUsingAnyMiddlewareTypeSupportedByPrepare(
        $middleware,
        string $assertion,
        $expected
    ) {
        $decorator = $this->factory->path('/foo', $middleware);
        $this->assertInstanceOf(PathMiddlewareDecorator::class, $decorator);

        $this->assertAttributeSame('/foo', 'prefix', $decorator);

        $r = new ReflectionProperty($decorator, 'middleware');
        $r->setAccessible(true);
        $received = $r->getValue($decorator);

        $this->{$assertion}($expected, $received);
    }

    public function testPathCanAcceptAnArrayOfMiddleware()
    {
        $callable = function ($request, $handler) {
        };
        $middleware = new DispatchMiddleware();

        $internalPipeline = [$callable, $middleware];

        $decorator = $this->factory->path('/foo', $internalPipeline);

        $this->assertInstanceOf(PathMiddlewareDecorator::class, $decorator);

        $this->assertAttributeSame('/foo', 'prefix', $decorator);

        $r = new ReflectionProperty($decorator, 'middleware');
        $r->setAccessible(true);
        $received = $r->getValue($decorator);

        $received = $this->reflectPipeline($received);
        $this->assertCount(2, $received);
        $this->assertCallableMiddleware($callable, $received[0]);
        $this->assertSame($middleware, $received[1]);
    }
}
