<?php
/**
 * @see       https://github.com/zendframework/zend-expressive for the canonical source repository
 * @copyright Copyright (c) 2016-2017 Zend Technologies USA Inc. (http://www.zend.com)
 * @license   https://github.com/zendframework/zend-expressive/blob/master/LICENSE.md New BSD License
 */

namespace ZendTest\Expressive\Container;

use PHPUnit\Framework\TestCase;
use Prophecy\Prophecy\ObjectProphecy;
use Psr\Container\ContainerInterface;
use Zend\Expressive\Container\ErrorResponseGeneratorFactory;
use Zend\Expressive\Middleware\ErrorResponseGenerator;
use Zend\Expressive\Template\TemplateRendererInterface;

class ErrorResponseGeneratorFactoryTest extends TestCase
{
    /** @var ContainerInterface|ObjectProphecy */
    private $container;

    /** @var TemplateRendererInterface|ObjectProphecy */
    private $renderer;

    public function setUp()
    {
        $this->container = $this->prophesize(ContainerInterface::class);
        $this->renderer  = $this->prophesize(TemplateRendererInterface::class);
    }

    public function testNoConfigurationCreatesInstanceWithDefaults()
    {
        $this->container->has('config')->willReturn(false);
        $this->container->has(TemplateRendererInterface::class)->willReturn(false);
        $factory = new ErrorResponseGeneratorFactory();

        $generator = $factory($this->container->reveal());

        $this->assertInstanceOf(ErrorResponseGenerator::class, $generator);
        $this->assertAttributeEquals(false, 'debug', $generator);
        $this->assertAttributeEmpty('renderer', $generator);
        $this->assertAttributeEquals('error::error', 'template', $generator);
    }

    public function testUsesDebugConfigurationToSetDebugFlag()
    {
        $this->container->has('config')->willReturn(true);
        $this->container->get('config')->willReturn(['debug' => true]);
        $this->container->has(TemplateRendererInterface::class)->willReturn(false);
        $factory = new ErrorResponseGeneratorFactory();

        $generator = $factory($this->container->reveal());

        $this->assertAttributeEquals(true, 'debug', $generator);
        $this->assertAttributeEmpty('renderer', $generator);
        $this->assertAttributeEquals('error::error', 'template', $generator);
    }

    public function testUsesConfiguredTemplateRenderToSetGeneratorRenderer()
    {
        $this->container->has('config')->willReturn(false);
        $this->container->has(TemplateRendererInterface::class)->willReturn(true);
        $this->container->get(TemplateRendererInterface::class)->will([$this->renderer, 'reveal']);
        $factory = new ErrorResponseGeneratorFactory();

        $generator = $factory($this->container->reveal());

        $this->assertAttributeEquals(false, 'debug', $generator);
        $this->assertAttributeSame($this->renderer->reveal(), 'renderer', $generator);
        $this->assertAttributeEquals('error::error', 'template', $generator);
    }

    public function testUsesTemplateConfigurationToSetTemplate()
    {
        $this->container->has('config')->willReturn(true);
        $this->container->get('config')->willReturn([
            'zend-expressive' => [
                'error_handler' => [
                    'template_error' => 'error::custom',
                ],
            ],
        ]);
        $this->container->has(TemplateRendererInterface::class)->willReturn(false);
        $factory = new ErrorResponseGeneratorFactory();

        $generator = $factory($this->container->reveal());

        $this->assertAttributeEquals(false, 'debug', $generator);
        $this->assertAttributeEmpty('renderer', $generator);
        $this->assertAttributeEquals('error::custom', 'template', $generator);
    }
}
