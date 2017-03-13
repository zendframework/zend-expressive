<?php
/**
 * @see       https://github.com/zendframework/zend-expressive for the canonical source repository
 * @copyright Copyright (c) 2015-2017 Zend Technologies USA Inc. (http://www.zend.com)
 * @license   https://github.com/zendframework/zend-expressive/blob/master/LICENSE.md New BSD License
 */

namespace ZendTest\Expressive\Container;

use ArrayObject;
use Interop\Http\ServerMiddleware\DelegateInterface;
use PHPUnit\Framework\Assert;
use PHPUnit\Framework\TestCase;
use Prophecy\Prophecy\ObjectProphecy;
use Psr\Container\ContainerInterface;
use ReflectionProperty;
use Zend\Diactoros\Response\EmitterInterface;
use Zend\Diactoros\Response\SapiEmitter;
use Zend\Expressive\Application;
use Zend\Expressive\Container\ApplicationFactory;
use Zend\Expressive\Delegate\NotFoundDelegate;
use Zend\Expressive\Emitter\EmitterStack;
use Zend\Expressive\Exception as ExpressiveException;
use Zend\Expressive\Middleware\DispatchMiddleware;
use Zend\Expressive\Middleware\LazyLoadingMiddleware;
use Zend\Expressive\Middleware\RouteMiddleware;
use Zend\Expressive\Router\FastRouteRouter;
use Zend\Expressive\Router\Route;
use Zend\Expressive\Router\RouterInterface;
use Zend\Stratigility\MiddlewarePipe;
use ZendTest\Expressive\ContainerTrait;
use ZendTest\Expressive\TestAsset\InteropMiddleware;
use ZendTest\Expressive\TestAsset\InvokableMiddleware;

/**
 * @covers Zend\Expressive\Container\ApplicationFactory
 */
class ApplicationFactoryTest extends TestCase
{
    use ContainerTrait;

    /** @var ContainerInterface|ObjectProphecy */
    protected $container;

    /** @var ApplicationFactory */
    private $factory;

    /** @var EmitterInterface|ObjectProphecy */
    protected $emitter;

    /** @var DelegateInterface|ObjectProphecy */
    protected $delegate;

    /** @var RouterInterface|ObjectProphecy */
    protected $router;

    public function setUp()
    {
        $this->container = $this->mockContainerInterface();
        $this->factory   = new ApplicationFactory();

        $this->router = $this->prophesize(RouterInterface::class);
        $this->emitter = $this->prophesize(EmitterInterface::class);
        $this->delegate = $this->prophesize(DelegateInterface::class)->reveal();

        $this->injectServiceInContainer($this->container, RouterInterface::class, $this->router->reveal());
        $this->injectServiceInContainer($this->container, EmitterInterface::class, $this->emitter->reveal());
        $this->injectServiceInContainer($this->container, 'Zend\Expressive\Delegate\DefaultDelegate', $this->delegate);
    }

    public static function assertRoute($spec, array $routes)
    {
        Assert::assertThat(
            array_reduce($routes, function ($found, $route) use ($spec) {
                if ($found) {
                    return $found;
                }

                if ($route->getPath() !== $spec['path']) {
                    return false;
                }

                if ($route->getMiddleware() !== $spec['middleware']) {
                    return false;
                }

                if (isset($spec['allowed_methods'])) {
                    if ($route->getAllowedMethods() !== $spec['allowed_methods']) {
                        return false;
                    }
                }

                if (! isset($spec['allowed_methods'])) {
                    if ($route->getAllowedMethods() !== Route::HTTP_METHOD_ANY) {
                        return false;
                    }
                }

                return true;
            }, false),
            Assert::isTrue(),
            'Route matching specification not found'
        );
    }

    public function getRouterFromApplication(Application $app)
    {
        $r = new ReflectionProperty($app, 'router');
        $r->setAccessible(true);
        return $r->getValue($app);
    }

    public function testFactoryWillPullAllReplaceableDependenciesFromContainerWhenPresent()
    {
        $app = $this->factory->__invoke($this->container->reveal());
        $this->assertInstanceOf(Application::class, $app);
        $test = $this->getRouterFromApplication($app);
        $this->assertSame($this->router->reveal(), $test);
        $this->assertSame($this->container->reveal(), $app->getContainer());
        $this->assertSame($this->emitter->reveal(), $app->getEmitter());
        $this->assertSame($this->delegate, $app->getDefaultDelegate());
    }

