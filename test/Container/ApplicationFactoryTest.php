<?php
/**
 * @see       https://github.com/zendframework/zend-expressive for the canonical source repository
 * @copyright Copyright (c) 2015-2017 Zend Technologies USA Inc. (http://www.zend.com)
 * @license   https://github.com/zendframework/zend-expressive/blob/master/LICENSE.md New BSD License
 */

namespace ZendTest\Expressive\Container;

use Closure;
use Interop\Http\ServerMiddleware\DelegateInterface;
use InvalidArgumentException;
use PHPUnit\Framework\Assert;
use PHPUnit\Framework\TestCase;
use Prophecy\Prophecy\ObjectProphecy;
use Psr\Http\Message\ServerRequestInterface;
use ReflectionFunction;
use ReflectionProperty;
use SplQueue;
use Zend\Diactoros\Response\EmitterInterface;
use Zend\Diactoros\Response\SapiEmitter;
use Zend\Expressive\Application;
use Zend\Expressive\Container\ApplicationFactory;
use Zend\Expressive\Emitter\EmitterStack;
use Zend\Expressive\Exception as ExpressiveException;
use Zend\Expressive\Exception\InvalidMiddlewareException;
use Zend\Expressive\Middleware\DispatchMiddleware;
use Zend\Expressive\Middleware\LazyLoadingMiddleware;
use Zend\Expressive\Middleware\RouteMiddleware;
use Zend\Expressive\Router\FastRouteRouter;
use Zend\Expressive\Router\Route;
use Zend\Expressive\Router\RouterInterface;
use Zend\Stratigility\MiddlewarePipe;
use Zend\Stratigility\NoopFinalHandler;
use Zend\Stratigility\Route as StratigilityRoute;
use ZendTest\Expressive\ContainerTrait;
use ZendTest\Expressive\TestAsset\InteropMiddleware;
use ZendTest\Expressive\TestAsset\InvokableMiddleware;

/**
 * @covers Zend\Expressive\Container\ApplicationFactory
 */
class ApplicationFactoryTest extends TestCase
{
    use ContainerTrait;

    /** @var ObjectProphecy */
    protected $container;

    /** @var ObjectProphecy */
    protected $emitter;

    /** @var ObjectProphecy */
    protected $delegate;

    /** @var ObjectProphecy */
    protected $router;

    public function setUp()
    {
        $this->container = $this->mockContainerInterface();
        $this->factory   = new ApplicationFactory();

        $this->router = $this->prophesize(RouterInterface::class);
        $this->emitter = $this->prophesize(EmitterInterface::class);
        $this->delegate = function ($req, $res, $err = null) {
        };

        $this->injectServiceInContainer($this->container, RouterInterface::class, $this->router->reveal());
        $this->injectServiceInContainer($this->container, EmitterInterface::class, $this->emitter->reveal());
        $this->injectServiceInContainer($this->container, 'Zend\Stratigility\NoopFinalHandler', $this->delegate);
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

    public static function assertPipelineContainsInstanceOf($class, $pipeline, $message = null)
    {
        $message = $message ?: 'Did not find expected middleware class type in pipeline';
        $found   = false;

        foreach ($pipeline as $middleware) {
            if ($middleware instanceof $class) {
                $found = true;
                break;
            }
        }

        Assert::assertThat($found, Assert::isTrue(), $message);
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
        return [
           ['HelloWorld'],
           [
                function () {
                }
           ],
           [[InvokableMiddleware::class, 'staticallyCallableMiddleware']],
        ];
    }

    /**
     * @dataProvider callableMiddlewares
     */
    public function testFactorySetsUpRoutesFromConfig($middleware)
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

        $this->injectServiceInContainer($this->container, 'config', $config);

        $app = $this->factory->__invoke($this->container->reveal());

        $routes = $app->getRoutes();

        foreach ($config['routes'] as $route) {
            $this->assertRoute($route, $routes);
        }
    }

