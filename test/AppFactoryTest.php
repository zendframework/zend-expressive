<?php
/**
 * Zend Framework (http://framework.zend.com/)
 *
 * @see       https://github.com/zendframework/zend-expressive for the canonical source repository
 * @copyright Copyright (c) 2015-2016 Zend Technologies USA Inc. (http://www.zend.com)
 * @license   https://github.com/zendframework/zend-expressive/blob/master/LICENSE.md New BSD License
 */

namespace ZendTest\Expressive;

use Interop\Container\ContainerInterface;
use PHPUnit_Framework_TestCase as TestCase;
use ReflectionProperty;
use Zend\Diactoros\Response\SapiEmitter;
use Zend\Expressive\AppFactory;
use Zend\Expressive\Application;
use Zend\Expressive\Emitter\EmitterStack;
use Zend\Expressive\Router\FastRouteRouter;
use Zend\Expressive\Router\RouterInterface;
use Zend\ServiceManager\ServiceManager;

/**
 * @covers Zend\Expressive\AppFactory
 */
class AppFactoryTest extends TestCase
{
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
}