    public function callableMiddlewares()
    {
        $middleware = [
            'service-name' => 'HelloWorld',
            'closure' => function () {
            },
            'callable' => [InvokableMiddleware::class, 'staticallyCallableMiddleware'],
        ];

        $configTypes = [
            'array' => null,
            'array-object' => ArrayObject::class,
        ];

        foreach ($configTypes as $configType => $config) {
            foreach ($middleware as $middlewareType => $middleware) {
                $name = sprintf('%s-%s', $configType, $middlewareType);
                yield $name => [$middleware, $config];
            }
        }
    }

    /**
     * @dataProvider callableMiddlewares
     *
     * @param callable|array|string $middleware
     * @param string $configType
     */
    public function testFactorySetsUpRoutesFromConfig($middleware, $configType)
    {
        $config = [
            'routes' => [
                [
                    'path' => '/',
                    'middleware' => $middleware,
                    'allowed_methods' => ['GET'],
                ],
                [
                    'path' => '/ping',
                    'middleware' => 'Ping',
                    'allowed_methods' => ['GET'],
                ],
            ],
        ];

        $config = $configType ? new $configType($config) : $config;

        $this->injectServiceInContainer($this->container, 'config', $config);

        $app = $this->factory->__invoke($this->container->reveal());

        $routes = $app->getRoutes();

        foreach ($config['routes'] as $route) {
            $this->assertRoute($route, $routes);
        }
    }

    public function testWillUseSaneDefaultsForOptionalServices()
    {
        $container = $this->mockContainerInterface();
        $factory = new ApplicationFactory();

        $app = $factory->__invoke($container->reveal());
        $this->assertInstanceOf(Application::class, $app);
        $router = $this->getRouterFromApplication($app);
        $this->assertInstanceOf(FastRouteRouter::class, $router);
        $this->assertSame($container->reveal(), $app->getContainer());
        $this->assertInstanceOf(EmitterStack::class, $app->getEmitter());
        $this->assertCount(1, $app->getEmitter());
        $this->assertInstanceOf(SapiEmitter::class, $app->getEmitter()->pop());
        $this->assertInstanceOf(NotFoundDelegate::class, $app->getDefaultDelegate());
    }

    public function configTypes()
    {
        return [
            'array'        => [null],
            'array-object' => [ArrayObject::class],
        ];
    }

    /**
     * @dataProvider configTypes
     * @group piping
     *
     * @param null|string $configType
     */
    public function testMiddlewareIsNotAddedIfSpecIsInvalid($configType)
    {
        $config = [
            'middleware_pipeline' => [
                ['foo' => 'bar'],
                ['path' => '/foo'],
            ],
        ];

        $config = $configType ? new $configType($config) : $config;

        $this->injectServiceInContainer($this->container, 'config', $config);

        $this->expectException(ExpressiveException\InvalidArgumentException::class);
        $this->expectExceptionMessage('pipeline');
        $this->factory->__invoke($this->container->reveal());
    }

    /**
     * @dataProvider configTypes
     *
     * @param null|string $configType
     */
    public function testCanSpecifyRouteViaConfigurationWithNoMethods($configType)
    {
        $config = [
            'routes' => [
                [
                    'path' => '/',
                    'middleware' => 'HelloWorld',
                ],
            ],
        ];

        $config = $configType ? new $configType($config) : $config;

        $this->injectServiceInContainer($this->container, 'config', $config);

        $app = $this->factory->__invoke($this->container->reveal());

        $routes = $app->getRoutes();

        foreach ($config['routes'] as $route) {
            $this->assertRoute($route, $routes);
        }
    }

    /**
     * @dataProvider configTypes
     *
     * @param null|string $configType
     */
    public function testCanSpecifyRouteOptionsViaConfiguration($configType)
    {
        $expected = [
            'values' => [
                'foo' => 'bar',
            ],
            'tokens' => [
                'bar' => 'foo',
            ],
        ];
        $config = [
            'routes' => [
                [
                    'path' => '/',
                    'middleware' => 'HelloWorld',
                    'name' => 'home',
                    'allowed_methods' => ['GET'],
                    'options' => $expected,
                ],
            ],
        ];

        $config = $configType ? new $configType($config) : $config;

        $this->injectServiceInContainer($this->container, 'config', $config);

        $app = $this->factory->__invoke($this->container->reveal());

        $routes = $app->getRoutes();
        $route  = array_shift($routes);

        $this->assertInstanceOf(Route::class, $route);
        $this->assertEquals($expected, $route->getOptions());
    }

