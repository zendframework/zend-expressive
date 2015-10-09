<?php
/**
 * Zend Framework (http://framework.zend.com/)
 *
 * @see       https://github.com/zendframework/zend-expressive for the canonical source repository
 * @copyright Copyright (c) 2015 Zend Technologies USA Inc. (http://www.zend.com)
 * @license   https://github.com/zendframework/zend-expressive/blob/master/LICENSE.md New BSD License
 */

namespace ZendTest\Expressive\Container\Template;

use Interop\Container\ContainerInterface;
use PHPUnit_Framework_TestCase as TestCase;
use ReflectionProperty;
use Zend\Expressive\Container\Template\PlatesRendererFactory;
use Zend\Expressive\Template\PlatesRenderer;

class PlatesRendererFactoryTest extends TestCase
{
    use PathsTrait;

    /** @var  ContainerInterface */
    private $container;
    /** @var bool */
    public $errorCaught = false;

    public function setUp()
    {
        $this->errorCaught = false;
        $this->container = $this->prophesize(ContainerInterface::class);
    }

    public function fetchPlatesEngine(PlatesRenderer $plates)
    {
        $r = new ReflectionProperty($plates, 'template');
        $r->setAccessible(true);
        return $r->getValue($plates);
    }

    public function testCallingFactoryWithNoConfigReturnsPlatesInstance()
    {
        $this->container->has('config')->willReturn(false);
        $factory = new PlatesRendererFactory();
        $plates = $factory($this->container->reveal());
        $this->assertInstanceOf(PlatesRenderer::class, $plates);
        return $plates;
    }

    /**
     * @depends testCallingFactoryWithNoConfigReturnsPlatesInstance
     */
    public function testUnconfiguredPlatesInstanceContainsNoPaths(PlatesRenderer $plates)
    {
        $paths = $plates->getPaths();
        $this->assertInternalType('array', $paths);
        $this->assertEmpty($paths);
    }

    public function testConfiguresTemplateSuffix()
    {
        $config = [
            'templates' => [
                'extension' => 'html',
            ],
        ];
        $this->container->has('config')->willReturn(true);
        $this->container->get('config')->willReturn($config);
        $factory = new PlatesRendererFactory();
        $plates = $factory($this->container->reveal());

        $engine = $this->fetchPlatesEngine($plates);
        $r = new ReflectionProperty($engine, 'fileExtension');
        $r->setAccessible(true);
        $extension = $r->getValue($engine);
        $this->assertAttributeSame($config['templates']['extension'], 'fileExtension', $extension);
    }

    public function testExceptionIsRaisedIfMultiplePathsSpecifyDefaultNamespace()
    {
        $config = [
            'templates' => [
                'paths' => [
                    0 => __DIR__ . '/TestAsset/bar',
                    1 => __DIR__ . '/TestAsset/baz',
                ]
            ],
        ];
        $this->container->has('config')->willReturn(true);
        $this->container->get('config')->willReturn($config);
        $factory = new PlatesRendererFactory();

        $reset = set_error_handler(function ($errno, $errstr) {
            $this->errorCaught = true;
        }, E_USER_WARNING);
        $plates = $factory($this->container->reveal());
        restore_error_handler();
        $this->assertTrue($this->errorCaught, 'Did not detect duplicate path for default namespace');
    }

    public function testExceptionIsRaisedIfMultiplePathsInSameNamespace()
    {
        $config = [
            'templates' => [
                'paths' => [
                    'bar' => [
                        __DIR__ . '/TestAsset/baz',
                        __DIR__ . '/TestAsset/bat',
                    ],
                ],
            ],
        ];
        $this->container->has('config')->willReturn(true);
        $this->container->get('config')->willReturn($config);
        $factory = new PlatesRendererFactory();

        $this->setExpectedException('LogicException', 'already being used');
        $plates = $factory($this->container->reveal());
    }

    public function testConfiguresPaths()
    {
        $config = [
            'templates' => [
                'paths' => [
                    'foo' => __DIR__ . '/TestAsset/bar',
                    1 => __DIR__ . '/TestAsset/one',
                    'bar' => __DIR__ . '/TestAsset/baz',
                ],
            ],
        ];
        $this->container->has('config')->willReturn(true);
        $this->container->get('config')->willReturn($config);
        $factory = new PlatesRendererFactory();
        $plates = $factory($this->container->reveal());

        $paths = $plates->getPaths();
        $this->assertPathsHasNamespace('foo', $paths);
        $this->assertPathsHasNamespace('bar', $paths);
        $this->assertPathsHasNamespace(null, $paths);

        $this->assertPathNamespaceCount(1, 'foo', $paths);
        $this->assertPathNamespaceCount(1, 'bar', $paths);
        $this->assertPathNamespaceCount(1, null, $paths);

        $this->assertPathNamespaceContains(__DIR__ . '/TestAsset/bar', 'foo', $paths);
        $this->assertPathNamespaceContains(__DIR__ . '/TestAsset/baz', 'bar', $paths);
        $this->assertPathNamespaceContains(__DIR__ . '/TestAsset/one', null, $paths);
    }
}
