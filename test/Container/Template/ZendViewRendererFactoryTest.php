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
use Zend\Expressive\Container\Template\ZendViewRendererFactory;
use Zend\Expressive\Router\RouterInterface;
use Zend\Expressive\Template\ZendView;
use Zend\Expressive\Template\ZendViewRenderer;
use Zend\View\HelperPluginManager;
use Zend\View\Model\ModelInterface;
use Zend\View\Resolver\AggregateResolver;
use Zend\View\Resolver\TemplateMapResolver;

class ZendViewRendererFactoryTest extends TestCase
{
    /** @var  ContainerInterface */
    private $container;

    use PathsTrait;

    public function setUp()
    {
        $this->container = $this->prophesize(ContainerInterface::class);
    }

    public function fetchPhpRenderer(ZendViewRenderer $view)
    {
        $r = new ReflectionProperty($view, 'renderer');
        $r->setAccessible(true);
        return $r->getValue($view);
    }

    public function testCallingFactoryWithNoConfigReturnsZendViewInstance()
    {
        $this->container->has('config')->willReturn(false);
        $this->container->has(HelperPluginManager::class)->willReturn(false);
        $factory = new ZendViewRendererFactory();
        $view    = $factory($this->container->reveal());
        $this->assertInstanceOf(ZendViewRenderer::class, $view);
        return $view;
    }

    /**
     * @depends testCallingFactoryWithNoConfigReturnsZendViewInstance
     */
    public function testUnconfiguredZendViewInstanceContainsNoPaths(ZendViewRenderer $view)
    {
        $paths = $view->getPaths();
        $this->assertInternalType('array', $paths);
        $this->assertEmpty($paths);
    }

    public function testConfiguresLayout()
    {
        $config = [
            'templates' => [
                'layout' => 'layout/layout',
            ],
        ];
        $this->container->has('config')->willReturn(true);
        $this->container->get('config')->willReturn($config);
        $this->container->has(HelperPluginManager::class)->willReturn(false);
        $factory = new ZendViewRendererFactory();
        $view = $factory($this->container->reveal());

        $r = new ReflectionProperty($view, 'layout');
        $r->setAccessible(true);
        $layout = $r->getValue($view);
        $this->assertInstanceOf(ModelInterface::class, $layout);
        $this->assertSame($config['templates']['layout'], $layout->getTemplate());
    }

    public function testConfiguresPaths()
    {
        $config = [
            'templates' => [
                'paths' => $this->getConfigurationPaths(),
            ],
        ];
        $this->container->has('config')->willReturn(true);
        $this->container->get('config')->willReturn($config);
        $this->container->has(HelperPluginManager::class)->willReturn(false);
        $factory = new ZendViewRendererFactory();
        $view = $factory($this->container->reveal());

        $paths = $view->getPaths();
        $this->assertPathsHasNamespace('foo', $paths);
        $this->assertPathsHasNamespace('bar', $paths);
        $this->assertPathsHasNamespace(null, $paths);

        $this->assertPathNamespaceCount(1, 'foo', $paths);
        $this->assertPathNamespaceCount(2, 'bar', $paths);
        $this->assertPathNamespaceCount(3, null, $paths);

        $this->assertPathNamespaceContains(__DIR__ . '/TestAsset/bar/', 'foo', $paths, var_export($paths, 1));
        $this->assertPathNamespaceContains(__DIR__ . '/TestAsset/baz/', 'bar', $paths);
        $this->assertPathNamespaceContains(__DIR__ . '/TestAsset/bat/', 'bar', $paths);
        $this->assertPathNamespaceContains(__DIR__ . '/TestAsset/one/', null, $paths);
        $this->assertPathNamespaceContains(__DIR__ . '/TestAsset/two/', null, $paths);
        $this->assertPathNamespaceContains(__DIR__ . '/TestAsset/three/', null, $paths);
    }

    public function testConfiguresTemplateMap()
    {
        $config = [
            'templates' => [
                'map' => [
                    'foo' => 'bar',
                    'bar' => 'baz',
                ],
            ],
        ];
        $this->container->has('config')->willReturn(true);
        $this->container->get('config')->willReturn($config);
        $this->container->has(HelperPluginManager::class)->willReturn(false);
        $factory = new ZendViewRendererFactory();
        $view = $factory($this->container->reveal());

        $r = new ReflectionProperty($view, 'renderer');
        $r->setAccessible(true);
        $renderer  = $r->getValue($view);
        $aggregate = $renderer->resolver();
        $this->assertInstanceOf(AggregateResolver::class, $aggregate);
        $resolver = false;
        foreach ($aggregate as $resolver) {
            if ($resolver instanceof TemplateMapResolver) {
                break;
            }
        }
        $this->assertInstanceOf(TemplateMapResolver::class, $resolver, 'Expected TemplateMapResolver not found!');
        $this->assertTrue($resolver->has('foo'));
        $this->assertEquals('bar', $resolver->get('foo'));
        $this->assertTrue($resolver->has('bar'));
        $this->assertEquals('baz', $resolver->get('bar'));
    }

    public function testInjectsCustomHelpersIntoHelperManager()
    {
        $router = $this->prophesize(RouterInterface::class)->reveal();
        $this->container->has('config')->willReturn(false);
        $this->container->has(HelperPluginManager::class)->willReturn(false);
        $this->container->has(RouterInterface::class)->willReturn(true);
        $this->container->get(RouterInterface::class)->willReturn($router);
        $factory = new ZendViewRendererFactory();
        $view    = $factory($this->container->reveal());
        $this->assertInstanceOf(ZendViewRenderer::class, $view);

        $renderer = $this->fetchPhpRenderer($view);
        $helpers  = $renderer->getHelperPluginManager();
        $this->assertInstanceOf(HelperPluginManager::class, $helpers);
        $this->assertTrue($helpers->has('url'));
        $this->assertTrue($helpers->has('serverurl'));
        $this->assertInstanceOf(ZendView\UrlHelper::class, $helpers->get('url'));
        $this->assertInstanceOf(ZendView\ServerUrlHelper::class, $helpers->get('serverurl'));
    }

    public function testWillUseHelperManagerFromContainer()
    {
        $router = $this->prophesize(RouterInterface::class)->reveal();
        $this->container->has('config')->willReturn(false);
        $this->container->has(RouterInterface::class)->willReturn(true);
        $this->container->get(RouterInterface::class)->willReturn($router);

        $helpers = new HelperPluginManager();
        $this->container->has(HelperPluginManager::class)->willReturn(true);
        $this->container->get(HelperPluginManager::class)->willReturn($helpers);
        $factory = new ZendViewRendererFactory();
        $view    = $factory($this->container->reveal());
        $this->assertInstanceOf(ZendViewRenderer::class, $view);

        $renderer = $this->fetchPhpRenderer($view);
        $this->assertSame($helpers, $renderer->getHelperPluginManager());
        return $helpers;
    }

    /**
     * @depends testWillUseHelperManagerFromContainer
     */
    public function testInjectsCustomHelpersIntoHelperManagerFromContainer(HelperPluginManager $helpers)
    {
        $this->assertTrue($helpers->has('url'));
        $this->assertTrue($helpers->has('serverurl'));
        $this->assertInstanceOf(ZendView\UrlHelper::class, $helpers->get('url'));
        $this->assertInstanceOf(ZendView\ServerUrlHelper::class, $helpers->get('serverurl'));
    }
}
