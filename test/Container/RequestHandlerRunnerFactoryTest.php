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
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use ReflectionProperty;
use RuntimeException;
use Throwable;
use Zend\Expressive\ApplicationPipeline;
use Zend\Expressive\Container\RequestHandlerRunnerFactory;
use Zend\Expressive\Response\ServerRequestErrorResponseGenerator;
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

        $this->assertAttributeNotSame($serverRequestFactory, 'serverRequestFactory', $runner);
        $this->assertAttributeNotSame($errorGenerator, 'serverRequestErrorResponseGenerator', $runner);

        $r = new ReflectionProperty($runner, 'serverRequestFactory');
        $r->setAccessible(true);
        $toTest = $r->getValue($runner);
        $this->assertSame($serverRequestFactory(), $toTest());

        $r = new ReflectionProperty($runner, 'serverRequestErrorResponseGenerator');
        $r->setAccessible(true);
        $toTest = $r->getValue($runner);
        $e = new RuntimeException();
        $this->assertSame($errorGenerator($e), $toTest($e));
    }

    public function registerHandlerInContainer($container) : RequestHandlerInterface
    {
        $app = $this->prophesize(RequestHandlerInterface::class)->reveal();
        $container->get(ApplicationPipeline::class)->willReturn($app);
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
        $request = $this->prophesize(ServerRequestInterface::class)->reveal();
        $factory = function () use ($request) {
            return $request;
        };
        $container->get(ServerRequestInterface::class)->willReturn($factory);
        return $factory;
    }

    public function registerServerRequestErrorResponseGeneratorInContainer($container) : callable
    {
        $response = $this->prophesize(ResponseInterface::class)->reveal();
        $generator = $this->prophesize(ServerRequestErrorResponseGenerator::class);
        $generator->__invoke(Argument::type(Throwable::class))->willReturn($response);
        $container->get(ServerRequestErrorResponseGenerator::class)->willReturn($generator->reveal());
        return $generator->reveal();
    }
}
