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
use Whoops\Handler\JsonResponseHandler;
use Whoops\Handler\PrettyPageHandler;
use Whoops\Run as Whoops;
use Zend\Expressive\Container\WhoopsErrorHandlerFactory;
use Zend\Expressive\Template\TemplateInterface;
use Zend\Expressive\WhoopsErrorHandler;

class WhoopsErrorHandlerFactoryTest extends TestCase
{
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
        $this->container->has(TemplateInterface::class)->willReturn(false);
        $this->container->has('Config')->willReturn(false);

        $factory = $this->factory;
        $result  = $factory($this->container->reveal());
        $this->assertInstanceOf(WhoopsErrorHandler::class, $result);
    }

    public function testWillInjectTemplateIntoErrorHandlerWhenServiceIsPresent()
    {
        $template = $this->prophesize(TemplateInterface::class);
        $this->container->has(TemplateInterface::class)->willReturn(true);
        $this->container->get(TemplateInterface::class)->willReturn($template->reveal());
        $this->container->has('Config')->willReturn(false);

        $factory = $this->factory;
        $result  = $factory($this->container->reveal());
        $this->assertInstanceOf(WhoopsErrorHandler::class, $result);
        $this->assertAttributeInstanceOf(TemplateInterface::class, 'template', $result);
    }

    public function testWillInjectTemplateNamesFromConfigurationWhenPresent()
    {
        $config = ['zend-expressive' => ['error_handler' => [
            'template_404'   => 'error::404',
            'template_error' => 'error::500',
        ]]];
        $this->container->has(TemplateInterface::class)->willReturn(false);
        $this->container->has('Config')->willReturn(true);
        $this->container->get('Config')->willReturn($config);

        $factory = $this->factory;
        $result  = $factory($this->container->reveal());
        $this->assertInstanceOf(WhoopsErrorHandler::class, $result);
        $this->assertAttributeEquals('error::404', 'template404', $result);
        $this->assertAttributeEquals('error::500', 'templateError', $result);
    }
}
