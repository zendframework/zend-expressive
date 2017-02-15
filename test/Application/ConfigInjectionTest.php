<?php
/**
 * @see       https://github.com/zendframework/zend-expressive for the canonical source repository
 * @copyright Copyright (c) 2016-2017 Zend Technologies USA Inc. (http://www.zend.com)
 * @license   https://github.com/zendframework/zend-expressive/blob/master/LICENSE.md New BSD License
 */

namespace ZendTest\Expressive\Application;

use PHPUnit\Framework\Assert;
use PHPUnit\Framework\TestCase;
use Prophecy\Prophecy\ObjectProphecy;
use Psr\Container\ContainerInterface;
use ReflectionProperty;
use SplQueue;
use Zend\Expressive\Application;
use Zend\Expressive\Exception\InvalidArgumentException;
use Zend\Expressive\Middleware;
use Zend\Expressive\Router\Route;
use Zend\Expressive\Router\RouterInterface;
use Zend\Stratigility\MiddlewarePipe;
use ZendTest\Expressive\ContainerTrait;
use ZendTest\Expressive\TestAsset\InvokableMiddleware;

/**
 * Tests the functionality present in the ApplicationConfigInjectionTrait.
 */
class ConfigInjectionTest extends TestCase
{
    use ContainerTrait;

    /** @var ContainerInterface|ObjectProphecy */
    private $container;

    /** @var RouterInterface|ObjectProphecy */
    private $router;

    public function setUp()
    {
        $this->container = $this->mockContainerInterface();
        $this->router = $this->prophesize(RouterInterface::class);
    }

