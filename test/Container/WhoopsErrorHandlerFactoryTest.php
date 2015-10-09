<?php
/**
 * Zend Framework (http://framework.zend.com/)
 *
 * @see       https://github.com/zendframework/zend-expressive for the canonical source repository
 * @copyright Copyright (c) 2015 Zend Technologies USA Inc. (http://www.zend.com)
 * @license   https://github.com/zendframework/zend-expressive/blob/master/LICENSE.md New BSD License
 */

namespace ZendTest\Expressive\Container;

use PHPUnit_Framework_TestCase as TestCase;
use Whoops\Handler\PrettyPageHandler;
use Whoops\Run as Whoops;
use Zend\Expressive\Container\WhoopsErrorHandlerFactory;
use Zend\Expressive\Template\TemplateRendererInterface;
use Zend\Expressive\WhoopsErrorHandler;

class WhoopsErrorHandlerFactoryTest extends TestCase
{
    /**
     * @var \Interop\Container\ContainerInterface
     */
    private $container;

    public function setUp()
    {
        $whoops      = $this->prophesize(Whoops::class);
        $pageHandler = $this->prophesize(PrettyPageHandler::class);
        $this->container = $this->prophesize('Interop\Container\ContainerInterface');
        $this->container->get('Zend\Expressive\WhoopsPageHandler')->willReturn($pageHandler->reveal());
        $this->container->get('Zend\Expressive\Whoops')->willReturn($whoops->reveal());

        $this->factory   = new WhoopsErrorHandlerFactory();
    }

    public function testReturnsAWhoopsErrorHandler()
    {
        $this->container->has(TemplateRendererInterface::class)->willReturn(false);
        $this->container->has('config')->willReturn(false);

        $factory = $this->factory;
        $result  = $factory($this->container->reveal());
        $this->assertInstanceOf(WhoopsErrorHandler::class, $result);
    }

    public function testWillInjectTemplateIntoErrorHandlerWhenServiceIsPresent()
    {
        $renderer = $this->prophesize(TemplateRendererInterface::class);
        $this->container->has(TemplateRendererInterface::class)->willReturn(true);
        $this->container->get(TemplateRendererInterface::class)->willReturn($renderer->reveal());
        $this->container->has('config')->willReturn(false);

        $factory = $this->factory;
        $result  = $factory($this->container->reveal());
        $this->assertInstanceOf(WhoopsErrorHandler::class, $result);
        $this->assertAttributeInstanceOf(TemplateRendererInterface::class, 'renderer', $result);
    }

    public function testWillInjectTemplateNamesFromConfigurationWhenPresent()
    {
        $config = ['zend-expressive' => ['error_handler' => [
            'template_404'   => 'error::404',
            'template_error' => 'error::500',
        ]]];
        $this->container->has(TemplateRendererInterface::class)->willReturn(false);
        $this->container->has('config')->willReturn(true);
        $this->container->get('config')->willReturn($config);

        $factory = $this->factory;
        $result  = $factory($this->container->reveal());
        $this->assertInstanceOf(WhoopsErrorHandler::class, $result);
        $this->assertAttributeEquals('error::404', 'template404', $result);
        $this->assertAttributeEquals('error::500', 'templateError', $result);
    }
}
