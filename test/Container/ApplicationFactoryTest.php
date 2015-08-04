<?php
namespace ZendTest\Expressive\Container;

use PHPUnit_Framework_TestCase as TestCase;
use ReflectionProperty;
use Zend\Expressive\Application;
use Zend\Expressive\Container\ApplicationFactory;

class ApplicationFactoryTest extends TestCase
{
    public function setUp()
    {
        $this->container = $this->prophesize('Interop\Container\ContainerInterface');
        $this->factory   = new ApplicationFactory();
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

            if ($route->getAllowedMethods() !== $spec['allowed_methods']) {
                return false;
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
        $router       = $this->prophesize('Zend\Expressive\Router\RouterInterface');
        $emitter      = $this->prophesize('Zend\Diactoros\Response\EmitterInterface');
        $finalHandler = function ($req, $res, $err = null) {
        };

        $this->container
            ->has('Zend\Expressive\Router\RouterInterface')
            ->willReturn(true);
        $this->container
            ->get('Zend\Expressive\Router\RouterInterface')
            ->will(function () use ($router) {
                return $router->reveal();
            });

        $this->container
            ->has('Zend\Diactoros\Response\EmitterInterface')
            ->willReturn(true);
        $this->container
            ->get('Zend\Diactoros\Response\EmitterInterface')
            ->will(function () use ($emitter) {
                return $emitter->reveal();
            });

        $this->container
            ->has('Zend\Expressive\FinalHandler')
            ->willReturn(true);
        $this->container
            ->get('Zend\Expressive\FinalHandler')
            ->willReturn($finalHandler);

        $this->container
            ->has('Config')
            ->willReturn(false);

        $app = $this->factory->__invoke($this->container->reveal());
        $this->assertInstanceOf('Zend\Expressive\Application', $app);
        $test = $this->getRouterFromApplication($app);
        $this->assertSame($router->reveal(), $test);
        $this->assertSame($this->container->reveal(), $app->getContainer());
        $this->assertSame($emitter->reveal(), $app->getEmitter());
        $this->assertSame($finalHandler, $app->getFinalHandler());
    }

    public function testFactorySetsUpRoutesFromConfig()
    {
        $router = $this->prophesize('Zend\Expressive\Router\RouterInterface');
        $emitter = $this->prophesize('Zend\Diactoros\Response\EmitterInterface');
        $finalHandler = function ($req, $res, $err = null) {
        };

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

        $this->container
            ->has('Zend\Expressive\Router\RouterInterface')
            ->willReturn(true);
        $this->container
            ->get('Zend\Expressive\Router\RouterInterface')
            ->will(function () use ($router) {
                return $router->reveal();
            });

        $this->container
            ->has('Zend\Diactoros\Response\EmitterInterface')
            ->willReturn(true);
        $this->container
            ->get('Zend\Diactoros\Response\EmitterInterface')
            ->will(function () use ($emitter) {
                return $emitter->reveal();
            });

        $this->container
            ->has('Zend\Expressive\FinalHandler')
            ->willReturn(true);
        $this->container
            ->get('Zend\Expressive\FinalHandler')
            ->willReturn($finalHandler);

        $this->container
            ->has('Config')
            ->willReturn(true);

        $this->container
            ->get('Config')
            ->willReturn($config);

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
        $this->container
            ->has('Zend\Expressive\Router\RouterInterface')
            ->willReturn(false);
        $this->container
            ->get('Zend\Expressive\Router\RouterInterface')
            ->shouldNotBeCalled();

        $this->container
            ->has('Zend\Diactoros\Response\EmitterInterface')
            ->willReturn(false);
        $this->container
            ->get('Zend\Diactoros\Response\EmitterInterface')
            ->shouldNotBeCalled();

        $this->container
            ->has('Zend\Expressive\FinalHandler')
            ->willReturn(false);
        $this->container
            ->get('Zend\Expressive\FinalHandler')
            ->shouldNotBeCalled();

        $this->container
            ->has('Config')
            ->willReturn(false);

        $app = $this->factory->__invoke($this->container->reveal());
        $this->assertInstanceOf('Zend\Expressive\Application', $app);
        $router = $this->getRouterFromApplication($app);
        $this->assertInstanceOf('Zend\Expressive\Router\Aura', $router);
        $this->assertSame($this->container->reveal(), $app->getContainer());
        $this->assertInstanceOf('Zend\Expressive\Emitter\EmitterStack', $app->getEmitter());
        $this->assertCount(1, $app->getEmitter());
        $this->assertInstanceOf('Zend\Diactoros\Response\SapiEmitter', $app->getEmitter()->pop());
        $this->assertInstanceOf('Zend\Stratigility\FinalHandler', $app->getFinalHandler());
    }
}
