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
use ReflectionFunction;
use ReflectionProperty;
use Whoops\Handler\PrettyPageHandler;
use Zend\Expressive\Container\WhoopsPageHandlerFactory;
use Zend\Expressive\Container\Exception\InvalidServiceException;

class WhoopsPageHandlerFactoryTest extends TestCase
{
    public function setUp()
    {
        $this->container = $this->prophesize('Interop\Container\ContainerInterface');
        $this->factory   = new WhoopsPageHandlerFactory();
    }

    public function testReturnsAPrettyPageHandler()
    {
        $this->container->has('Config')->willReturn(false);
        $factory = $this->factory;

        $result = $factory($this->container->reveal());
        $this->assertInstanceOf(PrettyPageHandler::class, $result);
    }

    public function testWillInjectStringEditor()
    {
        $config = ['whoops' => ['editor' => 'emacs']];
        $this->container->has('Config')->willReturn(true);
        $this->container->get('Config')->willReturn($config);
        $this->container->has('emacs')->willReturn(false);

        $factory = $this->factory;
        $result = $factory($this->container->reveal());
        $this->assertInstanceOf(PrettyPageHandler::class, $result);
        $this->assertAttributeEquals($config['whoops']['editor'], 'editor', $result);
    }

    public function testWillInjectCallableEditor()
    {
        $config = ['whoops' => ['editor' => function () {
        }]];
        $this->container->has('Config')->willReturn(true);
        $this->container->get('Config')->willReturn($config);
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
        $this->container->has('Config')->willReturn(true);
        $this->container->get('Config')->willReturn($config);
        $this->container->has('custom')->willReturn(true);
        $this->container->get('custom')->willReturn($editor);

        $factory = $this->factory;
        $result = $factory($this->container->reveal());
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
     */
    public function testInvalidEditorWillRaiseException($editor)
    {
        $config = ['whoops' => ['editor' => $editor]];
        $this->container->has('Config')->willReturn(true);
        $this->container->get('Config')->willReturn($config);

        $factory = $this->factory;

        $this->setExpectedException(InvalidServiceException::class);
        $factory($this->container->reveal());
    }
}
