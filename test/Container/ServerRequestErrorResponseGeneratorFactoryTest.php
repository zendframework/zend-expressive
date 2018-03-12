<?php
/**
 * @see       https://github.com/zendframework/zend-expressive for the canonical source repository
 * @copyright Copyright (c) 2018 Zend Technologies USA Inc. (https://www.zend.com)
 * @license   https://github.com/zendframework/zend-expressive/blob/master/LICENSE.md New BSD License
 */

declare(strict_types=1);

namespace ZendTest\Expressive\Container;

use Closure;
use PHPUnit\Framework\TestCase;
use Psr\Container\ContainerInterface;
use Psr\Http\Message\ResponseInterface;
use RuntimeException;
use Zend\Expressive\Container\ServerRequestErrorResponseGeneratorFactory;
use Zend\Expressive\Response\ServerRequestErrorResponseGenerator;
use Zend\Expressive\Template\TemplateRendererInterface;

class ServerRequestErrorResponseGeneratorFactoryTest extends TestCase
{
    public function testFactoryOnlyRequiresResponseService()
    {
        $container = $this->prophesize(ContainerInterface::class);
        $container->has('config')->willReturn(false);
        $container->get('config')->shouldNotBeCalled();
        $container->has(TemplateRendererInterface::class)->willReturn(false);
        $container->get(TemplateRendererInterface::class)->shouldNotBeCalled();

        $exception = new RuntimeException();
        $container->get(ResponseInterface::class)->willThrow($exception);

        $factory = new ServerRequestErrorResponseGeneratorFactory();

        $this->expectException(RuntimeException::class);
        $factory($container->reveal());
    }

    public function testFactoryCreatesGeneratorWhenOnlyResponseServiceIsPresent()
    {
        $container = $this->prophesize(ContainerInterface::class);
        $container->has('config')->willReturn(false);
        $container->get('config')->shouldNotBeCalled();
        $container->has(TemplateRendererInterface::class)->willReturn(false);
        $container->get(TemplateRendererInterface::class)->shouldNotBeCalled();

        $responseFactory = function () {
        };
        $container->get(ResponseInterface::class)->willReturn($responseFactory);

        $factory = new ServerRequestErrorResponseGeneratorFactory();

        $generator = $factory($container->reveal());

        $this->assertAttributeNotSame($responseFactory, 'responseFactory', $generator);
        $this->assertAttributeInstanceOf(Closure::class, 'responseFactory', $generator);
        $this->assertAttributeSame(false, 'debug', $generator);
        $this->assertAttributeEmpty('renderer', $generator);
        $this->assertAttributeSame(ServerRequestErrorResponseGenerator::TEMPLATE_DEFAULT, 'template', $generator);
    }

    public function testFactoryCreatesGeneratorUsingConfiguredServices()
    {
        $config = [
            'debug' => true,
            'zend-expressive' => [
                'error_handler' => [
                    'template_error' => 'some::template',
                ],
            ],
        ];
        $renderer = $this->prophesize(TemplateRendererInterface::class)->reveal();

        $container = $this->prophesize(ContainerInterface::class);
        $container->has('config')->willReturn(true);
        $container->get('config')->willReturn($config);
        $container->has(TemplateRendererInterface::class)->willReturn(true);
        $container->get(TemplateRendererInterface::class)->willReturn($renderer);

        $responseFactory = function () {
        };
        $container->get(ResponseInterface::class)->willReturn($responseFactory);

        $factory = new ServerRequestErrorResponseGeneratorFactory();

        $generator = $factory($container->reveal());

        $this->assertAttributeNotSame($responseFactory, 'responseFactory', $generator);
        $this->assertAttributeInstanceOf(Closure::class, 'responseFactory', $generator);
        $this->assertAttributeSame(true, 'debug', $generator);
        $this->assertAttributeSame($renderer, 'renderer', $generator);
        $this->assertAttributeSame(
            $config['zend-expressive']['error_handler']['template_error'],
            'template',
            $generator
        );
    }
}
