<?php
/**
 * @link      http://github.com/zendframework/zend-expressive for the canonical source repository
 * @copyright Copyright (c) 2016 Zend Technologies USA Inc. (http://www.zend.com)
 * @license   http://framework.zend.com/license/new-bsd New BSD License
 */

namespace ZendTest\Expressive\Container;

use Interop\Container\ContainerInterface;
use PHPUnit_Framework_TestCase as TestCase;
use Psr\Http\Message\ResponseInterface;
use Zend\Expressive\Container\NotFoundHandlerFactory;
use Zend\Expressive\Middleware\NotFoundHandler;
use Zend\Expressive\Template\TemplateRendererInterface;

class NotFoundHandlerFactoryTest extends TestCase
{
    public function setUp()
    {
        $this->container = $this->prophesize(ContainerInterface::class);
        $this->renderer  = $this->prophesize(TemplateRendererInterface::class)->reveal();
    }

    public function testReturnsInstanceWithDefaultsWhenNoConfigurationPresent()
    {
        $this->container->has('config')->willReturn(false);
        $this->container->has(TemplateRendererInterface::class)->willReturn(false);

        $factory = new NotFoundHandlerFactory();
        $handler = $factory($this->container->reveal());

        $this->assertInstanceOf(NotFoundHandler::class, $handler);
        $this->assertAttributeInstanceOf(ResponseInterface::class, 'responsePrototype', $handler);
        $this->assertAttributeEmpty('renderer', $handler);
        $this->assertAttributeEquals('error::404', 'template', $handler);
    }

    public function testTemplateIsSetWhenFoundInConfiguration()
    {
        $this->container->has('config')->willReturn(true);
        $this->container->get('config')->willReturn(['zend-expressive' => [
            'error_handler' => [
                'template_404' => 'error::custom404'
            ]
        ]]);
        $this->container->has(TemplateRendererInterface::class)->willReturn(false);

        $factory = new NotFoundHandlerFactory();
        $handler = $factory($this->container->reveal());

        $this->assertInstanceOf(NotFoundHandler::class, $handler);
        $this->assertAttributeInstanceOf(ResponseInterface::class, 'responsePrototype', $handler);
        $this->assertAttributeEmpty('renderer', $handler);
        $this->assertAttributeEquals('error::custom404', 'template', $handler);
    }

    public function testSetsRendererWhenServiceIsConfigured()
    {
        $this->container->has('config')->willReturn(false);
        $this->container->has(TemplateRendererInterface::class)->willReturn(true);
        $this->container->get(TemplateRendererInterface::class)->willReturn($this->renderer);

        $factory = new NotFoundHandlerFactory();
        $handler = $factory($this->container->reveal());

        $this->assertInstanceOf(NotFoundHandler::class, $handler);
        $this->assertAttributeInstanceOf(ResponseInterface::class, 'responsePrototype', $handler);
        $this->assertAttributeSame($this->renderer, 'renderer', $handler);
        $this->assertAttributeEquals('error::404', 'template', $handler);
    }
}
