<?php
/**
 * @see       https://github.com/zendframework/zend-expressive for the canonical source repository
 * @copyright Copyright (c) 2015-2017 Zend Technologies USA Inc. (http://www.zend.com)
 * @license   https://github.com/zendframework/zend-expressive/blob/master/LICENSE.md New BSD License
 */

namespace ZendTest\Expressive\Container;

use PHPUnit\Framework\TestCase;
use Prophecy\Prophecy\ObjectProphecy;
use Psr\Container\ContainerInterface;
use Whoops\Handler\PrettyPageHandler;
use Zend\Expressive\Container\Exception\InvalidServiceException;
use Zend\Expressive\Container\WhoopsPageHandlerFactory;
use ZendTest\Expressive\ContainerTrait;

/**
 * @covers Zend\Expressive\Container\WhoopsPageHandlerFactory
 */
class WhoopsPageHandlerFactoryTest extends TestCase
{
    use ContainerTrait;

    /** @var ContainerInterface|ObjectProphecy */
    private $container;

    /** @var WhoopsPageHandlerFactory */
    private $factory;

    public function setUp()
    {
        $this->container = $this->mockContainerInterface();
        $this->factory   = new WhoopsPageHandlerFactory();
    }

    public function testReturnsAPrettyPageHandler()
    {
        $factory = $this->factory;

        $result = $factory($this->container->reveal());
        $this->assertInstanceOf(PrettyPageHandler::class, $result);
    }

    public function testWillInjectStringEditor()
    {
        $config = ['whoops' => ['editor' => 'emacs']];
        $this->injectServiceInContainer($this->container, 'config', $config);

        $factory = $this->factory;
        $result  = $factory($this->container->reveal());
        $this->assertInstanceOf(PrettyPageHandler::class, $result);
        $this->assertAttributeEquals($config['whoops']['editor'], 'editor', $result);
    }

    public function testWillInjectCallableEditor()
    {
        $config = [
            'whoops' => [
                'editor' => function () {
                },
            ],
        ];
        $this->injectServiceInContainer($this->container, 'config', $config);
        $factory = $this->factory;

        $result = $factory($this->container->reveal());
        $this->assertInstanceOf(PrettyPageHandler::class, $result);
        $this->assertAttributeSame($config['whoops']['editor'], 'editor', $result);
    }

    public function testWillInjectEditorAsAService()
    {
        $config = ['whoops' => ['editor' => 'custom']];
        $editor = function () {
        };
        $this->injectServiceInContainer($this->container, 'config', $config);
        $this->injectServiceInContainer($this->container, 'custom', $editor);

        $factory = $this->factory;
        $result  = $factory($this->container->reveal());
        $this->assertInstanceOf(PrettyPageHandler::class, $result);
        $this->assertAttributeSame($editor, 'editor', $result);
    }

    public function invalidEditors()
    {
        return [
            'true'       => [true],
            'false'      => [false],
            'zero'       => [0],
            'int'        => [1],
            'zero-float' => [0.0],
            'float'      => [1.1],
            'array'      => [['emacs']],
            'object'     => [(object) ['editor' => 'emacs']],
        ];
    }

    /**
     * @dataProvider invalidEditors
     *
     * @param mixed $editor
     */
    public function testInvalidEditorWillRaiseException($editor)
    {
        $config = ['whoops' => ['editor' => $editor]];
        $this->injectServiceInContainer($this->container, 'config', $config);

        $factory = $this->factory;

        $this->expectException(InvalidServiceException::class);
        $factory($this->container->reveal());
    }
}