    /**
     * @dataProvider configTypes
     *
     * @param null|string $configType
     */
    public function testExceptionIsRaisedInCaseOfInvalidRouteMethodsConfiguration($configType)
    {
        $config = [
            'routes' => [
                [
                    'path' => '/',
                    'middleware' => 'HelloWorld',
                    'allowed_methods' => 'invalid',
                ],
            ],
        ];

        $config = $configType ? new $configType($config) : $config;

        $this->injectServiceInContainer($this->container, 'config', $config);

        $this->expectException(ExpressiveException\InvalidArgumentException::class);
        $this->expectExceptionMessage('route must be in form of an array; received "string"');
        $this->factory->__invoke($this->container->reveal());
    }

    /**
     * @dataProvider configTypes
     *
     * @param null|string $configType
     */
    public function testExceptionIsRaisedInCaseOfInvalidRouteOptionsConfiguration($configType)
    {
        $config = [
            'routes' => [
                [
                    'path' => '/',
                    'middleware' => 'HelloWorld',
                    'options' => 'invalid',
                ],
            ],
        ];

        $config = $configType ? new $configType($config) : $config;

        $this->injectServiceInContainer($this->container, 'config', $config);

        $this->expectException(ExpressiveException\InvalidArgumentException::class);
        $this->expectExceptionMessage('options must be an array; received "string"');
        $this->factory->__invoke($this->container->reveal());
    }

    /**
     * @dataProvider configTypes
     *
     * @param null|string $configType
     */
    public function testWillCreatePipelineBasedOnMiddlewareConfiguration($configType)
    {
        $api = new InteropMiddleware();

        $dynamicPath   = clone $api;
        $noPath        = clone $api;
        $goodbye       = clone $api;
        $pipelineFirst = clone $api;
        $hello         = clone $api;
        $pipelineLast  = clone $api;

        $this->injectServiceInContainer($this->container, 'DynamicPath', $dynamicPath);
        $this->injectServiceInContainer($this->container, 'Goodbye', $goodbye);
        $this->injectServiceInContainer($this->container, 'Hello', $hello);

        $pipeline = [
            ['path' => '/api', 'middleware' => $api],
            ['path' => '/dynamic-path', 'middleware' => 'DynamicPath'],
            ['middleware' => $noPath],
            ['middleware' => 'Goodbye'],
            [
                'middleware' => [
                    $pipelineFirst,
                    'Hello',
                    $pipelineLast,
                ],
            ],
        ];

        $config = ['middleware_pipeline' => $pipeline];
        $config = $configType ? new $configType($config) : $config;
        $this->injectServiceInContainer($this->container, 'config', $config);

        $app = $this->factory->__invoke($this->container->reveal());

        $this->assertAttributeSame(
            false,
            'routeMiddlewareIsRegistered',
            $app,
            'Route middleware was registered when it should not have been'
        );

        $this->assertAttributeSame(
            false,
            'dispatchMiddlewareIsRegistered',
            $app,
            'Dispatch middleware was registered when it should not have been'
        );

        $r = new ReflectionProperty($app, 'pipeline');
        $r->setAccessible(true);
        $pipeline = $r->getValue($app);

        $this->assertCount(5, $pipeline, 'Did not get expected pipeline count!');

        $test = $pipeline->dequeue();
        $this->assertEquals('/api', $test->path);
        $this->assertSame($api, $test->handler);

        // Lazy middleware is not marshaled until invocation
        $test = $pipeline->dequeue();
        $this->assertEquals('/dynamic-path', $test->path);
        $this->assertNotSame($dynamicPath, $test->handler);
        $this->assertInstanceOf(LazyLoadingMiddleware::class, $test->handler);

        $test = $pipeline->dequeue();
        $this->assertEquals('/', $test->path);
        $this->assertSame($noPath, $test->handler);

        // Lazy middleware is not marshaled until invocation
        $test = $pipeline->dequeue();
        $this->assertEquals('/', $test->path);
        $this->assertNotSame($goodbye, $test->handler);
        $this->assertInstanceOf(LazyLoadingMiddleware::class, $test->handler);

        $test = $pipeline->dequeue();
        $nestedPipeline = $test->handler;
        $this->assertInstanceOf(MiddlewarePipe::class, $nestedPipeline);

        $r = new ReflectionProperty($nestedPipeline, 'pipeline');
        $r->setAccessible(true);
        $nestedPipeline = $r->getValue($nestedPipeline);

        $test = $nestedPipeline->dequeue();
        $this->assertSame($pipelineFirst, $test->handler);

        // Lazy middleware is not marshaled until invocation
        $test = $nestedPipeline->dequeue();
        $this->assertNotSame($hello, $test->handler);
        $this->assertInstanceOf(LazyLoadingMiddleware::class, $test->handler);

        $test = $nestedPipeline->dequeue();
        $this->assertSame($pipelineLast, $test->handler);
    }

