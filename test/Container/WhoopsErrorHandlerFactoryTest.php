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
use Prophecy\Prophecy\ObjectProphecy;
use Whoops\Handler\PrettyPageHandler;
use Whoops\Run as Whoops;
use Zend\Expressive\Container\WhoopsErrorHandlerFactory;
use Zend\Expressive\Template\TemplateRendererInterface;
use Zend\Expressive\WhoopsErrorHandler;
use ZendTest\Expressive\ContainerTrait;

/**
 * @covers Zend\Expressive\Container\WhoopsErrorHandlerFactory
 */
class WhoopsErrorHandlerFactoryTest extends TestCase
{
    use ContainerTrait;

    /** @var ObjectProphecy */
    protected $container;

    public function setUp()
    {
        $whoops      = $this->prophesize(Whoops::class);
        $pageHandler = $this->prophesize(PrettyPageHandler::class);
        $this->container = $this->mockContainerInterface();
        $this->injectServiceInContainer($this->container, 'Zend\Expressive\WhoopsPageHandler', $pageHandler->reveal());
        $this->injectServiceInContainer($this->container, 'Zend\Expressive\Whoops', $whoops->reveal());

        $this->factory   = new WhoopsErrorHandlerFactory();
    }

    public function testReturnsAWhoopsErrorHandler()
    {
        $factory = $this->factory;
        $result  = $factory($this->container->reveal());
        $this->assertInstanceOf(WhoopsErrorHandler::class, $result);
    }

    public function testWillInjectTemplateIntoErrorHandlerWhenServiceIsPresent()
    {
        $renderer = $this->prophesize(TemplateRendererInterface::class);
        $this->injectServiceInContainer($this->container, TemplateRendererInterface::class, $renderer->reveal());

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
        $this->injectServiceInContainer($this->container, 'config', $config);

        $factory = $this->factory;
        $result  = $factory($this->container->reveal());
        $this->assertInstanceOf(WhoopsErrorHandler::class, $result);
        $this->assertAttributeEquals('error::404', 'template404', $result);
        $this->assertAttributeEquals('error::500', 'templateError', $result);
    }
}
