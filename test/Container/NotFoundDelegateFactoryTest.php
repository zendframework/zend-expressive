<?php
/**
 * @see       https://github.com/zendframework/zend-expressive for the canonical source repository
 * @copyright Copyright (c) 2017 Zend Technologies USA Inc. (http://www.zend.com)
 * @license   https://github.com/zendframework/zend-expressive/blob/master/LICENSE.md New BSD License
 */

namespace ZendTest\Expressive\Container;

use PHPUnit\Framework\TestCase;
use Prophecy\Prophecy\ObjectProphecy;
use Psr\Container\ContainerInterface;
use Zend\Diactoros\Response;
use Zend\Expressive\Container\NotFoundDelegateFactory;
use Zend\Expressive\Delegate\NotFoundDelegate;
use Zend\Expressive\Template\TemplateRendererInterface;

class NotFoundDelegateFactoryTest extends TestCase
{
    /** @var ContainerInterface|ObjectProphecy */
    private $container;

    protected function setUp()
    {
        $this->container = $this->prophesize(ContainerInterface::class);
    }

    public function testFactoryCreatesInstanceWithoutRendererIfRendererServiceIsMissing()
    {
        $this->container->has('config')->willReturn(false);
        $this->container->has(TemplateRendererInterface::class)->willReturn(false);
        $factory = new NotFoundDelegateFactory();

        $delegate = $factory($this->container->reveal());
        $this->assertInstanceOf(NotFoundDelegate::class, $delegate);
        $this->assertAttributeInstanceOf(Response::class, 'responsePrototype', $delegate);
        $this->assertAttributeEmpty('renderer', $delegate);
    }

    public function testFactoryCreatesInstanceUsingRendererServiceWhenPresent()
    {
        $renderer = $this->prophesize(TemplateRendererInterface::class)->reveal();
        $this->container->has('config')->willReturn(false);
        $this->container->has(TemplateRendererInterface::class)->willReturn(true);
        $this->container->get(TemplateRendererInterface::class)->willReturn($renderer);
        $factory = new NotFoundDelegateFactory();

        $delegate = $factory($this->container->reveal());
        $this->assertAttributeSame($renderer, 'renderer', $delegate);
    }

    public function testFactoryUsesConfigured404TemplateWhenPresent()
    {
        $config = [
            'zend-expressive' => [
                'error_handler' => [
                    'template_404' => 'foo::bar',
                ],
            ],
        ];
        $this->container->has('config')->willReturn(true);
        $this->container->get('config')->willReturn($config);
        $this->container->has(TemplateRendererInterface::class)->willReturn(false);
        $factory = new NotFoundDelegateFactory();

        $delegate = $factory($this->container->reveal());
        $this->assertAttributeEquals(
            $config['zend-expressive']['error_handler']['template_404'],
            'template',
            $delegate
        );
    }
}
