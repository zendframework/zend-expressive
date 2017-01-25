<?php
/**
 * @see       https://github.com/zendframework/zend-expressive for the canonical source repository
 * @copyright Copyright (c) 2016 Zend Technologies USA Inc. (http://www.zend.com)
 * @license   https://github.com/zendframework/zend-expressive/blob/master/LICENSE.md New BSD License
 */

namespace ZendTest\Expressive\Application;

use Closure;
use Interop\Http\ServerMiddleware\MiddlewareInterface as ServerMiddlewareInterface;
use PHPUnit\Framework\Assert;
use PHPUnit\Framework\TestCase;
use ReflectionFunction;
use ReflectionProperty;
use SplQueue;
use Zend\Expressive\Application;
use Zend\Expressive\Container\ApplicationFactory;
use Zend\Expressive\Middleware;
use Zend\Expressive\Router\RouterInterface;
use Zend\Stratigility\MiddlewarePipe;
use ZendTest\Expressive\ContainerTrait;

/**
 * Tests the functionality present in the ApplicationConfigInjectionTrait.
 */
class ConfigInjectionTest extends TestCase
{
    use ContainerTrait;

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

        $app = $this->createApplication();

        $app->injectRoutesFromConfig($config);

        $r = new ReflectionProperty($app, 'routes');
        $r->setAccessible(true);
        $routes = $r->getValue($app);

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

        $app->injectPipelineFromConfig($config);

        $r = new ReflectionProperty($app, 'routes');
        $r->setAccessible(true);
        $routes = $r->getValue($app);
        $this->assertEquals(0, count($routes));
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

    public function testFactoryHonorsPriorityOrderWhenAttachingMiddleware()
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
     */
    public function testRoutingAndDispatchMiddlewareCanBeComposedWithinArrayStandardSpecification($pipeline)
    {
        $expected = $pipeline[0]['middleware'];
        $config = ['middleware_pipeline' => $pipeline];

        $app = $this->createApplication();

        $app->injectPipelineFromConfig($config);

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
}