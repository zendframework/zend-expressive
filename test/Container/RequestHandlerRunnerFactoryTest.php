<?php
/**
 * @see       https://github.com/zendframework/zend-expressive for the canonical source repository
 * @copyright Copyright (c) 2018 Zend Technologies USA Inc. (https://www.zend.com)
 * @license   https://github.com/zendframework/zend-expressive/blob/master/LICENSE.md New BSD License
 */

declare(strict_types=1);

namespace ZendTest\Expressive\Container;

use PHPUnit\Framework\TestCase;
use Prophecy\Prophecy\ObjectProphecy;
use Psr\Container\ContainerInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Zend\Expressive\ApplicationPipeline;
use Zend\Expressive\Container\RequestHandlerRunnerFactory;
use Zend\Expressive\ServerRequestErrorResponseGenerator;
use Zend\HttpHandlerRunner\Emitter\EmitterInterface;
use Zend\HttpHandlerRunner\RequestHandlerRunner;

class RequestHandlerRunnerFactoryTest extends TestCase
{
    public function testFactoryProducesRunnerUsingServicesFromContainer()
    {
        $container = $this->prophesize(ContainerInterface::class);
        $handler = $this->registerHandlerInContainer($container);
        $emitter = $this->registerEmitterInContainer($container);
        $serverRequestFactory = $this->registerServerRequestFactoryInContainer($container);
        $errorGenerator = $this->registerServerRequestErrorResponseGeneratorInContainer($container);

        $factory = new RequestHandlerRunnerFactory();

        $runner = $factory($container->reveal());

        $this->assertInstanceOf(RequestHandlerRunner::class, $runner);
        $this->assertAttributeSame($handler, 'handler', $runner);
        $this->assertAttributeSame($emitter, 'emitter', $runner);
        $this->assertAttributeSame($serverRequestFactory, 'serverRequestFactory', $runner);
        $this->assertAttributeSame($errorGenerator, 'serverRequestErrorResponseGenerator', $runner);
    }

    /**
     * @param ContainerInterface|ObjectProphecy $container
     */
    private function registerHandlerInContainer(ObjectProphecy $container) : RequestHandlerInterface
    {
        $app = $this->prophesize(RequestHandlerInterface::class)->reveal();
        $container->get(ApplicationPipeline::class)->willReturn($app);
        return $app;
    }

    /**
     * @param ContainerInterface|ObjectProphecy $container
     */
    private function registerEmitterInContainer(ObjectProphecy $container) : EmitterInterface
    {
        $emitter = $this->prophesize(EmitterInterface::class)->reveal();
        $container->get(EmitterInterface::class)->willReturn($emitter);
        return $emitter;
    }

    /**
     * @param ContainerInterface|ObjectProphecy $container
     */
    private function registerServerRequestFactoryInContainer(ObjectProphecy $container) : callable
    {
        $factory = function () {
        };
        $container->get(ServerRequestInterface::class)->willReturn($factory);
        return $factory;
    }

    /**
     * @param ContainerInterface|ObjectProphecy $container
     */
    private function registerServerRequestErrorResponseGeneratorInContainer(ObjectProphecy $container) : callable
    {
        $generator = function ($e) {
        };
        $container->get(ServerRequestErrorResponseGenerator::class)->willReturn($generator);
        return $generator;
    }
}
