<?php
/**
 * @see       https://github.com/zendframework/zend-expressive for the canonical source repository
 * @copyright Copyright (c) 2015-2016 Zend Technologies USA Inc. (http://www.zend.com)
 * @license   https://github.com/zendframework/zend-expressive/blob/master/LICENSE.md New BSD License
 */

namespace ZendTest\Expressive;

use Closure;
use PHPUnit_Framework_TestCase as TestCase;
use ReflectionFunction;
use ReflectionProperty;
use SplQueue;
use Zend\Expressive\Application;
use Zend\Expressive\Container\ApplicationFactory;
use Zend\Expressive\ErrorMiddlewarePipe;
use Zend\Expressive\Router\RouterInterface;
use Zend\Stratigility\ErrorMiddlewareInterface;
use Zend\Stratigility\MiddlewarePipe;

/**
 * Tests the functionality present in the ApplicationConfigInjectionTrait.
 */
class ApplicationConfigInjectionTest extends TestCase
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

    public function assertRoute($spec, array $routes)
    {
        $this->assertTrue(array_reduce($routes, function ($found, $route) use ($spec) {
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
        }, false));
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
                    'allowed_methods' => [ 'GET' ],
                ],
                [
                    'path' => '/ping',
                    'middleware' => 'Ping',
                    'allowed_methods' => [ 'GET' ],
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
                    'allowed_methods' => [ 'GET' ],
                ],
                [
                    'allowed_methods' => [ 'POST' ],
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
                'allowed_methods' => [ 'GET' ],
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
        $this->assertSame([$app, 'routeMiddleware'], $test->handler);

        $test = $pipeline->dequeue();
        $this->assertEquals('/', $test->path);
        $this->assertSame([$app, 'dispatchMiddleware'], $test->handler);
    }

    public function testPipelineContainingRoutingMiddlewareConstantPipesRoutingMiddleware()
    {
        $config = [
            'middleware_pipeline' => [
                ApplicationFactory::ROUTING_MIDDLEWARE,
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
                ApplicationFactory::DISPATCH_MIDDLEWARE,
            ],
        ];
        $app = $this->createApplication();

        $app->injectPipelineFromConfig($config);

        $this->assertAttributeSame(true, 'dispatchMiddlewareIsRegistered', $app);
    }

    public function testFactoryHonorsPriorityOrderWhenAttachingMiddleware()
    {
        // @codingStandardsIgnoreStart
        $middleware = function ($request, $response, $next) {};
        // @codingStandardsIgnoreEnd

        $pipeline1 = [ [ 'middleware' => clone $middleware, 'priority' => 1 ] ];
        $pipeline2 = [ [ 'middleware' => clone $middleware, 'priority' => 100 ] ];
        $pipeline3 = [ [ 'middleware' => clone $middleware, 'priority' => -100 ] ];

        $pipeline = array_merge($pipeline3, $pipeline1, $pipeline2);
        $config = [ 'middleware_pipeline' => $pipeline ];

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
        // @codingStandardsIgnoreStart
        $middleware = function ($request, $response, $next) {};
        // @codingStandardsIgnoreEnd

        $pipeline1 = [ [ 'middleware' => clone $middleware ] ];
        $pipeline2 = [ [ 'middleware' => clone $middleware ] ];
        $pipeline3 = [ [ 'middleware' => clone $middleware ] ];

        $pipeline = array_merge($pipeline3, $pipeline1, $pipeline2);
        $config = [ 'middleware_pipeline' => $pipeline ];

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
        // @codingStandardsIgnoreStart
        $middleware = function ($request, $response, $next) {};
        // @codingStandardsIgnoreEnd

        $pipeline = [
            [ 'middleware' => clone $middleware, 'priority' => -100 ],
            ApplicationFactory::ROUTING_MIDDLEWARE,
            [ 'middleware' => clone $middleware, 'priority' => 1 ],
            [ 'middleware' => clone $middleware ],
            ApplicationFactory::DISPATCH_MIDDLEWARE,
            [ 'middleware' => clone $middleware, 'priority' => 100 ],
        ];

        $config = [ 'middleware_pipeline' => $pipeline ];

        $app = $this->createApplication();

        $app->injectPipelineFromConfig($config);

        $r = new ReflectionProperty($app, 'pipeline');
        $r->setAccessible(true);
        $test = $r->getValue($app);

        $this->assertSame($pipeline[5]['middleware'], $test->dequeue()->handler);
        $this->assertSame([ $app, 'routeMiddleware' ], $test->dequeue()->handler);
        $this->assertSame($pipeline[2]['middleware'], $test->dequeue()->handler);
        $this->assertSame($pipeline[3]['middleware'], $test->dequeue()->handler);
        $this->assertSame([ $app, 'dispatchMiddleware' ], $test->dequeue()->handler);
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
        $config = [ 'middleware_pipeline' => $pipeline ];

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
                case ApplicationFactory::ROUTING_MIDDLEWARE:
                    $middleware = [$app, 'routeMiddleware'];
                    break;
                case ApplicationFactory::DISPATCH_MIDDLEWARE:
                    $middleware = [$app, 'dispatchMiddleware'];
                    break;
                default:
                    $this->fail('Unexpected value in pipeline passed from data provider');
            }
            $this->assertContains($middleware, $innerPipeline);
        }
    }

    /**
     * @todo Remove for 2.0.0
     */
    public function testProperlyRegistersNestedErrorMiddlewareAsLazyErrorMiddleware()
    {
        $config = ['middleware_pipeline' => [
            'error' => [
                'middleware' => [
                    'FooError',
                ],
                'error' => true,
                'priority' => -10000,
            ],
        ]];

        $fooError = $this->prophesize(ErrorMiddlewareInterface::class)->reveal();
        $this->injectServiceInContainer($this->container, 'FooError', $fooError);

        $app = $this->createApplication();

        set_error_handler(function ($errno, $errmsg) {
            return false !== strstr($errmsg, 'error middleware is deprecated');
        }, E_USER_DEPRECATED);

        $app->injectPipelineFromConfig($config);

        restore_error_handler();

        $r = new ReflectionProperty($app, 'pipeline');
        $r->setAccessible(true);
        $pipeline = $r->getValue($app);

        $nestedPipeline = $pipeline->dequeue()->handler;

        $this->assertInstanceOf(ErrorMiddlewarePipe::class, $nestedPipeline);

        $r = new ReflectionProperty($nestedPipeline, 'pipeline');
        $r->setAccessible(true);
        $internalPipeline = $r->getValue($nestedPipeline);
        $this->assertInstanceOf(MiddlewarePipe::class, $internalPipeline);

        $r = new ReflectionProperty($internalPipeline, 'pipeline');
        $r->setAccessible(true);
        $middleware = $r->getValue($internalPipeline)->dequeue()->handler;

        $this->assertInstanceOf(Closure::class, $middleware);
        $r = new ReflectionFunction($middleware);
        $this->assertTrue($r->isClosure(), 'Configured middleware is not the expected lazy-middleware closure');
        $this->assertEquals(4, $r->getNumberOfParameters(), 'Configured middleware is not error middleware');
    }
}
