<?php
/**
 * @see       https://github.com/zendframework/zend-expressive for the canonical source repository
 * @copyright Copyright (c) 2016-2018 Zend Technologies USA Inc. (https://www.zend.com)
 * @license   https://github.com/zendframework/zend-expressive/blob/master/LICENSE.md New BSD License
 */

declare(strict_types=1);

namespace ZendTest\Expressive\Container;

use PHPUnit\Framework\Assert;
use PHPUnit\Framework\TestCase;
use Prophecy\Prophecy\ObjectProphecy;
use Psr\Container\ContainerInterface;
use Psr\Http\Server\MiddlewareInterface;
use ReflectionProperty;
use Zend\Diactoros\Response;
use Zend\Expressive\Application;
use Zend\Expressive\Container\ApplicationConfigInjectionDelegator;
use Zend\Expressive\Container\Exception\InvalidServiceException;
use Zend\Expressive\Exception\InvalidArgumentException;
use Zend\Expressive\Middleware;
use Zend\Expressive\MiddlewareContainer;
use Zend\Expressive\MiddlewareFactory;
use Zend\Expressive\Router\Middleware\DispatchMiddleware;
use Zend\Expressive\Router\Middleware\MethodNotAllowedMiddleware;
use Zend\Expressive\Router\Middleware\PathBasedRoutingMiddleware;
use Zend\Expressive\Router\Route;
use Zend\Expressive\Router\RouterInterface;
use Zend\HttpHandlerRunner\RequestHandlerRunner;
use Zend\Stratigility\MiddlewarePipe;
use ZendTest\Expressive\ContainerTrait;
use ZendTest\Expressive\TestAsset\InvokableMiddleware;

class ApplicationConfigInjectionDelegatorTest extends TestCase
{
    use ContainerTrait;

    /** @var ContainerInterface|ObjectProphecy */
    private $container;

    /** @var DispatchMiddleware|ObjectProphecy */
    private $dispatchMiddleware;

    /** @var MethodNotAllowedMiddleware|ObjectProphecy */
    private $methodNotAllowedMiddleware;

    /** @var PathBasedRoutingMiddleware */
    private $routeMiddleware;

    /** @var RouterInterface|ObjectProphecy */
    private $router;

    public function setUp()
    {
        $this->container = $this->mockContainerInterface();
        $this->router = $this->prophesize(RouterInterface::class);
        $this->routeMiddleware = new PathBasedRoutingMiddleware(
            $this->router->reveal(),
            new Response()
        );
        $this->dispatchMiddleware = $this->prophesize(DispatchMiddleware::class)->reveal();
        $this->methodNotAllowedMiddleware = $this->prophesize(MethodNotAllowedMiddleware::class)->reveal();
    }

    public function createApplication()
    {
        $container = new MiddlewareContainer($this->container->reveal());
        $factory = new MiddlewareFactory($container);
        $pipeline = new MiddlewarePipe();
        $runner = $this->prophesize(RequestHandlerRunner::class)->reveal();
        return new Application(
            $factory,
            $pipeline,
            $this->routeMiddleware,
            $runner
        );
    }