    public function testNoRoutesAreAddedIfSpecDoesNotProvidePathOrMiddleware()
    {
        $config = [
            'routes' => [
                [
                    'allowed_methods' => ['GET'],
                ],
                [
                    'allowed_methods' => ['POST'],
                ],
            ],
        ];

        $this->injectServiceInContainer($this->container, 'config', $config);

        $app = $this->factory->__invoke($this->container->reveal());

        $routes = $app->getRoutes();
        $this->assertCount(0, $routes);
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
        $this->assertEquals(new NoopFinalHandler(), $app->getDefaultDelegate());
    }

    /**
     * @group piping
     */
    public function testPipedMiddlewareAsServiceNamesAreReturnedAsClosuresThatPullFromContainer()
    {
        $middleware = function ($req, $res, $next = null) {
            return true;
        };

        $config = [
            'middleware_pipeline' => [
                ['middleware' => 'Middleware'],
                ['path' => '/foo', 'middleware' => 'Middleware'],
            ],
        ];

        $this->injectServiceInContainer($this->container, 'config', $config);
        $this->injectServiceInContainer($this->container, 'Middleware', $middleware);

        $app = $this->factory->__invoke($this->container->reveal());

        $r = new ReflectionProperty($app, 'pipeline');
        $r->setAccessible(true);
        $pipeline = $r->getValue($app);

        $this->assertCount(2, $pipeline);

        $route = $pipeline->dequeue();
        $this->assertInstanceOf(StratigilityRoute::class, $route);
        $this->assertInstanceOf(LazyLoadingMiddleware::class, $route->handler);
        $this->assertEquals('/', $route->path);

        $route = $pipeline->dequeue();
        $this->assertInstanceOf(StratigilityRoute::class, $route);
        $this->assertInstanceOf(LazyLoadingMiddleware::class, $route->handler);
        $this->assertEquals('/foo', $route->path);
    }

    /**
     * @group piping
     */
    public function testMiddlewareIsNotAddedIfSpecIsInvalid()
    {
        $config = [
            'middleware_pipeline' => [
                ['foo' => 'bar'],
                ['path' => '/foo'],
            ],
        ];

        $this->injectServiceInContainer($this->container, 'config', $config);

        $this->expectException(ExpressiveException\InvalidArgumentException::class);
        $this->expectExceptionMessage('pipeline');
        $app = $this->factory->__invoke($this->container->reveal());
    }

    public function uncallableMiddleware()
    {
        return [
            'null'       => [null],
            'true'       => [true],
            'false'      => [false],
            'zero'       => [0],
            'int'        => [1],
            'zero-float' => [0.0],
            'float'      => [1.1],
            'array'      => [['Middleware']],
            'object'     => [(object) ['call' => 'Middleware']],
        ];
    }

    /**
     * @group piping
     * @dataProvider uncallableMiddleware
     */
    public function testRaisesExceptionForNonCallableNonServiceMiddleware($middleware)
    {
        $config = [
            'middleware_pipeline' => [
                ['middleware' => $middleware],
                ['path' => '/foo', 'middleware' => $middleware],
            ],
        ];

        $this->injectServiceInContainer($this->container, 'config', $config);

        try {
            $this->factory->__invoke($this->container->reveal());
            $this->fail('No exception raised when fetching non-callable non-service middleware');
        } catch (InvalidMiddlewareException $e) {
            // This is acceptable
            $this->assertInstanceOf(InvalidMiddlewareException::class, $e);
        } catch (InvalidArgumentException $e) {
            // This is acceptable
            $this->assertInstanceOf(InvalidArgumentException::class, $e);
        }
    }

    /**
     * @group piping
     */
    public function testRaisesExceptionForPipedMiddlewareServiceNamesNotFoundInContainer()
    {
        $config = [
            'middleware_pipeline' => [
                ['middleware' => 'Middleware'],
                ['path' => '/foo', 'middleware' => 'Middleware'],
            ],
        ];

        $this->injectServiceInContainer($this->container, 'config', $config);

        $this->expectException(InvalidMiddlewareException::class);
        $app = $this->factory->__invoke($this->container->reveal());
    }

