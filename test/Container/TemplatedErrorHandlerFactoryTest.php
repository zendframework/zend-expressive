<?php
/**
 * Zend Framework (http://framework.zend.com/)
 *
 * @see       https://github.com/zendframework/zend-expressive for the canonical source repository
 * @copyright Copyright (c) 2015-2016 Zend Technologies USA Inc. (http://www.zend.com)
 * @license   https://github.com/zendframework/zend-expressive/blob/master/LICENSE.md New BSD License
 */

namespace ZendTest\Expressive\Container;

use PHPUnit_Framework_TestCase as TestCase;
use Prophecy\Prophecy\ObjectProphecy;
use Zend\Expressive\Container\TemplatedErrorHandlerFactory;
use Zend\Expressive\Template\TemplateRendererInterface;
use Zend\Expressive\TemplatedErrorHandler;
use ZendTest\Expressive\ContainerTrait;

/**
 * @covers Zend\Expressive\Container\TemplatedErrorHandlerFactory
 */
class TemplatedErrorHandlerFactoryTest extends TestCase
{
    use ContainerTrait;

    /** @var ObjectProphecy */
    protected $container;

    public function setUp()
    {
        $this->container = $this->mockContainerInterface();
        $this->factory   = new TemplatedErrorHandlerFactory();
    }

    public function testReturnsATemplatedErrorHandler()
    {
        $factory = $this->factory;
        $result  = $factory($this->container->reveal());
        $this->assertInstanceOf(TemplatedErrorHandler::class, $result);
    }

    public function testWillInjectTemplateIntoErrorHandlerWhenServiceIsPresent()
    {
        $renderer = $this->prophesize(TemplateRendererInterface::class);
        $this->injectServiceInContainer($this->container, TemplateRendererInterface::class, $renderer->reveal());

        $factory = $this->factory;
        $result  = $factory($this->container->reveal());
        $this->assertInstanceOf(TemplatedErrorHandler::class, $result);
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
        $this->assertInstanceOf(TemplatedErrorHandler::class, $result);
        $this->assertAttributeEquals('error::404', 'template404', $result);
        $this->assertAttributeEquals('error::500', 'templateError', $result);
    }
}