    public function createApplication()
    {
        return new Application($this->router->reveal(), $this->container->reveal());
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

                if (isset($spec['allowed_methods'])
                    && $route->getAllowedMethods() !== $spec['allowed_methods']
                ) {
                    return false;
                }

                if (! isset($spec['allowed_methods'])
                    && $route->getAllowedMethods() !== Route::HTTP_METHOD_ANY
                ) {
                    return false;
                }

                return true;
            }, false),
            Assert::isTrue(),
            'Route created does not match any specifications'
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

    public function callableMiddlewares()
    {
        return [
            ['HelloWorld'],
            [
                function () {
                },
            ],
            [[InvokableMiddleware::class, 'staticallyCallableMiddleware']],
        ];
    }

    /**
     * @dataProvider callableMiddlewares
     *
     * @param callable|array|string $middleware
     */
    public function testInjectRoutesFromConfigSetsUpRoutesFromConfig($middleware)
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

        $app = $this->createApplication();

        $app->injectRoutesFromConfig($config);

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

        $app = $this->createApplication();

        $app->injectRoutesFromConfig($config);

        $routes = $app->getRoutes();
        $this->assertCount(0, $routes);
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

        return [
            'no-pipeline-defined' => [['routes' => $routes]],
            'empty-pipeline' => [['middleware_pipeline' => [], 'routes' => $routes]],
            'null-pipeline' => [['middleware_pipeline' => null, 'routes' => $routes]],
        ];
    }

    /**
     * @dataProvider configWithRoutesButNoPipeline
     *
     * @param array $config
     */
    public function testProvidingRoutesAndNoPipelineImplicitlyRegistersRoutingAndDispatchMiddleware(array $config)
    {
        $this->injectServiceInContainer($this->container, RouterInterface::class, $this->router->reveal());
        $app = $this->createApplication();

        $app->injectPipelineFromConfig($config);

        $this->assertAttributeSame(true, 'routeMiddlewareIsRegistered', $app);
        $this->assertAttributeSame(true, 'dispatchMiddlewareIsRegistered', $app);

        $r = new ReflectionProperty($app, 'pipeline');
        $r->setAccessible(true);
        $pipeline = $r->getValue($app);

        $this->assertCount(2, $pipeline, 'Did not get expected pipeline count!');

        $test = $pipeline->dequeue();
        $this->assertEquals('/', $test->path);
        $this->assertInstanceOf(Middleware\RouteMiddleware::class, $test->handler);

        $test = $pipeline->dequeue();
        $this->assertEquals('/', $test->path);
        $this->assertInstanceOf(Middleware\DispatchMiddleware::class, $test->handler);
    }

    public function testPipelineContainingRoutingMiddlewareConstantPipesRoutingMiddleware()
    {
        $config = [
            'middleware_pipeline' => [
                Application::ROUTING_MIDDLEWARE,
            ],
        ];
        $app = $this->createApplication();

        $app->injectPipelineFromConfig($config);

        $this->assertAttributeSame(true, 'routeMiddlewareIsRegistered', $app);
    }

    public function testPipelineContainingDispatchMiddlewareConstantPipesDispatchMiddleware()
    {
        $config = [
            'middleware_pipeline' => [
                Application::DISPATCH_MIDDLEWARE,
            ],
        ];
        $app = $this->createApplication();

        $app->injectPipelineFromConfig($config);

        $this->assertAttributeSame(true, 'dispatchMiddlewareIsRegistered', $app);
    }

    public function testInjectPipelineFromConfigHonorsPriorityOrderWhenAttachingMiddleware()
    {
        $middleware = new TestAsset\InteropMiddleware();

        $pipeline1 = [['middleware' => clone $middleware, 'priority' => 1]];
        $pipeline2 = [['middleware' => clone $middleware, 'priority' => 100]];
        $pipeline3 = [['middleware' => clone $middleware, 'priority' => -100]];

        $pipeline = array_merge($pipeline3, $pipeline1, $pipeline2);
        $config = ['middleware_pipeline' => $pipeline];

        $app = $this->createApplication();

        $app->injectPipelineFromConfig($config);

        $r = new ReflectionProperty($app, 'pipeline');
        $r->setAccessible(true);
        $pipeline = $r->getValue($app);

        $this->assertSame($pipeline2[0]['middleware'], $pipeline->dequeue()->handler);
        $this->assertSame($pipeline1[0]['middleware'], $pipeline->dequeue()->handler);
        $this->assertSame($pipeline3[0]['middleware'], $pipeline->dequeue()->handler);
    }

    public function testMiddlewareWithoutPriorityIsGivenDefaultPriorityAndRegisteredInOrderReceived()
    {
        $middleware = new TestAsset\InteropMiddleware();

        $pipeline1 = [['middleware' => clone $middleware]];
        $pipeline2 = [['middleware' => clone $middleware]];
        $pipeline3 = [['middleware' => clone $middleware]];

        $pipeline = array_merge($pipeline3, $pipeline1, $pipeline2);
        $config = ['middleware_pipeline' => $pipeline];

        $app = $this->createApplication();

        $app->injectPipelineFromConfig($config);

        $r = new ReflectionProperty($app, 'pipeline');
        $r->setAccessible(true);
        $pipeline = $r->getValue($app);

        $this->assertSame($pipeline3[0]['middleware'], $pipeline->dequeue()->handler);
        $this->assertSame($pipeline1[0]['middleware'], $pipeline->dequeue()->handler);
        $this->assertSame($pipeline2[0]['middleware'], $pipeline->dequeue()->handler);
    }

    public function testRoutingAndDispatchMiddlewareUseDefaultPriority()
    {
        $middleware = new TestAsset\InteropMiddleware();

        $pipeline = [
            ['middleware' => clone $middleware, 'priority' => -100],
            Application::ROUTING_MIDDLEWARE,
            ['middleware' => clone $middleware, 'priority' => 1],
            ['middleware' => clone $middleware],
            Application::DISPATCH_MIDDLEWARE,
            ['middleware' => clone $middleware, 'priority' => 100],
        ];

        $config = ['middleware_pipeline' => $pipeline];

        $app = $this->createApplication();

        $app->injectPipelineFromConfig($config);

        $r = new ReflectionProperty($app, 'pipeline');
        $r->setAccessible(true);
        $test = $r->getValue($app);

        $this->assertSame($pipeline[5]['middleware'], $test->dequeue()->handler);
        $this->assertInstanceOf(Middleware\RouteMiddleware::class, $test->dequeue()->handler);
        $this->assertSame($pipeline[2]['middleware'], $test->dequeue()->handler);
        $this->assertSame($pipeline[3]['middleware'], $test->dequeue()->handler);
        $this->assertInstanceOf(Middleware\DispatchMiddleware::class, $test->dequeue()->handler);
        $this->assertSame($pipeline[0]['middleware'], $test->dequeue()->handler);
    }

    public function specMiddlewareContainingRoutingAndOrDispatchMiddleware()
    {
        // @codingStandardsIgnoreStart
        return [
            'routing-only'              => [[['middleware' => [Application::ROUTING_MIDDLEWARE]]]],
            'dispatch-only'             => [[['middleware' => [Application::DISPATCH_MIDDLEWARE]]]],
            'both-routing-and-dispatch' => [[['middleware' => [Application::ROUTING_MIDDLEWARE, Application::DISPATCH_MIDDLEWARE]]]],
        ];
        // @codingStandardsIgnoreEnd
    }

    /**
     * @dataProvider specMiddlewareContainingRoutingAndOrDispatchMiddleware
     *
     * @param array $pipeline
     */
    public function testRoutingAndDispatchMiddlewareCanBeComposedWithinArrayStandardSpecification(array $pipeline)
    {
        $expected = $pipeline[0]['middleware'];
        $config = ['middleware_pipeline' => $pipeline];

        $app = $this->createApplication();

        $app->injectPipelineFromConfig($config);

        $r = new ReflectionProperty($app, 'pipeline');
        $r->setAccessible(true);
        $appPipeline = $r->getValue($app);

        $this->assertCount(1, $appPipeline);

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
                case Application::ROUTING_MIDDLEWARE:
                    $middleware = Middleware\RouteMiddleware::class;
                    $message = 'Did not find routing middleware in pipeline';
                    break;
                case Application::DISPATCH_MIDDLEWARE:
                    $middleware = Middleware\DispatchMiddleware::class;
                    $message = 'Did not find dispatch middleware in pipeline';
                    break;
                default:
                    $this->fail('Unexpected value in pipeline passed from data provider');
            }
            $this->assertPipelineContainsInstanceOf($middleware, $innerPipeline, $message);
        }
    }

    public function testInjectPipelineFromConfigWithEmptyConfigAndNoConfigServiceDoesNothing()
    {
        $this->container->has('config')->willReturn(false);
        $app = $this->createApplication();

        $app->injectPipelineFromConfig();

        $r = new ReflectionProperty($app, 'pipeline');
        $r->setAccessible(true);
        $pipeline = $r->getValue($app);
        $this->assertInstanceOf(SplQueue::class, $pipeline);

        $this->assertEquals(0, $pipeline->count());
    }

    public function testInjectRoutesFromConfigWithEmptyConfigAndNoConfigServiceDoesNothing()
    {
        $this->container->has('config')->willReturn(false);
        $app = $this->createApplication();

        $app->injectRoutesFromConfig();
        $this->assertAttributeEquals([], 'routes', $app);
    }

    public function testInjectRoutesFromConfigRaisesExceptionIfAllowedMethodsIsInvalid()
    {
        $config = [
            'routes' => [
                [
                    'path' => '/',
                    'middleware' => new TestAsset\InteropMiddleware(),
                    'allowed_methods' => 'not-valid',
                ],
            ],
        ];
        $this->container->has('config')->willReturn(false);
        $app = $this->createApplication();

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Allowed HTTP methods');
        $app->injectRoutesFromConfig($config);
    }

    public function testInjectRoutesFromConfigRaisesExceptionIfOptionsIsNotAnArray()
    {
        $config = [
            'routes' => [
                [
                    'path' => '/',
                    'middleware' => new TestAsset\InteropMiddleware(),
                    'allowed_methods' => ['GET'],
                    'options' => 'invalid',
                ],
            ],
        ];
        $this->container->has('config')->willReturn(false);
        $app = $this->createApplication();

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Route options must be an array');
        $app->injectRoutesFromConfig($config);
    }

    public function testInjectRoutesFromConfigCanProvideRouteOptions()
    {
        $config = [
            'routes' => [
                [
                    'path' => '/',
                    'middleware' => new TestAsset\InteropMiddleware(),
                    'allowed_methods' => ['GET'],
                    'options' => [
                        'foo' => 'bar',
                    ],
                ],
            ],
        ];
        $this->container->has('config')->willReturn(false);
        $app = $this->createApplication();

        $app->injectRoutesFromConfig($config);

        $routes = $app->getRoutes();

        $route = array_shift($routes);
        $this->assertEquals($config['routes'][0]['options'], $route->getOptions());
    }

    public function testInjectRoutesFromConfigWillSkipSpecsThatOmitPath()
    {
        $config = [
            'routes' => [
                [
                    'middleware' => new TestAsset\InteropMiddleware(),
                    'allowed_methods' => ['GET'],
                    'options' => [
                        'foo' => 'bar',
                    ],
                ],
            ],
        ];
        $this->container->has('config')->willReturn(false);
        $app = $this->createApplication();

        $app->injectPipelineFromConfig($config);
        $this->assertAttributeEquals([], 'routes', $app);
    }

    public function testInjectRoutesFromConfigWillSkipSpecsThatOmitMiddleware()
    {
        $config = [
            'routes' => [
                [
                    'path' => '/',
                    'allowed_methods' => ['GET'],
                    'options' => [
                        'foo' => 'bar',
                    ],
                ],
            ],
        ];
        $this->container->has('config')->willReturn(false);
        $app = $this->createApplication();

        $app->injectPipelineFromConfig($config);
        $this->assertAttributeEquals([], 'routes', $app);
    }

    public function testInjectPipelineFromConfigRaisesExceptionForSpecsOmittingMiddlewareKey()
    {
        $config = [
            'middleware_pipeline' => [
                [
                    'this' => 'will not work',
                ],
            ],
        ];
        $app = $this->createApplication();

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid pipeline specification received');
        $app->injectPipelineFromConfig($config);
    }
}