    /**
     * @group piping
     */
    public function testRaisesExceptionOnInvocationOfUninvokableServiceSpecifiedMiddlewarePulledFromContainer()
    {
        $middleware = (object) [];

        $config = [
            'middleware_pipeline' => [
                ['middleware' => 'Middleware'],
                ['path' => '/foo', 'middleware' => 'Middleware'],
            ],
        ];

        $this->injectServiceInContainer($this->container, 'config', $config);
        $this->injectServiceInContainer($this->container, 'Middleware', $middleware);

        $app = $this->factory->__invoke($this->container->reveal());

        $r = new ReflectionProperty($app, 'pipeline');
        $r->setAccessible(true);
        $pipeline = $r->getValue($app);

        $this->assertCount(2, $pipeline);

        $first = $pipeline->dequeue();
        $this->assertInstanceOf(StratigilityRoute::class, $first);
        $this->assertInstanceOf(LazyLoadingMiddleware::class, $first->handler);
        $this->assertEquals('/', $first->path);

        $second = $pipeline->dequeue();
        $this->assertInstanceOf(StratigilityRoute::class, $second);
        $this->assertInstanceOf(LazyLoadingMiddleware::class, $second->handler);
        $this->assertEquals('/foo', $second->path);

        $request = $this->prophesize(ServerRequestInterface::class)->reveal();
        $delegate = $this->prophesize(DelegateInterface::class)->reveal();

        foreach (['first' => $first->handler, 'second' => $second->handler] as $index => $handler) {
            try {
                $handler->process($request, $delegate);
                $this->fail(sprintf('%s handler succeed, but should have raised an exception', $index));
            } catch (\Exception $e) {
                $this->assertInstanceOf(InvalidMiddlewareException::class, $e);
                $this->assertContains('Lazy-loaded', $e->getMessage());
            }
        }
    }

    public function testCanSpecifyRouteViaConfigurationWithNoMethods()
    {
        $config = [
            'routes' => [
                [
                    'path' => '/',
                    'middleware' => 'HelloWorld',
                ],
            ],
        ];

        $this->injectServiceInContainer($this->container, 'config', $config);

        $app = $this->factory->__invoke($this->container->reveal());

        $routes = $app->getRoutes();

        foreach ($config['routes'] as $route) {
            $this->assertRoute($route, $routes);
        }
    }

    public function testCanSpecifyRouteOptionsViaConfiguration()
    {
        $expected = [
            'values' => [
                'foo' => 'bar'
            ],
            'tokens' => [
                'bar' => 'foo'
            ]
        ];
        $config = [
            'routes' => [
                [
                    'path' => '/',
                    'middleware' => 'HelloWorld',
                    'name' => 'home',
                    'allowed_methods' => ['GET'],
                    'options' => $expected
                ],
            ],
        ];

        $this->injectServiceInContainer($this->container, 'config', $config);

        $app = $this->factory->__invoke($this->container->reveal());

        $routes = $app->getRoutes();
        $route  = array_shift($routes);

        $this->assertInstanceOf(Route::class, $route);
        $this->assertEquals($expected, $route->getOptions());
    }

    public function testExceptionIsRaisedInCaseOfInvalidRouteMethodsConfiguration()
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

        $this->injectServiceInContainer($this->container, 'config', $config);

