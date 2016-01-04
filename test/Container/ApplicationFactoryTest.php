<?php
/**
 * Zend Framework (http://framework.zend.com/)
 *
 * @see       https://github.com/zendframework/zend-expressive for the canonical source repository
 * @copyright Copyright (c) 2015-2016 Zend Technologies USA Inc. (http://www.zend.com)
 * @license   https://github.com/zendframework/zend-expressive/blob/master/LICENSE.md New BSD License
 */

namespace ZendTest\Expressive\Container;

use Closure;
use InvalidArgumentException;
use PHPUnit_Framework_TestCase as TestCase;
use Prophecy\Prophecy\ObjectProphecy;
use ReflectionFunction;
use ReflectionProperty;
use Zend\Diactoros\Response\EmitterInterface;
use Zend\Diactoros\Response\SapiEmitter;
use Zend\Expressive\Application;
use Zend\Expressive\Container\ApplicationFactory;
use Zend\Expressive\Container\Exception as ContainerException;
use Zend\Expressive\Emitter\EmitterStack;
use Zend\Expressive\Exception\InvalidMiddlewareException;
use Zend\Expressive\Router\FastRouteRouter;
use Zend\Expressive\Router\Route;
use Zend\Expressive\Router\RouterInterface;
use Zend\Stratigility\Route as StratigilityRoute;
use ZendTest\Expressive\ContainerTrait;
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
    protected $finalHandler;

    /** @var ObjectProphecy */
    protected $router;

    public function setUp()
    {
        $this->container = $this->mockContainerInterface();
        $this->factory   = new ApplicationFactory();

        $this->router = $this->prophesize(RouterInterface::class);
        $this->emitter = $this->prophesize(EmitterInterface::class);
        $this->finalHandler = function ($req, $res, $err = null) {
        };

        $this->injectServiceInContainer($this->container, RouterInterface::class, $this->router->reveal());
        $this->injectServiceInContainer($this->container, EmitterInterface::class, $this->emitter->reveal());
        $this->injectServiceInContainer($this->container, 'Zend\Expressive\FinalHandler', $this->finalHandler);
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
        $this->assertSame($this->finalHandler, $app->getFinalHandler());
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

        $this->injectServiceInContainer($this->container, 'config', $config);

        $app = $this->factory->__invoke($this->container->reveal());

        $r = new ReflectionProperty($app, 'routes');
        $r->setAccessible(true);
        $routes = $r->getValue($app);

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
        $this->assertNull($app->getFinalHandler());
    }

    /**
     * @group piping
     */
    public function testCanPipeMiddlewareProvidedDuringConfigurationPriorToSettingRoutes()
    {
        $middleware = function ($req, $res, $next = null) {
        };

        $config = [
            'routes' => [
                [
                    'path' => '/',
                    'middleware' => 'HelloWorld',
                    'allowed_methods' => [ 'GET' ],
                ],
            ],
            'middleware_pipeline' => [
                'pre_routing' => [
                    [ 'middleware' => $middleware ],
                    [ 'path' => '/foo', 'middleware' => $middleware ],
                ],
                'post_routing' => [ ],
            ],
        ];

        $this->injectServiceInContainer($this->container, 'config', $config);

        $app = $this->factory->__invoke($this->container->reveal());

        $r = new ReflectionProperty($app, 'pipeline');
        $r->setAccessible(true);
        $pipeline = $r->getValue($app);

        $this->assertCount(3, $pipeline);

        $route = $pipeline->dequeue();
        $this->assertInstanceOf(StratigilityRoute::class, $route);
        $this->assertSame($middleware, $route->handler);
        $this->assertEquals('/', $route->path);

        $route = $pipeline->dequeue();
        $this->assertInstanceOf(StratigilityRoute::class, $route);
        $this->assertSame($middleware, $route->handler);
        $this->assertEquals('/foo', $route->path);

        $route = $pipeline->dequeue();
        $this->assertInstanceOf(StratigilityRoute::class, $route);
        $this->assertSame([$app, 'routeMiddleware'], $route->handler);
        $this->assertEquals('/', $route->path);
    }

    /**
     * @group piping
     */
    public function testCanPipeMiddlewareProvidedDuringConfigurationAfterSettingRoutes()
    {
        $middleware = function ($req, $res, $next = null) {
            return true;
        };

        $config = [
            'routes' => [
                [
                    'path' => '/',
                    'middleware' => 'HelloWorld',
                    'allowed_methods' => [ 'GET' ],
                ],
            ],
            'middleware_pipeline' => [
                'pre_routing' => [ ],
                'post_routing' => [
                    [ 'middleware' => $middleware ],
                    [ 'path' => '/foo', 'middleware' => $middleware ],
                ],
            ],
        ];

        $this->injectServiceInContainer($this->container, 'config', $config);

        $app = $this->factory->__invoke($this->container->reveal());

        $r = new ReflectionProperty($app, 'pipeline');
        $r->setAccessible(true);
        $pipeline = $r->getValue($app);

        $this->assertCount(3, $pipeline);

        $route = $pipeline->dequeue();
        $this->assertInstanceOf(StratigilityRoute::class, $route);
        $this->assertSame([$app, 'routeMiddleware'], $route->handler);
        $this->assertEquals('/', $route->path);

        $route = $pipeline->dequeue();
        $this->assertInstanceOf(StratigilityRoute::class, $route);
        $this->assertInstanceOf(Closure::class, $route->handler);
        $this->assertTrue(call_user_func($route->handler, 'req', 'res'));
        $this->assertEquals('/', $route->path);

        $route = $pipeline->dequeue();
        $this->assertInstanceOf(StratigilityRoute::class, $route);
        $this->assertInstanceOf(Closure::class, $route->handler);
        $this->assertTrue(call_user_func($route->handler, 'req', 'res'));
        $this->assertEquals('/foo', $route->path);
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
                'post_routing' => [
                    [ 'middleware' => 'Middleware' ],
                    [ 'path' => '/foo', 'middleware' => 'Middleware' ],
                ],
            ],
        ];

        $this->injectServiceInContainer($this->container, 'config', $config);
        $this->injectServiceInContainer($this->container, 'Middleware', $middleware);

        $app = $this->factory->__invoke($this->container->reveal());

        $r = new ReflectionProperty($app, 'pipeline');
        $r->setAccessible(true);
        $pipeline = $r->getValue($app);

        $this->assertCount(3, $pipeline);

        $route = $pipeline->dequeue();
        $this->assertInstanceOf(StratigilityRoute::class, $route);
        $this->assertSame([$app, 'routeMiddleware'], $route->handler);
        $this->assertEquals('/', $route->path);

        $route = $pipeline->dequeue();
        $this->assertInstanceOf(StratigilityRoute::class, $route);
        $this->assertInstanceOf(Closure::class, $route->handler);
        $this->assertTrue(call_user_func($route->handler, 'req', 'res'));
        $this->assertEquals('/', $route->path);

        $route = $pipeline->dequeue();
        $this->assertInstanceOf(StratigilityRoute::class, $route);
        $this->assertInstanceOf(Closure::class, $route->handler);
        $this->assertTrue(call_user_func($route->handler, 'req', 'res'));
        $this->assertEquals('/foo', $route->path);
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
     * @group fail
     * @group piping
     * @dataProvider uncallableMiddleware
     */
    public function testRaisesExceptionForNonCallableNonServiceMiddleware($middleware)
    {
        $config = [
            'middleware_pipeline' => [
                'post_routing' => [
                    [ 'middleware' => $middleware ],
                    [ 'path' => '/foo', 'middleware' => $middleware ],
                ],
            ],
        ];

        $this->injectServiceInContainer($this->container, 'config', $config);

        $this->setExpectedException(InvalidArgumentException::class);
        $app = $this->factory->__invoke($this->container->reveal());
    }

    /**
     * @group piping
     */
    public function testCanPipePreRoutingMiddlewareAsArray()
    {
        $config = [
            'middleware_pipeline' => [
                'pre_routing' => [
                    [
                        'middleware' => [
                            'Hello',
                            function () {
                            },
                        ],
                    ],
                ],
            ],
        ];

        $this->injectServiceInContainer($this->container, 'config', $config);
        $this->injectServiceInContainer($this->container, 'Hello', function () {
        });

        $this->factory->__invoke($this->container->reveal());
    }

    /**
     * @group piping
     */
    public function testCanPipePostRoutingMiddlewareAsArray()
    {
        $config = [
            'middleware_pipeline' => [
                'post_routing' => [
                    [
                        'middleware' => [
                            'Hello',
                            function () {
                            },
                        ],
                    ],
                ],
            ],
        ];

        $this->injectServiceInContainer($this->container, 'config', $config);
        $this->injectServiceInContainer($this->container, 'Hello', function () {
        });

        $this->factory->__invoke($this->container->reveal());
    }

    /**
     * @group piping
     */
    public function testRaisesExceptionForPipedMiddlewareServiceNamesNotFoundInContainer()
    {
        $config = [
            'middleware_pipeline' => [
                'post_routing' => [
                    [ 'middleware' => 'Middleware' ],
                    [ 'path' => '/foo', 'middleware' => 'Middleware' ],
                ],
            ],
        ];

        $this->injectServiceInContainer($this->container, 'config', $config);

        $this->setExpectedException(InvalidArgumentException::class);
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
                'post_routing' => [
                    [ 'middleware' => 'Middleware' ],
                    [ 'path' => '/foo', 'middleware' => 'Middleware' ],
                ],
            ],
        ];

        $this->injectServiceInContainer($this->container, 'config', $config);
        $this->injectServiceInContainer($this->container, 'Middleware', $middleware);

        $app = $this->factory->__invoke($this->container->reveal());

        $r = new ReflectionProperty($app, 'pipeline');
        $r->setAccessible(true);
        $pipeline = $r->getValue($app);

        $this->assertCount(3, $pipeline);

        $routing = $pipeline->dequeue();
        $this->assertInstanceOf(StratigilityRoute::class, $routing);
        $this->assertSame([$app, 'routeMiddleware'], $routing->handler);
        $this->assertEquals('/', $routing->path);

        $first = $pipeline->dequeue();
        $this->assertInstanceOf(StratigilityRoute::class, $first);
        $this->assertInstanceOf(Closure::class, $first->handler);
        $this->assertEquals('/', $first->path);

        $second = $pipeline->dequeue();
        $this->assertInstanceOf(StratigilityRoute::class, $second);
        $this->assertInstanceOf(Closure::class, $second->handler);
        $this->assertEquals('/foo', $second->path);

        foreach (['first' => $first->handler, 'second' => $second->handler] as $index => $handler) {
            try {
                $handler('req', 'res');
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

        $r = new ReflectionProperty($app, 'routes');
        $r->setAccessible(true);
        $routes = $r->getValue($app);

        foreach ($config['routes'] as $route) {
            $this->assertRoute($route, $routes);
        }
    }

    /**
     * @group piping
     */
    public function testCanMarkPipedMiddlewareServiceAsErrorMiddleware()
    {
        $middleware = function ($err, $req, $res, $next) {
            return true;
        };

        $config = [
            'middleware_pipeline' => [
                'post_routing' => [
                    [ 'middleware' => 'Middleware', 'error' => true ],
                ],
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
        $this->assertEquals('/', $route->path);
        $this->assertSame([$app, 'routeMiddleware'], $route->handler);

        $route = $pipeline->dequeue();
        $this->assertInstanceOf(StratigilityRoute::class, $route);
        $this->assertEquals('/', $route->path);
        $this->assertInstanceOf(Closure::class, $route->handler);

        $r = new ReflectionFunction($route->handler);
        $this->assertEquals(4, $r->getNumberOfRequiredParameters());
        $this->assertTrue(call_user_func($route->handler, 'error', 'req', 'res', 'next'));
    }

    /**
     * @group 64
     */
    public function testWillPipeRoutingMiddlewareEvenIfNoRoutesAreRegistered()
    {
        $middleware = function ($req, $res, $next = null) {
        };

        $config = [
            'middleware_pipeline' => [
                'pre_routing' => [
                    [ 'middleware' => $middleware ],
                    [ 'path' => '/foo', 'middleware' => $middleware ],
                ],
                'post_routing' => [ ],
            ],
        ];

        $this->injectServiceInContainer($this->container, 'config', $config);

        $app = $this->factory->__invoke($this->container->reveal());

        $r = new ReflectionProperty($app, 'pipeline');
        $r->setAccessible(true);
        $pipeline = $r->getValue($app);

        $this->assertCount(3, $pipeline);

        $route = $pipeline->dequeue();
        $this->assertInstanceOf(StratigilityRoute::class, $route);
        $this->assertSame($middleware, $route->handler);
        $this->assertEquals('/', $route->path);

        $route = $pipeline->dequeue();
        $this->assertInstanceOf(StratigilityRoute::class, $route);
        $this->assertSame($middleware, $route->handler);
        $this->assertEquals('/foo', $route->path);

        $route = $pipeline->dequeue();
        $this->assertInstanceOf(StratigilityRoute::class, $route);
        $this->assertSame([$app, 'routeMiddleware'], $route->handler);
        $this->assertEquals('/', $route->path);
    }

    public function testCanSpecifyRouteNamesViaConfiguration()
    {
        $config = [
            'routes' => [
                [
                    'path' => '/',
                    'middleware' => 'HelloWorld',
                    'name' => 'home',
                ],
            ],
        ];

        $this->injectServiceInContainer($this->container, 'config', $config);

        $app = $this->factory->__invoke($this->container->reveal());

        $r = new ReflectionProperty($app, 'routes');
        $r->setAccessible(true);
        $routes = $r->getValue($app);
        $route  = array_shift($routes);

        $this->assertInstanceOf(Route::class, $route);
        $this->assertEquals('home', $route->getName());
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

        $r = new ReflectionProperty($app, 'routes');
        $r->setAccessible(true);
        $routes = $r->getValue($app);
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

        $this->setExpectedException(
            ContainerException\InvalidArgumentException::class,
            'route must be in form of an array; received "string"'
        );
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

        $this->setExpectedException(
            ContainerException\InvalidArgumentException::class,
            'options must be an array; received "string"'
        );
        $this->factory->__invoke($this->container->reveal());
    }

    public function testExceptionIsRaisedInCaseOfInvalidPreRoutingMiddlewarePipeline()
    {
        $config = [
            'middleware_pipeline' => [
                'pre_routing' => 'invalid',
            ],
        ];

        $this->injectServiceInContainer($this->container, 'config', $config);

        $this->setExpectedException(
            ContainerException\InvalidArgumentException::class,
            'Pre-routing middleware collection must be an array; received "string"'
        );
        $this->factory->__invoke($this->container->reveal());
    }

    public function testExceptionIsRaisedInCaseOfInvalidPostRoutingMiddlewarePipeline()
    {
        $config = [
            'middleware_pipeline' => [
                'post_routing' => 'invalid',
            ],
        ];

        $this->injectServiceInContainer($this->container, 'config', $config);

        $this->setExpectedException(
            ContainerException\InvalidArgumentException::class,
            'Post-routing middleware collection must be an array; received "string"'
        );
        $this->factory->__invoke($this->container->reveal());
    }
}
