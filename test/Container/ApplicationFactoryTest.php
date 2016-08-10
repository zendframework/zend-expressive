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
use ReflectionMethod;
use ReflectionProperty;
use SplQueue;
use Zend\Diactoros\Response\EmitterInterface;
use Zend\Diactoros\Response\SapiEmitter;
use Zend\Expressive\Application;
use Zend\Expressive\Container\ApplicationFactory;
use Zend\Expressive\Container\Exception as ContainerException;
use Zend\Expressive\Emitter\EmitterStack;
use Zend\Expressive\ErrorMiddlewarePipe;
use Zend\Expressive\Exception\InvalidMiddlewareException;
use Zend\Expressive\Router\FastRouteRouter;
use Zend\Expressive\Router\Route;
use Zend\Expressive\Router\RouterInterface;
use Zend\Stratigility\ErrorMiddlewareInterface;
use Zend\Stratigility\MiddlewarePipe;
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

        $this->injectServiceInContainer($this->container, 'config', $config);

        $app = $this->factory->__invoke($this->container->reveal());

        $r = new ReflectionProperty($app, 'routes');
        $r->setAccessible(true);
        $routes = $r->getValue($app);
        $this->assertEquals(0, count($routes));
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
     * @deprecated This test can be removed for 1.1
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
                    [ 'host' => 'foo.example.com', 'middleware' => $middleware ],
                    [ 'host' => 'foo.example.com', 'path' => '/foo', 'middleware' => $middleware ],
                ],
                'post_routing' => [ ],
            ],
        ];

        $this->injectServiceInContainer($this->container, 'config', $config);

        // @codingStandardsIgnoreStart
        set_error_handler(function ($errno, $errmsg) {
            $this->assertContains('routing', $errmsg);
        }, E_USER_DEPRECATED);
        // @codingStandardsIgnoreEnd

        $app = $this->factory->__invoke($this->container->reveal());
        restore_error_handler();

        $r = new ReflectionProperty($app, 'pipeline');
        $r->setAccessible(true);
        $pipeline = $r->getValue($app);

        $this->assertCount(7, $pipeline);

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
        $this->assertSame($middleware, $route->handler);
        $this->assertEquals('/', $route->path);
        $this->assertEquals('foo.example.com', $route->host);

        $route = $pipeline->dequeue();
        $this->assertInstanceOf(StratigilityRoute::class, $route);
        $this->assertSame($middleware, $route->handler);
        $this->assertEquals('/foo', $route->path);
        $this->assertEquals('foo.example.com', $route->host);

        $route = $pipeline->dequeue();
        $this->assertInstanceOf(StratigilityRoute::class, $route);
        $this->assertSame([$app, 'routeMiddleware'], $route->handler);
        $this->assertEquals('/', $route->path);

        $route = $pipeline->dequeue();
        $this->assertInstanceOf(StratigilityRoute::class, $route);
        $this->assertSame([$app, 'routeResultObserverMiddleware'], $route->handler);
        $this->assertEquals('/', $route->path);

        $route = $pipeline->dequeue();
        $this->assertInstanceOf(StratigilityRoute::class, $route);
        $this->assertSame([$app, 'dispatchMiddleware'], $route->handler);
        $this->assertEquals('/', $route->path);
    }

    /**
     * @group piping
     * @deprecated This test can be removed for 1.1
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
                    [ 'host' => 'foo.example.com', 'middleware' => $middleware ],
                    [ 'host' => 'foo.example.com', 'path' => '/foo', 'middleware' => $middleware ],
                ],
            ],
        ];

        $this->injectServiceInContainer($this->container, 'config', $config);

        // @codingStandardsIgnoreStart
        set_error_handler(function ($errno, $errmsg) {
            $this->assertContains('routing', $errmsg);
        }, E_USER_DEPRECATED);
        // @codingStandardsIgnoreEnd

        $app = $this->factory->__invoke($this->container->reveal());
        restore_error_handler();

        $r = new ReflectionProperty($app, 'pipeline');
        $r->setAccessible(true);
        $pipeline = $r->getValue($app);

        $this->assertCount(7, $pipeline);

        $route = $pipeline->dequeue();
        $this->assertInstanceOf(StratigilityRoute::class, $route);
        $this->assertSame([$app, 'routeMiddleware'], $route->handler);
        $this->assertEquals('/', $route->path);

        $route = $pipeline->dequeue();
        $this->assertInstanceOf(StratigilityRoute::class, $route);
        $this->assertSame([$app, 'routeResultObserverMiddleware'], $route->handler);
        $this->assertEquals('/', $route->path);

        $route = $pipeline->dequeue();
        $this->assertInstanceOf(StratigilityRoute::class, $route);
        $this->assertSame([$app, 'dispatchMiddleware'], $route->handler);
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

        $route = $pipeline->dequeue();
        $this->assertInstanceOf(StratigilityRoute::class, $route);
        $this->assertSame($middleware, $route->handler);
        $this->assertEquals('/', $route->path);
        $this->assertEquals('foo.example.com', $route->host);

        $route = $pipeline->dequeue();
        $this->assertInstanceOf(StratigilityRoute::class, $route);
        $this->assertSame($middleware, $route->handler);
        $this->assertEquals('/foo', $route->path);
        $this->assertEquals('foo.example.com', $route->host);
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
                [ 'middleware' => 'Middleware' ],
                [ 'path' => '/foo', 'middleware' => 'Middleware' ],
                [ 'host' => 'foo.example.com', 'middleware' => 'Middleware' ],
                [ 'host' => 'foo.example.com', 'path' => '/foo', 'middleware' => 'Middleware' ],
            ],
        ];

        $this->injectServiceInContainer($this->container, 'config', $config);
        $this->injectServiceInContainer($this->container, 'Middleware', $middleware);

        $app = $this->factory->__invoke($this->container->reveal());

        $r = new ReflectionProperty($app, 'pipeline');
        $r->setAccessible(true);
        $pipeline = $r->getValue($app);

        $this->assertCount(4, $pipeline);

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

        $route = $pipeline->dequeue();
        $this->assertInstanceOf(StratigilityRoute::class, $route);
        $this->assertInstanceOf(Closure::class, $route->handler);
        $this->assertTrue(call_user_func($route->handler, 'req', 'res'));
        $this->assertEquals('/', $route->path);
        $this->assertEquals('foo.example.com', $route->host);

        $route = $pipeline->dequeue();
        $this->assertInstanceOf(StratigilityRoute::class, $route);
        $this->assertInstanceOf(Closure::class, $route->handler);
        $this->assertTrue(call_user_func($route->handler, 'req', 'res'));
        $this->assertEquals('/foo', $route->path);
        $this->assertEquals('foo.example.com', $route->host);
    }

    /**
     * @group piping
     */
    public function testMiddlewareIsNotAddedIfSpecIsInvalid()
    {
        $config = [
            'middleware_pipeline' => [
                [ 'foo' => 'bar' ],
                [ 'path' => '/foo' ],
            ],
        ];

        $this->injectServiceInContainer($this->container, 'config', $config);

        $this->setExpectedException(ContainerException\InvalidArgumentException::class, 'pipeline');
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
                [ 'middleware' => $middleware ],
                [ 'path' => '/foo', 'middleware' => $middleware ],
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
     * @deprecated This test can be removed for 1.1
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

        // @codingStandardsIgnoreStart
        set_error_handler(function ($errno, $errmsg) {
            $this->assertContains('routing', $errmsg);
        }, E_USER_DEPRECATED);
        // @codingStandardsIgnoreEnd

        $this->factory->__invoke($this->container->reveal());
        restore_error_handler();
    }

    /**
     * @group piping
     * @deprecated This test can be removed for 1.1
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

        // @codingStandardsIgnoreStart
        set_error_handler(function ($errno, $errmsg) {
            $this->assertContains('routing', $errmsg);
        }, E_USER_DEPRECATED);
        // @codingStandardsIgnoreEnd

        $this->factory->__invoke($this->container->reveal());
        restore_error_handler();
    }

    /**
     * @group piping
     */
    public function testRaisesExceptionForPipedMiddlewareServiceNamesNotFoundInContainer()
    {
        $config = [
            'middleware_pipeline' => [
                [ 'middleware' => 'Middleware' ],
                [ 'path' => '/foo', 'middleware' => 'Middleware' ],
                [ 'host' => 'foo.example.com', 'middleware' => 'Middleware' ],
                [ 'host' => 'foo.example.com', 'path' => '/foo', 'middleware' => 'Middleware' ],
            ],
        ];

        $this->injectServiceInContainer($this->container, 'config', $config);

        $this->setExpectedException(InvalidMiddlewareException::class);
        $app = $this->factory->__invoke($this->container->reveal());
    }

    /**
     * @group piping
     */
    public function testRaisesExceptionOnInvocationOfUninvokableServiceSpecifiedMiddlewarePulledFromContainer()
    {
        $middleware = (object)[];

        $config = [
            'middleware_pipeline' => [
                ['middleware' => 'Middleware'],
                ['path' => '/foo', 'middleware' => 'Middleware'],
                ['host' => 'foo.example.com', 'middleware' => 'Middleware'],
                ['host' => 'foo.example.com', 'path' => '/foo', 'middleware' => 'Middleware'],
            ],
        ];

        $this->injectServiceInContainer($this->container, 'config', $config);
        $this->injectServiceInContainer($this->container, 'Middleware', $middleware);

        $app = $this->factory->__invoke($this->container->reveal());

        $r = new ReflectionProperty($app, 'pipeline');
        $r->setAccessible(true);
        $pipeline = $r->getValue($app);

        $this->assertCount(4, $pipeline);

        $first = $pipeline->dequeue();
        $this->assertInstanceOf(StratigilityRoute::class, $first);
        $this->assertInstanceOf(Closure::class, $first->handler);
        $this->assertEquals('/', $first->path);

        $second = $pipeline->dequeue();
        $this->assertInstanceOf(StratigilityRoute::class, $second);
        $this->assertInstanceOf(Closure::class, $second->handler);
        $this->assertEquals('/foo', $second->path);

        $third = $pipeline->dequeue();
        $this->assertInstanceOf(StratigilityRoute::class, $third);
        $this->assertInstanceOf(Closure::class, $third->handler);
        $this->assertEquals('/', $third->path);
        $this->assertEquals('foo.example.com', $third->host);

        $fourth = $pipeline->dequeue();
        $this->assertInstanceOf(StratigilityRoute::class, $fourth);
        $this->assertInstanceOf(Closure::class, $fourth->handler);
        $this->assertEquals('/foo', $fourth->path);
        $this->assertEquals('foo.example.com', $fourth->host);

        foreach ([
                     'first' => $first->handler,
                     'second' => $second->handler,
                     'third' => $third->handler,
                     'fourth' => $fourth->handler
                 ] as $index => $handler) {
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
                [ 'middleware' => 'Middleware', 'error' => true ],
            ],
        ];

        $this->injectServiceInContainer($this->container, 'config', $config);
        $this->injectServiceInContainer($this->container, 'Middleware', $middleware);

        $app = $this->factory->__invoke($this->container->reveal());

        $r = new ReflectionProperty($app, 'pipeline');
        $r->setAccessible(true);
        $pipeline = $r->getValue($app);

        $this->assertCount(1, $pipeline);

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
     * @deprecated This test can be removed for 1.1
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
                    [ 'host' => 'foo.example.com', 'middleware' => $middleware ],
                    [ 'host' => 'foo.example.com', 'path' => '/foo', 'middleware' => $middleware ],
                ],
                'post_routing' => [ ],
            ],
        ];

        $this->injectServiceInContainer($this->container, 'config', $config);

        // @codingStandardsIgnoreStart
        set_error_handler(function ($errno, $errmsg) {
            $this->assertContains('routing', $errmsg);
        }, E_USER_DEPRECATED);
        // @codingStandardsIgnoreEnd

        $app = $this->factory->__invoke($this->container->reveal());
        restore_error_handler();

        $r = new ReflectionProperty($app, 'pipeline');
        $r->setAccessible(true);
        $pipeline = $r->getValue($app);

        $this->assertCount(7, $pipeline);

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
        $this->assertSame($middleware, $route->handler);
        $this->assertEquals('/', $route->path);
        $this->assertEquals('foo.example.com', $route->host);

        $route = $pipeline->dequeue();
        $this->assertInstanceOf(StratigilityRoute::class, $route);
        $this->assertSame($middleware, $route->handler);
        $this->assertEquals('/foo', $route->path);
        $this->assertEquals('foo.example.com', $route->host);

        $route = $pipeline->dequeue();
        $this->assertInstanceOf(StratigilityRoute::class, $route);
        $this->assertSame([$app, 'routeMiddleware'], $route->handler);
        $this->assertEquals('/', $route->path);

        $route = $pipeline->dequeue();
        $this->assertInstanceOf(StratigilityRoute::class, $route);
        $this->assertSame([$app, 'routeResultObserverMiddleware'], $route->handler);
        $this->assertEquals('/', $route->path);

        $route = $pipeline->dequeue();
        $this->assertInstanceOf(StratigilityRoute::class, $route);
        $this->assertSame([$app, 'dispatchMiddleware'], $route->handler);
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

    /**
     * @deprecated This test can be removed for 1.1
     */
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
        // @codingStandardsIgnoreStart
        set_error_handler(function ($errno, $errmsg) {
            $this->assertContains('routing', $errmsg);
        }, E_USER_DEPRECATED);
        // @codingStandardsIgnoreEnd

        $this->factory->__invoke($this->container->reveal());
    }

    /**
     * @deprecated This test can be removed for 1.1
     */
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
        // @codingStandardsIgnoreStart
        set_error_handler(function ($errno, $errmsg) {
            $this->assertContains('routing', $errmsg);
        }, E_USER_DEPRECATED);
        // @codingStandardsIgnoreEnd

        $this->factory->__invoke($this->container->reveal());
    }

    public function testWillCreatePipelineBasedOnMiddlewareConfiguration()
    {
        // @codingStandardsIgnoreStart
        $api = function ($request, $response, $next) {};
        // @codingStandardsIgnoreEnd

        $dynamicPath = clone $api;
        $noPath = clone $api;
        $goodbye = clone $api;
        $pipelineFirst = clone $api;
        $hello = clone $api;
        $pipelineLast = clone $api;
        $hostWithNoPath = clone $api;
        $hostWithPath = clone $api;

        $this->injectServiceInContainer($this->container, 'DynamicPath', $dynamicPath);
        $this->injectServiceInContainer($this->container, 'Goodbye', $goodbye);
        $this->injectServiceInContainer($this->container, 'Hello', $hello);

        $pipeline = [
            [ 'path' => '/api', 'middleware' => $api ],
            [ 'path' => '/dynamic-path', 'middleware' => 'DynamicPath' ],
            [ 'host' => 'foo.example.com', 'middleware' => $hostWithNoPath ],
            [ 'host' => 'foo.example.com', 'path' => '/foo', 'middleware' => $hostWithPath ],
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

        $this->assertCount(7, $pipeline, 'Did not get expected pipeline count!');

        $test = $pipeline->dequeue();
        $this->assertEquals('/api', $test->path);
        $this->assertEquals(null, $test->host);
        $this->assertSame($api, $test->handler);

        // Lazy middleware is not marshaled until invocation
        $test = $pipeline->dequeue();
        $this->assertEquals('/dynamic-path', $test->path);
        $this->assertEquals(null, $test->host);
        $this->assertNotSame($dynamicPath, $test->handler);
        $this->assertInstanceOf(Closure::class, $test->handler);

        $test = $pipeline->dequeue();
        $this->assertEquals('/', $test->path);
        $this->assertEquals('foo.example.com', $test->host);
        $this->assertSame($hostWithNoPath, $test->handler);

        $test = $pipeline->dequeue();
        $this->assertEquals('/foo', $test->path);
        $this->assertEquals('foo.example.com', $test->host);
        $this->assertSame($hostWithPath, $test->handler);

        $test = $pipeline->dequeue();
        $this->assertEquals('/', $test->path);
        $this->assertEquals(null, $test->host);
        $this->assertSame($noPath, $test->handler);

        // Lazy middleware is not marshaled until invocation
        $test = $pipeline->dequeue();
        $this->assertEquals('/', $test->path);
        $this->assertEquals(null, $test->host);
        $this->assertNotSame($goodbye, $test->handler);
        $this->assertInstanceOf(Closure::class, $test->handler);

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
        $this->assertInstanceOf(Closure::class, $test->handler);

        $test = $nestedPipeline->dequeue();
        $this->assertSame($pipelineLast, $test->handler);
    }

    public function mixedMiddlewarePipelines()
    {
        // @codingStandardsIgnoreStart
        $middleware = function ($request, $response, $next) {};
        $pre = ['middleware' => clone $middleware];
        $post = ['middleware' => clone $middleware];
        $pipelined = ['middleware' => clone $middleware];
        return [
            'pre_routing'  => [['middleware_pipeline' => ['pre_routing' => [$pre], $pipelined]]],
            'post_routing' => [['middleware_pipeline' => ['post_routing' => [$post], $pipelined]]],
            'pre_and_post' => [['middleware_pipeline' => ['pre_routing' => [$pre], 'post_routing' => [$post], $pipelined]]],
        ];
        // @codingStandardsIgnoreEnd
    }

    /**
     * @dataProvider mixedMiddlewarePipelines
     */
    public function testRaisesExceptionIfMiddlewarePipelineConfigurationMixesMiddlewareAndPreOrPostRouting($config)
    {
        $this->injectServiceInContainer($this->container, 'config', $config);

        $this->setExpectedException(InvalidArgumentException::class, 'mix of middleware');
        $this->factory->__invoke($this->container->reveal());
    }

    public function middlewarePipelinesWithPreOrPostRouting()
    {
        // @codingStandardsIgnoreStart
        $middleware = function ($request, $response, $next) {};
        $config = ['middleware' => clone $middleware];
        return [
            'pre_routing'  => [['pre_routing'  => [$config]]],
            'post_routing' => [['post_routing' => [$config]]],
        ];
        // @codingStandardsIgnoreEnd
    }

    /**
     * @dataProvider middlewarePipelinesWithPreOrPostRouting
     * @deprecated This test can be removed for 1.1
     */
    public function testRaisesDeprecationNoticeForUsageOfPreOrPostRoutingPipelineConfiguration($config)
    {
        $config = ['middleware_pipeline' => $config];
        $this->injectServiceInContainer($this->container, 'config', $config);

        // @codingStandardsIgnoreStart
        $triggered = false;
        set_error_handler(function ($errno, $errmsg) use (&$triggered) {
            $this->assertContains('routing', $errmsg);
            $triggered = true;
        }, E_USER_DEPRECATED);
        // @codingStandardsIgnoreEnd

        $this->factory->__invoke($this->container->reveal());
        restore_error_handler();
        $this->assertTrue($triggered, 'Deprecation notice was not triggered!');
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

    /**
     * @deprecated This test can be removed for 1.1
     */
    public function testPipelineContainingRouteResultObserverMiddlewareConstantPipesRelatedMiddleware()
    {
        $config = [
            'middleware_pipeline' => [
                ApplicationFactory::ROUTE_RESULT_OBSERVER_MIDDLEWARE,
            ],
        ];
        $this->injectServiceInContainer($this->container, 'config', $config);

        $app = $this->factory->__invoke($this->container->reveal());
        $this->assertAttributeSame(true, 'routeResultObserverMiddlewareIsRegistered', $app);
    }

    /**
     * @dataProvider middlewarePipelinesWithPreOrPostRouting
     * @deprecated This test can be removed for 1.1
     */
    public function testUsageOfDeprecatedConfigurationRegistersRouteResultObserverMiddleware($config)
    {
        $config = ['middleware_pipeline' => $config];
        $this->injectServiceInContainer($this->container, 'config', $config);

        // @codingStandardsIgnoreStart
        set_error_handler(function ($errno, $errmsg) use (&$triggered) {
            $this->assertContains('routing', $errmsg);
            $triggered = true;
        }, E_USER_DEPRECATED);
        // @codingStandardsIgnoreEnd

        $app = $this->factory->__invoke($this->container->reveal());
        restore_error_handler();

        $this->assertAttributeSame(true, 'routeResultObserverMiddlewareIsRegistered', $app);
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
        // @codingStandardsIgnoreStart
        $middleware = function ($request, $response, $next) {};
        // @codingStandardsIgnoreEnd

        $pipeline1 = [ [ 'middleware' => clone $middleware ] ];
        $pipeline2 = [ [ 'middleware' => clone $middleware ] ];
        $pipeline3 = [ [ 'middleware' => clone $middleware ] ];

        $pipeline = array_merge($pipeline3, $pipeline1, $pipeline2);
        $config = [ 'middleware_pipeline' => $pipeline ];
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
        $this->injectServiceInContainer($this->container, 'config', $config);

        $app = $this->factory->__invoke($this->container->reveal());

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

        $this->injectServiceInContainer($this->container, 'config', $config);
        $fooError = $this->prophesize(ErrorMiddlewareInterface::class)->reveal();
        $this->injectServiceInContainer($this->container, 'FooError', $fooError);

        $app = $this->factory->__invoke($this->container->reveal());

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
