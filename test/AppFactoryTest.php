<?php
/**
 * @see       https://github.com/zendframework/zend-expressive for the canonical source repository
 * @copyright Copyright (c) 2015-2017 Zend Technologies USA Inc. (http://www.zend.com)
 * @license   https://github.com/zendframework/zend-expressive/blob/master/LICENSE.md New BSD License
 */

namespace ZendTest\Expressive;

use PHPUnit\Framework\TestCase;
use Psr\Container\ContainerInterface;
use ReflectionClass;
use ReflectionProperty;
use Zend\Diactoros\Response\SapiEmitter;
use Zend\Expressive\AppFactory;
use Zend\Expressive\Application;
use Zend\Expressive\Emitter\EmitterStack;
use Zend\Expressive\Exception\MissingDependencyException;
use Zend\Expressive\Router\FastRouteRouter;
use Zend\Expressive\Router\RouterInterface;
use Zend\ServiceManager\ServiceManager;

/**
 * @covers Zend\Expressive\AppFactory
 */
class AppFactoryTest extends TestCase
{
    static public $existingClasses;

    protected function tearDown()
    {
        self::$existingClasses = null;
    }

    public function getRouterFromApplication(Application $app)
    {
        $r = new ReflectionProperty($app, 'router');
        $r->setAccessible(true);
        return $r->getValue($app);
    }

    public function testFactoryReturnsApplicationInstance()
    {
        $app = AppFactory::create();
        $this->assertInstanceOf(Application::class, $app);
    }

    public function testFactoryUsesFastRouteByDefault()
    {
        $app    = AppFactory::create();
        $router = $this->getRouterFromApplication($app);
        $this->assertInstanceOf(FastRouteRouter::class, $router);
    }

    public function testFactoryUsesZf2ServiceManagerByDefault()
    {
        $app        = AppFactory::create();
        $container  = $app->getContainer();
        $this->assertInstanceOf(ServiceManager::class, $container);
    }

    public function testFactoryUsesEmitterStackWithSapiEmitterComposedByDefault()
    {
        $app     = AppFactory::create();
        $emitter = $app->getEmitter();
        $this->assertInstanceOf(EmitterStack::class, $emitter);

        $this->assertCount(1, $emitter);
        $this->assertInstanceOf(SapiEmitter::class, $emitter->pop());
    }

    public function testFactoryAllowsPassingContainerToUse()
    {
        $container = $this->prophesize(ContainerInterface::class);
        $app       = AppFactory::create($container->reveal());
        $test      = $app->getContainer();
        $this->assertSame($container->reveal(), $test);
    }

    public function testFactoryAllowsPassingRouterToUse()
    {
        $router = $this->prophesize(RouterInterface::class);
        $app    = AppFactory::create(null, $router->reveal());
        $test   = $this->getRouterFromApplication($app);
        $this->assertSame($router->reveal(), $test);
    }

    /**
     * @see http://stackoverflow.com/questions/4753811/php-unit-tests-is-it-possible-to-test-for-a-fatal-error
     */
    public function testCannotInstantiateExternally()
    {
        $reflection = new ReflectionClass(AppFactory::class);
        $constructor = $reflection->getConstructor();
        $this->assertFalse($constructor->isPublic());
    }

    public function testThrowExceptionWhenContainerNotProvidedAndServiceManagerNotExists()
    {
        self::$existingClasses = [
            FastRouteRouter::class,
        ];

        $this->expectException(MissingDependencyException::class);

        AppFactory::create();
    }

    public function testThrowExceptionWhenContainerNotProvidedAndFastRouteRouterNotExists()
    {
        self::$existingClasses = [
            ServiceManager::class,
        ];

        $this->expectException(MissingDependencyException::class);

        AppFactory::create();
    }
}