    public function getQueueFromApplicationPipeline(Application $app)
    {
        $r = new ReflectionProperty($app, 'pipeline');
        $r->setAccessible(true);
        $pipeline = $r->getValue($app);

        $r = new ReflectionProperty($pipeline, 'pipeline');
        $r->setAccessible(true);
        return $r->getValue($pipeline);
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

    public static function assertRouteMiddleware(MiddlewareInterface $middleware)
    {
        if ($middleware instanceof PathBasedRoutingMiddleware) {
            Assert::assertInstanceOf(PathBasedRoutingMiddleware::class, $middleware);
            return;
        }

        if (! $middleware instanceof Middleware\LazyLoadingMiddleware) {
            Assert::fail('Middleware is not an instance of PathBasedRoutingMiddleware');
        }

        Assert::assertAttributeSame(
            PathBasedRoutingMiddleware::class,
            'middlewareName',
            $middleware,
            'Middleware is not an instance of PathBasedRoutingMiddleware'
        );
    }

    public static function assertDispatchMiddleware(MiddlewareInterface $middleware)
    {
        if ($middleware instanceof DispatchMiddleware) {
            Assert::assertInstanceOf(DispatchMiddleware::class, $middleware);
            return;
        }

        if (! $middleware instanceof Middleware\LazyLoadingMiddleware) {
            Assert::fail('Middleware is not an instance of DispatchMiddleware');
        }

        Assert::assertAttributeSame(
            DispatchMiddleware::class,
            'middlewareName',
            $middleware,
            'Middleware is not an instance of DispatchMiddleware'
        );
    }

    public static function assertMethodNotAllowedMiddleware(MiddlewareInterface $middleware)
    {
        if ($middleware instanceof MethodNotAllowedMiddleware) {
            Assert::assertInstanceOf(MethodNotAllowedMiddleware::class, $middleware);
            return;
        }

        if (! $middleware instanceof Middleware\LazyLoadingMiddleware) {
            Assert::fail('Middleware is not an instance of MethodNotAllowedMiddleware');
        }

        Assert::assertAttributeSame(
            MethodNotAllowedMiddleware::class,
            'middlewareName',
            $middleware,
            'Middleware is not an instance of MethodNotAllowedMiddleware'
        );
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

    public function testInvocationAsDelegatorFactoryRaisesExceptionIfCallbackIsNotAnApplication()
    {
        $container = $this->prophesize(ContainerInterface::class)->reveal();
        $callback = function () {
            return $this;
        };
        $factory = new ApplicationConfigInjectionDelegator();
        $this->expectException(InvalidServiceException::class);
        $this->expectExceptionMessage('cannot operate');
        $factory($container, Application::class, $callback);
    }

    /**
     * @dataProvider callableMiddlewares
     *
     * @param callable|array|string $middleware
     */
    public function testInjectRoutesFromConfigSetsUpRoutesFromConfig($middleware)
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

        $app = $this->createApplication();

        ApplicationConfigInjectionDelegator::injectRoutesFromConfig($app, $config);

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

        ApplicationConfigInjectionDelegator::injectRoutesFromConfig($app, $config);

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
        $this->injectServiceInContainer(
            $this->container,
            PathBasedRoutingMiddleware::class,
            $this->routeMiddleware
        );
        $this->injectServiceInContainer(
            $this->container,
            DispatchMiddleware::class,
            $this->dispatchMiddleware
        );
        $app = $this->createApplication();

        ApplicationConfigInjectionDelegator::injectPipelineFromConfig($app, $config);

        $queue = $this->getQueueFromApplicationPipeline($app);

        $this->assertCount(3, $queue, 'Did not get expected pipeline count!');

        $test = $queue->dequeue();
        $this->assertRouteMiddleware($test);

        $test = $queue->dequeue();
        $this->assertMethodNotAllowedMiddleware($test);

        $test = $queue->dequeue();
        $this->assertDispatchMiddleware($test);
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

        ApplicationConfigInjectionDelegator::injectPipelineFromConfig($app, $config);

        $pipeline = $this->getQueueFromApplicationPipeline($app);

        $this->assertSame($pipeline2[0]['middleware'], $pipeline->dequeue());
        $this->assertSame($pipeline1[0]['middleware'], $pipeline->dequeue());
        $this->assertSame($pipeline3[0]['middleware'], $pipeline->dequeue());
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

        ApplicationConfigInjectionDelegator::injectPipelineFromConfig($app, $config);

        $pipeline = $this->getQueueFromApplicationPipeline($app);

        $this->assertSame($pipeline3[0]['middleware'], $pipeline->dequeue());
        $this->assertSame($pipeline1[0]['middleware'], $pipeline->dequeue());
        $this->assertSame($pipeline2[0]['middleware'], $pipeline->dequeue());
    }

    public function testInjectPipelineFromConfigWithEmptyConfigDoesNothing()
    {
        $app = $this->createApplication();
        ApplicationConfigInjectionDelegator::injectPipelineFromConfig($app, []);
        $pipeline = $this->getQueueFromApplicationPipeline($app);
        $this->assertEquals(0, $pipeline->count());
    }

    public function testInjectRoutesFromConfigWithEmptyConfigDoesNothing()
    {
        $app = $this->createApplication();
        ApplicationConfigInjectionDelegator::injectRoutesFromConfig($app, []);
        $this->assertEquals([], $app->getRoutes());
        $pipeline = $this->getQueueFromApplicationPipeline($app);
        $this->assertEquals(0, $pipeline->count());
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
        ApplicationConfigInjectionDelegator::injectRoutesFromConfig($app, $config);
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
        ApplicationConfigInjectionDelegator::injectRoutesFromConfig($app, $config);
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

        ApplicationConfigInjectionDelegator::injectRoutesFromConfig($app, $config);

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
        $this->injectServiceInContainer(
            $this->container,
            PathBasedRoutingMiddleware::class,
            $this->routeMiddleware
        );
        $this->injectServiceInContainer(
            $this->container,
            DispatchMiddleware::class,
            $this->dispatchMiddleware
        );

        $app = $this->createApplication();

        ApplicationConfigInjectionDelegator::injectPipelineFromConfig($app, $config);
        $this->assertEquals([], $app->getRoutes());
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
        $this->injectServiceInContainer(
            $this->container,
            PathBasedRoutingMiddleware::class,
            $this->routeMiddleware
        );
        $this->injectServiceInContainer(
            $this->container,
            DispatchMiddleware::class,
            $this->dispatchMiddleware
        );

        $app = $this->createApplication();

        ApplicationConfigInjectionDelegator::injectPipelineFromConfig($app, $config);
        $this->assertEquals([], $app->getRoutes());
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
        ApplicationConfigInjectionDelegator::injectPipelineFromConfig($app, $config);
    }
}
