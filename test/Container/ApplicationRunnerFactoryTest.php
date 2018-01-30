<?php
/**
 * @see       https://github.com/zendframework/zend-expressive for the canonical source repository
 * @copyright Copyright (c) 2018 Zend Technologies USA Inc. (https://www.zend.com)
 * @license   https://github.com/zendframework/zend-expressive/blob/master/LICENSE.md New BSD License
 */

declare(strict_types=1);

namespace ZendTest\Expressive\Container;

use PHPUnit\Framework\TestCase;
use Psr\Container\ContainerInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Zend\Diactoros\Response\EmitterInterface;
use Zend\Expressive\Application;
use Zend\Expressive\ApplicationRunner;
use Zend\Expressive\Container\ApplicationRunnerFactory;
use Zend\Expressive\ServerRequestFactory;
use Zend\Expressive\ServerRequestErrorResponseGenerator;

class ApplicationRunnerFactoryTest extends TestCase
{
    public function testFactoryProducesRunnerUsingServicesFromContainer()
    {
        $container = $this->prophesize(ContainerInterface::class);
        $app = $this->registerApplicationInContainer($container);
        $emitter = $this->registerEmitterInContainer($container);
        $serverRequestFactory = $this->registerServerRequestFactoryInContainer($container);
        $errorGenerator = $this->registerServerRequestErroResponseGeneratorInContainer($container);

        $factory = new ApplicationRunnerFactory();

        $runner = $factory($container->reveal());

        $this->assertInstanceOf(ApplicationRunner::class, $runner);
        $this->assertAttributeSame($app, 'handler', $runner);
        $this->assertAttributeSame($emitter, 'emitter', $runner);
        $this->assertAttributeSame($serverRequestFactory, 'serverRequestFactory', $runner);
        $this->assertAttributeSame($errorGenerator, 'serverRequestErrorResponseGenerator', $runner);
    }

    public function registerApplicationInContainer($container) : RequestHandlerInterface
    {
        $app = $this->prophesize(RequestHandlerInterface::class)->reveal();
        $container->get(Application::class)->willReturn($app);
        return $app;
    }

    public function registerEmitterInContainer($container) : EmitterInterface
    {
        $emitter = $this->prophesize(EmitterInterface::class)->reveal();
        $container->get(EmitterInterface::class)->willReturn($emitter);
        return $emitter;
    }

    public function registerServerRequestFactoryInContainer($container) : callable
    {
        $factory = function () {
        };
        $container->get(ServerRequestFactory::class)->willReturn($factory);
        return $factory;
    }

    public function registerServerRequestErroResponseGeneratorInContainer($container) : callable
    {
        $generator = function ($e) {
        };
        $container->get(ServerRequestErrorResponseGenerator::class)->willReturn($generator);
        return $generator;
    }
}