    public function configWithRoutesButNoPipeline()
    {
        $middleware = function ($request, $response, $next) {
        };

        $routes = [
            [
                'path' => '/',
                'middleware' => clone $middleware,
                'allowed_methods' => ['GET'],
            ],
        ];

        $configs = [
            'no-pipeline-defined' => ['routes' => $routes],
            'empty-pipeline'      => ['middleware_pipeline' => [], 'routes' => $routes],
            'null-pipeline'       => ['middleware_pipeline' => null, 'routes' => $routes],
        ];

        $configTypes = [
            'array'        => null,
            'array-object' => ArrayObject::class,
        ];

        foreach ($configTypes as $configName => $configType) {
            foreach ($configs as $name => $config) {
                $caseName = sprintf('%s-%s', $configName, $name);
                yield $caseName => [$config, $configType];
            }
        }
    }

    /**
     * @dataProvider configWithRoutesButNoPipeline
     *
     * @param array $config
     * @param string|null $configType
     */
    public function testProvidingRoutesAndNoPipelineImplicitlyRegistersRoutingAndDispatchMiddleware(
        array $config,
        $configType
    ) {
        $config = $configType ? new $configType($config) : $config;
        $this->injectServiceInContainer($this->container, 'config', $config);
        $app = $this->factory->__invoke($this->container->reveal());
        $this->assertAttributeSame(true, 'routeMiddlewareIsRegistered', $app);
        $this->assertAttributeSame(true, 'dispatchMiddlewareIsRegistered', $app);

        $r = new ReflectionProperty($app, 'pipeline');
        $r->setAccessible(true);
        $pipeline = $r->getValue($app);

        $this->assertCount(2, $pipeline, 'Did not get expected pipeline count!');

        $test = $pipeline->dequeue();
        $this->assertEquals('/', $test->path);
        $this->assertInstanceOf(RouteMiddleware::class, $test->handler);

        $test = $pipeline->dequeue();
        $this->assertEquals('/', $test->path);
        $this->assertInstanceOf(DispatchMiddleware::class, $test->handler);
    }

    /**
     * @dataProvider configTypes
     * @group programmatic
     *
     * @param null|string $configType
     */
    public function testWillNotInjectConfiguredRoutesOrPipelineIfProgrammaticPipelineFlagEnabled($configType)
    {
        $api = new InteropMiddleware();

        $dynamicPath = clone $api;
        $noPath = clone $api;
        $goodbye = clone $api;
        $pipelineFirst = clone $api;
        $hello = clone $api;
        $pipelineLast = clone $api;

        $config = [
            'middleware_pipeline' => [
                ['path' => '/api', 'middleware' => $api],
                ['path' => '/dynamic-path', 'middleware' => 'DynamicPath'],
                ['middleware' => $noPath],
                ['middleware' => 'Goodbye'],
                [
                    'middleware' => [
                        $pipelineFirst,
                        'Hello',
                        $pipelineLast,
                    ],
                ],
            ],
            'routes' => [
                [
                    'path' => '/',
                    'middleware' => 'HelloWorld',
                    'name' => 'home',
                    'allowed_methods' => ['GET'],
                    'options' => [],
                ],
            ],
            'zend-expressive' => [
                'programmatic_pipeline' => true,
            ],
        ];

        $config = $configType ? new $configType($config) : $config;

        $this->injectServiceInContainer($this->container, 'DynamicPath', $dynamicPath);
        $this->injectServiceInContainer($this->container, 'Goodbye', $goodbye);
        $this->injectServiceInContainer($this->container, 'Hello', $hello);
        $this->injectServiceInContainer($this->container, 'config', $config);

        $app = $this->factory->__invoke($this->container->reveal());

        $this->assertAttributeSame(false, 'routeMiddlewareIsRegistered', $app);
        $this->assertAttributeSame(false, 'dispatchMiddlewareIsRegistered', $app);

        $r = new ReflectionProperty($app, 'pipeline');
        $r->setAccessible(true);
        $pipeline = $r->getValue($app);
        $this->assertCount(0, $pipeline, 'Pipeline contains entries and should not');

        $routes = $app->getRoutes();
        $this->assertEmpty($routes, 'Routes exist, and should not');
    }
}
