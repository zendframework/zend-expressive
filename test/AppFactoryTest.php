<?php
/**
 * Zend Framework (http://framework.zend.com/)
 *
 * @see       https://github.com/zendframework/zend-diactoros for the canonical source repository
 * @copyright Copyright (c) 2015 Zend Technologies USA Inc. (http://www.zend.com)
 * @license   https://github.com/zendframework/zend-diactoros/blob/master/LICENSE.md New BSD License
 */

namespace ZendTest\Expressive;

use PHPUnit_Framework_TestCase as TestCase;
use ReflectionProperty;
use Zend\Expressive\Application;
use Zend\Expressive\AppFactory;

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
        $this->assertInstanceOf('Zend\Expressive\Application', $app);
    }

    public function testFactoryUsesAuraRouterByDefault()
    {
        $app    = AppFactory::create();
        $router = $this->getRouterFromApplication($app);
        $this->assertInstanceOf('Zend\Expressive\Router\Aura', $router);
    }

    public function testFactoryUsesZf2ServiceManagerByDefault()
    {
        $app        = AppFactory::create();
        $container  = $app->getContainer();
        $this->assertInstanceOf('Zend\ServiceManager\ServiceManager', $container);
    }

    public function testFactoryUsesEmitterStackWithSapiEmitterComposedByDefault()
    {
        $app     = AppFactory::create();
        $emitter = $app->getEmitter();
        $this->assertInstanceOf('Zend\Expressive\Emitter\EmitterStack', $emitter);

        $this->assertCount(1, $emitter);
        $this->assertInstanceOf('Zend\Diactoros\Response\SapiEmitter', $emitter->pop());
    }

    public function testFactoryAllowsPassingContainerToUse()
    {
        $container = $this->prophesize('Interop\Container\ContainerInterface');
        $app       = AppFactory::create($container->reveal());
        $test      = $app->getContainer();
        $this->assertSame($container->reveal(), $test);
    }

    public function testFactoryAllowsPassingRouterToUse()
    {
        $router = $this->prophesize('Zend\Expressive\Router\RouterInterface');
        $app    = AppFactory::create(null, $router->reveal());
        $test   = $this->getRouterFromApplication($app);
        $this->assertSame($router->reveal(), $test);
    }
}
