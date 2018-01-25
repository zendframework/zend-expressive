<?php
/**
 * @see       https://github.com/zendframework/zend-expressive for the canonical source repository
 * @copyright Copyright (c) 2015-2017 Zend Technologies USA Inc. (https://www.zend.com)
 * @license   https://github.com/zendframework/zend-expressive/blob/master/LICENSE.md New BSD License
 */

declare(strict_types=1);

namespace ZendTest\Expressive\Container;

use ArrayObject;
use PHPUnit\Framework\Assert;
use PHPUnit\Framework\TestCase;
use Prophecy\Prophecy\ObjectProphecy;
use Psr\Container\ContainerInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use ReflectionProperty;
use Zend\Diactoros\Response\EmitterInterface;
use Zend\Diactoros\Response\SapiEmitter;
use Zend\Expressive\Application;
use Zend\Expressive\Container\ApplicationFactory;
use Zend\Expressive\Emitter\EmitterStack;
use Zend\Expressive\Exception as ExpressiveException;
use Zend\Expressive\Handler\NotFoundHandler;
use Zend\Expressive\Middleware\DispatchMiddleware;
use Zend\Expressive\Middleware\LazyLoadingMiddleware;
use Zend\Expressive\Middleware\RouteMiddleware;
use Zend\Expressive\Router\FastRouteRouter;
use Zend\Expressive\Router\Route;
use Zend\Expressive\Router\RouterInterface;
use Zend\Stratigility\Middleware\PathMiddlewareDecorator;
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

    /** @var RequestHandlerInterface|ObjectProphecy */
    protected $handler;

    /** @var RouterInterface|ObjectProphecy */
    protected $router;

    public function setUp()
    {
        $this->container = $this->mockContainerInterface();
        $this->factory   = new ApplicationFactory();

        $this->router = $this->prophesize(RouterInterface::class);
        $this->emitter = $this->prophesize(EmitterInterface::class);
        $this->handler = $this->prophesize(RequestHandlerInterface::class)->reveal();

        $this->injectServiceInContainer($this->container, RouterInterface::class, $this->router->reveal());
        $this->injectServiceInContainer($this->container, EmitterInterface::class, $this->emitter->reveal());
        $this->injectServiceInContainer($this->container, 'Zend\Expressive\Handler\DefaultHandler', $this->handler);
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

                if (! $route->getMiddleware() instanceof MiddlewareInterface) {
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

    public function getInternalQueueFromApplication(Application $app)
    {
        $r = new ReflectionProperty($app, 'pipeline');
        $r->setAccessible(true);
        $pipeline = $r->getValue($app);

        $r = new ReflectionProperty($pipeline, 'pipeline');
        $r->setAccessible(true);
        return $r->getValue($pipeline);
    }

    public function injectRouteAndDispatchMiddleware()
    {
        $routeMiddleware = $this->prophesize(RouteMiddleware::class)->reveal();
        $this->injectServiceInContainer($this->container, RouteMiddleware::class, $routeMiddleware);
        $dispatchMiddleware = $this->prophesize(DispatchMiddleware::class)->reveal();
        $this->injectServiceInContainer($this->container, DispatchMiddleware::class, $dispatchMiddleware);
    }

    public function testFactoryWillPullAllReplaceableDependenciesFromContainerWhenPresent()
    {
        $app = $this->factory->__invoke($this->container->reveal());
        $this->assertInstanceOf(Application::class, $app);
        $test = $this->getRouterFromApplication($app);
        $this->assertSame($this->router->reveal(), $test);
        $this->assertSame($this->container->reveal(), $app->getContainer());
        $this->assertSame($this->emitter->reveal(), $app->getEmitter());
        $this->assertSame($this->handler, $app->getDefaultHandler());
    }

    public function callableMiddlewares()
    {
        $middleware = [
            'service-name' => 'HelloWorld',
            'closure' => function () {
            },
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
        $this->container->has('HelloWorld')->willReturn(true);
        $this->container->has('Ping')->willReturn(true);

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
        $this->injectRouteAndDispatchMiddleware();

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
        $this->assertInstanceOf(SapiEmitter::class, $app->getEmitter()->pop(), var_export($app->getEmitter(), true));
        $this->assertInstanceOf(NotFoundHandler::class, $app->getDefaultHandler());
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
     * @group xya
     */
    public function testCanSpecifyRouteViaConfigurationWithNoMethods($configType)
    {
        $this->container->has('HelloWorld')->willReturn(true);

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
        $this->injectRouteAndDispatchMiddleware();

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
        $this->container->has('HelloWorld')->willReturn(true);

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
        $this->injectRouteAndDispatchMiddleware();

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
        $this->container->has('HelloWorld')->willReturn(true);

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

        $pipeline = $this->getInternalQueueFromApplication($app);

        $this->assertCount(5, $pipeline, 'Did not get expected pipeline count!');

        $test = $pipeline->dequeue();
        $this->assertInstanceOf(PathMiddlewareDecorator::class, $test);
        $this->assertAttributeSame('/api', 'prefix', $test);
        $this->assertAttributeSame($api, 'middleware', $test);

        $test = $pipeline->dequeue();
        $this->assertInstanceOf(PathMiddlewareDecorator::class, $test);
        $this->assertAttributeSame('/dynamic-path', 'prefix', $test);
        $this->assertAttributeInstanceOf(LazyLoadingMiddleware::class, 'middleware', $test);

        $test = $pipeline->dequeue();
        $this->assertSame($noPath, $test);

        $test = $pipeline->dequeue();
        $this->assertNotSame($goodbye, $test);
        $this->assertInstanceOf(LazyLoadingMiddleware::class, $test);

        $test = $pipeline->dequeue();
        $this->assertInstanceOf(MiddlewarePipe::class, $test);

        $r = new ReflectionProperty($test, 'pipeline');
        $r->setAccessible(true);
        $nestedPipeline = $r->getValue($test);

        $test = $nestedPipeline->dequeue();
        $this->assertSame($pipelineFirst, $test);

        // Lazy middleware is not marshaled until invocation
        $test = $nestedPipeline->dequeue();
        $this->assertNotSame($hello, $test);
        $this->assertInstanceOf(LazyLoadingMiddleware::class, $test);

        $test = $nestedPipeline->dequeue();
        $this->assertSame($pipelineLast, $test);
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
        $this->injectRouteAndDispatchMiddleware();

        $config = $configType ? new $configType($config) : $config;
        $this->injectServiceInContainer($this->container, 'config', $config);

        $app = $this->factory->__invoke($this->container->reveal());

        $pipeline = $this->getInternalQueueFromApplication($app);

        $this->assertCount(2, $pipeline, 'Did not get expected pipeline count!');

        $test = $pipeline->dequeue();
        $this->assertInstanceOf(RouteMiddleware::class, $test);

        $test = $pipeline->dequeue();
        $this->assertInstanceOf(DispatchMiddleware::class, $test);
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

        $pipeline = $this->getInternalQueueFromApplication($app);
        $this->assertCount(0, $pipeline, 'Pipeline contains entries and should not');

        $routes = $app->getRoutes();
        $this->assertEmpty($routes, 'Routes exist, and should not');
    }
}