        $this->expectException(ExpressiveException\InvalidArgumentException::class);
        $this->expectExceptionMessage('route must be in form of an array; received "string"');
        $this->factory->__invoke($this->container->reveal());
    }

    public function testExceptionIsRaisedInCaseOfInvalidRouteOptionsConfiguration()
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

        $this->injectServiceInContainer($this->container, 'config', $config);

        $this->expectException(ExpressiveException\InvalidArgumentException::class);
        $this->expectExceptionMessage('options must be an array; received "string"');
        $this->factory->__invoke($this->container->reveal());
    }

    public function testWillCreatePipelineBasedOnMiddlewareConfiguration()
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
            ['middleware' => [
                $pipelineFirst,
                'Hello',
                $pipelineLast,
            ]],
        ];

        $config = ['middleware_pipeline' => $pipeline];
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
        // @codingStandardsIgnoreStart
        $middleware = function ($request, $response, $next) {};
        // @codingStandardsIgnoreEnd

        $routes = [
            [
                'path' => '/',
                'middleware' => clone $middleware,
                'allowed_methods' => ['GET'],
            ],
        ];

        return [
            'no-pipeline-defined' => [['routes' => $routes]],
            'empty-pipeline' => [['middleware_pipeline' => [], 'routes' => $routes]],
            'null-pipeline' => [['middleware_pipeline' => null, 'routes' => $routes]],
        ];
    }

    /**
     * @dataProvider configWithRoutesButNoPipeline
     */
    public function testProvidingRoutesAndNoPipelineImplicitlyRegistersRoutingAndDispatchMiddleware($config)
    {
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

    public function testPipelineContainingRoutingMiddlewareConstantPipesRoutingMiddleware()
    {
        $config = [
            'middleware_pipeline' => [
                ApplicationFactory::ROUTING_MIDDLEWARE,
            ],
        ];
        $this->injectServiceInContainer($this->container, 'config', $config);

        $app = $this->factory->__invoke($this->container->reveal());
        $this->assertAttributeSame(true, 'routeMiddlewareIsRegistered', $app);
    }

    public function testPipelineContainingDispatchMiddlewareConstantPipesDispatchMiddleware()
    {
        $config = [
            'middleware_pipeline' => [
                ApplicationFactory::DISPATCH_MIDDLEWARE,
            ],
        ];
        $this->injectServiceInContainer($this->container, 'config', $config);

        $app = $this->factory->__invoke($this->container->reveal());
        $this->assertAttributeSame(true, 'dispatchMiddlewareIsRegistered', $app);
    }

    public function testFactoryHonorsPriorityOrderWhenAttachingMiddleware()
    {
        $middleware = new InteropMiddleware();

        $pipeline1 = [['middleware' => clone $middleware, 'priority' => 1]];
        $pipeline2 = [['middleware' => clone $middleware, 'priority' => 100]];
        $pipeline3 = [['middleware' => clone $middleware, 'priority' => -100]];

        $pipeline = array_merge($pipeline3, $pipeline1, $pipeline2);
        $config = ['middleware_pipeline' => $pipeline];
        $this->injectServiceInContainer($this->container, 'config', $config);

        $app = $this->factory->__invoke($this->container->reveal());

        $r = new ReflectionProperty($app, 'pipeline');
        $r->setAccessible(true);
        $pipeline = $r->getValue($app);

        $this->assertSame($pipeline2[0]['middleware'], $pipeline->dequeue()->handler);
        $this->assertSame($pipeline1[0]['middleware'], $pipeline->dequeue()->handler);
        $this->assertSame($pipeline3[0]['middleware'], $pipeline->dequeue()->handler);
    }

    public function testMiddlewareWithoutPriorityIsGivenDefaultPriorityAndRegisteredInOrderReceived()
    {
        $middleware = new InteropMiddleware();

        $pipeline1 = [['middleware' => clone $middleware]];
        $pipeline2 = [['middleware' => clone $middleware]];
        $pipeline3 = [['middleware' => clone $middleware]];

        $pipeline = array_merge($pipeline3, $pipeline1, $pipeline2);
        $config = ['middleware_pipeline' => $pipeline];
        $this->injectServiceInContainer($this->container, 'config', $config);

        $app = $this->factory->__invoke($this->container->reveal());

        $r = new ReflectionProperty($app, 'pipeline');
        $r->setAccessible(true);
        $pipeline = $r->getValue($app);

        $this->assertSame($pipeline3[0]['middleware'], $pipeline->dequeue()->handler);
        $this->assertSame($pipeline1[0]['middleware'], $pipeline->dequeue()->handler);
        $this->assertSame($pipeline2[0]['middleware'], $pipeline->dequeue()->handler);
    }

    public function testRoutingAndDispatchMiddlewareUseDefaultPriority()
    {
        $middleware = new InteropMiddleware();

        $pipeline = [
            ['middleware' => clone $middleware, 'priority' => -100],
            ApplicationFactory::ROUTING_MIDDLEWARE,
            ['middleware' => clone $middleware, 'priority' => 1],
            ['middleware' => clone $middleware],
            ApplicationFactory::DISPATCH_MIDDLEWARE,
            ['middleware' => clone $middleware, 'priority' => 100],
        ];

        $config = ['middleware_pipeline' => $pipeline];
        $this->injectServiceInContainer($this->container, 'config', $config);

        $app = $this->factory->__invoke($this->container->reveal());

        $r = new ReflectionProperty($app, 'pipeline');
        $r->setAccessible(true);
        $test = $r->getValue($app);

        $this->assertSame($pipeline[5]['middleware'], $test->dequeue()->handler);
        $this->assertInstanceOf(RouteMiddleware::class, $test->dequeue()->handler);
        $this->assertSame($pipeline[2]['middleware'], $test->dequeue()->handler);
        $this->assertSame($pipeline[3]['middleware'], $test->dequeue()->handler);
        $this->assertInstanceOf(DispatchMiddleware::class, $test->dequeue()->handler);
        $this->assertSame($pipeline[0]['middleware'], $test->dequeue()->handler);
    }

    public function specMiddlewareContainingRoutingAndOrDispatchMiddleware()
    {
        // @codingStandardsIgnoreStart
        return [
            'routing-only'              => [[['middleware' => [ApplicationFactory::ROUTING_MIDDLEWARE]]]],
            'dispatch-only'             => [[['middleware' => [ApplicationFactory::DISPATCH_MIDDLEWARE]]]],
            'both-routing-and-dispatch' => [[['middleware' => [ApplicationFactory::ROUTING_MIDDLEWARE, ApplicationFactory::DISPATCH_MIDDLEWARE]]]],
        ];
        // @codingStandardsIgnoreEnd
    }

    /**
     * @dataProvider specMiddlewareContainingRoutingAndOrDispatchMiddleware
     */
    public function testRoutingAndDispatchMiddlewareCanBeComposedWithinArrayStandardSpecification($pipeline)
    {
        $expected = $pipeline[0]['middleware'];
        $config = ['middleware_pipeline' => $pipeline];
        $this->injectServiceInContainer($this->container, 'config', $config);

        $app = $this->factory->__invoke($this->container->reveal());

        $r = new ReflectionProperty($app, 'pipeline');
        $r->setAccessible(true);
        $appPipeline = $r->getValue($app);

        $this->assertEquals(1, count($appPipeline));

        $innerMiddleware = $appPipeline->dequeue()->handler;
        $this->assertInstanceOf(MiddlewarePipe::class, $innerMiddleware);

        $r = new ReflectionProperty($innerMiddleware, 'pipeline');
        $r->setAccessible(true);
        $innerPipeline = $r->getValue($innerMiddleware);
        $this->assertInstanceOf(SplQueue::class, $innerPipeline);

        $this->assertEquals(
            count($expected),
            $innerPipeline->count(),
            sprintf('Expected %d items in pipeline; received %d', count($expected), $innerPipeline->count())
        );

        foreach ($innerPipeline as $index => $route) {
            $innerPipeline[$index] = $route->handler;
        }

        foreach ($expected as $type) {
            switch ($type) {
                case ApplicationFactory::ROUTING_MIDDLEWARE:
                    $middleware = RouteMiddleware::class;
                    $message    = 'RouteMiddleware not found in pipeline';
                    break;
                case ApplicationFactory::DISPATCH_MIDDLEWARE:
                    $middleware = DispatchMiddleware::class;
                    $message    = 'DispatchMiddleware not found in pipeline';
                    break;
                default:
                    $this->fail('Unexpected value in pipeline passed from data provider');
            }
            $this->assertPipelineContainsInstanceOf($middleware, $innerPipeline, $message);
        }
    }

    /**
     * @group programmatic
     */
    public function testWillNotInjectConfiguredRoutesOrPipelineIfProgrammaticPipelineFlagEnabled()
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
                ['middleware' => [
                    $pipelineFirst,
                    'Hello',
                    $pipelineLast,
                ]],
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
