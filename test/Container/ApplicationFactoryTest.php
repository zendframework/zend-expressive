<?php
/**
 * Zend Framework (http://framework.zend.com/)
 *
 * @see       https://github.com/zendframework/zend-expressive for the canonical source repository
 * @copyright Copyright (c) 2015 Zend Technologies USA Inc. (http://www.zend.com)
 * @license   https://github.com/zendframework/zend-expressive/blob/master/LICENSE.md New BSD License
 */

namespace ZendTest\Expressive\Container;

use PHPUnit_Framework_TestCase as TestCase;
use Prophecy\Prophecy\ObjectProphecy;
use ReflectionFunction;
use ReflectionProperty;
use Zend\Diactoros\Response\EmitterInterface;
use Zend\Expressive\Application;
use Zend\Expressive\Container\ApplicationFactory;
use Zend\Expressive\Router\Route;
use Zend\Expressive\Router\RouterInterface;
use ZendTest\Expressive\ContainerTrait;

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

    protected function getContainer($config = null, $router = null, $finalHandler = null, $emitter = null)
    {
        if (null !== $config) {
            $this->container
                ->has('config')
                ->willReturn(true);

            $this->container
                ->get('config')
                ->willReturn($config);
        } else {
            $this->container
                ->has('config')
                ->willReturn(false);
        }

        if (null !== $router) {
            $this->container
                ->has('Zend\Expressive\Router\RouterInterface')
                ->willReturn(true);
            $this->container
                ->get('Zend\Expressive\Router\RouterInterface')
                ->will(function () use ($router) {
                    return $router->reveal();
                });
        } else {
            $this->container
                ->has('Zend\Expressive\Router\RouterInterface')
                ->willReturn(false);
        }

        if (null !== $finalHandler) {
            $this->container
                ->has('Zend\Expressive\FinalHandler')
                ->willReturn(true);
            $this->container
                ->get('Zend\Expressive\FinalHandler')
                ->willReturn($finalHandler);
        } else {
            $this->container
                ->has('Zend\Expressive\FinalHandler')
                ->willReturn(false);
        }

        if (null !== $emitter) {
            $this->container
                ->has('Zend\Diactoros\Response\EmitterInterface')
                ->willReturn(true);
            $this->container
                ->get('Zend\Diactoros\Response\EmitterInterface')
                ->will(function () use ($emitter) {
                    return $emitter->reveal();
                });
        } else {
            $this->container
                ->has('Zend\Diactoros\Response\EmitterInterface')
                ->willReturn(false);
        }

        return $this->container;
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

    /**
     * @expectedException \Zend\Expressive\Exception\InvalidArgumentException
     */
    public function testInvalidPhpSettingsRaisesException()
    {
        $config = ['php_settings' => 'invalid'];

        $this->factory->__invoke($this->getContainer($config)->reveal());
    }

    /**
     * @expectedException \Zend\Expressive\Container\Exception\PhpSettingsFailureException
     */
    public function testWhenSettingPhpConfigurationFailsExceptionIsRaised()
    {
        $config = [
            'php_settings' => [
                'invalid_option' => 1,
            ],
        ];

        $this->factory->__invoke($this->getContainer($config)->reveal());
    }

    public function testFactorySetsPhpSettingsFromConfig()
    {
        $config = [
            'php_settings' => [
                'display_errors' => 1,
                'date.timezone' => 'Europe/Belgrade',
            ],
        ];

        $this->iniSet('display_errors', 0);
        $this->iniSet('date.timezone', 'UTC');

        $this->factory->__invoke($this->getContainer($config)->reveal());

        foreach ($config['php_settings'] as $name => $value) {
            $this->assertEquals($value, ini_get($name));
        }
    }

    public function testFactoryWillPullAllReplaceableDependenciesFromContainerWhenPresent()
    {
        $app = $this->factory->__invoke($this->container->reveal());
        $this->assertInstanceOf('Zend\Expressive\Application', $app);
        $test = $this->getRouterFromApplication($app);
        $this->assertSame($this->router->reveal(), $test);
        $this->assertSame($this->container->reveal(), $app->getContainer());
        $this->assertSame($this->emitter->reveal(), $app->getEmitter());
        $this->assertSame($this->finalHandler, $app->getFinalHandler());
    }

    public function testFactorySetsUpRoutesFromConfig()
    {
        $config = [
            'routes' => [
                [
                    'path' => '/',
                    'middleware' => 'HelloWorld',
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
        $this->assertInstanceOf('Zend\Expressive\Application', $app);
        $router = $this->getRouterFromApplication($app);
        $this->assertInstanceOf('Zend\Expressive\Router\FastRouteRouter', $router);
        $this->assertSame($container->reveal(), $app->getContainer());
        $this->assertInstanceOf('Zend\Expressive\Emitter\EmitterStack', $app->getEmitter());
        $this->assertCount(1, $app->getEmitter());
        $this->assertInstanceOf('Zend\Diactoros\Response\SapiEmitter', $app->getEmitter()->pop());
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
        $this->assertInstanceOf('Zend\Stratigility\Route', $route);
        $this->assertSame($middleware, $route->handler);
        $this->assertEquals('/', $route->path);

        $route = $pipeline->dequeue();
        $this->assertInstanceOf('Zend\Stratigility\Route', $route);
        $this->assertSame($middleware, $route->handler);
        $this->assertEquals('/foo', $route->path);

        $route = $pipeline->dequeue();
        $this->assertInstanceOf('Zend\Stratigility\Route', $route);
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
        $this->assertInstanceOf('Zend\Stratigility\Route', $route);
        $this->assertSame([$app, 'routeMiddleware'], $route->handler);
        $this->assertEquals('/', $route->path);

        $route = $pipeline->dequeue();
        $this->assertInstanceOf('Zend\Stratigility\Route', $route);
        $this->assertInstanceOf('Closure', $route->handler);
        $this->assertTrue(call_user_func($route->handler, 'req', 'res'));
        $this->assertEquals('/', $route->path);

        $route = $pipeline->dequeue();
        $this->assertInstanceOf('Zend\Stratigility\Route', $route);
        $this->assertInstanceOf('Closure', $route->handler);
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
        $this->assertInstanceOf('Zend\Stratigility\Route', $route);
        $this->assertSame([$app, 'routeMiddleware'], $route->handler);
        $this->assertEquals('/', $route->path);

        $route = $pipeline->dequeue();
        $this->assertInstanceOf('Zend\Stratigility\Route', $route);
        $this->assertInstanceOf('Closure', $route->handler);
        $this->assertTrue(call_user_func($route->handler, 'req', 'res'));
        $this->assertEquals('/', $route->path);

        $route = $pipeline->dequeue();
        $this->assertInstanceOf('Zend\Stratigility\Route', $route);
        $this->assertInstanceOf('Closure', $route->handler);
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

        $this->setExpectedException('InvalidArgumentException');
        $app = $this->factory->__invoke($this->container->reveal());
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

        $this->setExpectedException('InvalidArgumentException');
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
        $this->assertInstanceOf('Zend\Stratigility\Route', $routing);
        $this->assertSame([$app, 'routeMiddleware'], $routing->handler);
        $this->assertEquals('/', $routing->path);

        $first = $pipeline->dequeue();
        $this->assertInstanceOf('Zend\Stratigility\Route', $first);
        $this->assertInstanceOf('Closure', $first->handler);
        $this->assertEquals('/', $first->path);

        $second = $pipeline->dequeue();
        $this->assertInstanceOf('Zend\Stratigility\Route', $second);
        $this->assertInstanceOf('Closure', $second->handler);
        $this->assertEquals('/foo', $second->path);

        foreach (['first' => $first->handler, 'second' => $second->handler] as $index => $handler) {
            try {
                $handler('req', 'res');
                $this->fail(sprintf('%s handler succeed, but should have raised an exception', $index));
            } catch (\Exception $e) {
                $this->assertInstanceOf('Zend\Expressive\Exception\InvalidMiddlewareException', $e);
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
        $this->assertInstanceOf('Zend\Stratigility\Route', $route);
        $this->assertEquals('/', $route->path);
        $this->assertSame([$app, 'routeMiddleware'], $route->handler);

        $route = $pipeline->dequeue();
        $this->assertInstanceOf('Zend\Stratigility\Route', $route);
        $this->assertEquals('/', $route->path);
        $this->assertInstanceOf('Closure', $route->handler);

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
        $this->assertInstanceOf('Zend\Stratigility\Route', $route);
        $this->assertSame($middleware, $route->handler);
        $this->assertEquals('/', $route->path);

        $route = $pipeline->dequeue();
        $this->assertInstanceOf('Zend\Stratigility\Route', $route);
        $this->assertSame($middleware, $route->handler);
        $this->assertEquals('/foo', $route->path);

        $route = $pipeline->dequeue();
        $this->assertInstanceOf('Zend\Stratigility\Route', $route);
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
}
