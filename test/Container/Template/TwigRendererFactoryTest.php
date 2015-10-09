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
use Zend\Expressive\Container\Template\TwigRendererFactory;
use Zend\Expressive\Router\RouterInterface;
use Zend\Expressive\Template\TwigRenderer;
use Zend\Expressive\Template\Twig\TwigExtension;

class TwigFactoryTest extends TestCase
{
    /** @var  ContainerInterface */
    private $container;

    use PathsTrait;

    public function setUp()
    {
        $this->container = $this->prophesize(ContainerInterface::class);
    }

    public function fetchTwigEnvironment(TwigRenderer $twig)
    {
        $r = new ReflectionProperty($twig, 'template');
        $r->setAccessible(true);
        return $r->getValue($twig);
    }

    public function testCallingFactoryWithNoConfigReturnsTwigInstance()
    {
        $this->container->has('config')->willReturn(false);
        $this->container->has(RouterInterface::class)->willReturn(false);
        $factory = new TwigRendererFactory();
        $twig = $factory($this->container->reveal());
        $this->assertInstanceOf(TwigRenderer::class, $twig);
        return $twig;
    }

    /**
     * @depends testCallingFactoryWithNoConfigReturnsTwigInstance
     */
    public function testUnconfiguredTwigInstanceContainsNoPaths(TwigRenderer $twig)
    {
        $paths = $twig->getPaths();
        $this->assertInternalType('array', $paths);
        $this->assertEmpty($paths);
    }

    public function testUsesDebugConfigurationToPrepareEnvironment()
    {
        $config = ['debug' => true];
        $this->container->has('config')->willReturn(true);
        $this->container->get('config')->willReturn($config);
        $this->container->has(RouterInterface::class)->willReturn(false);
        $factory = new TwigRendererFactory();
        $twig = $factory($this->container->reveal());

        $environment = $this->fetchTwigEnvironment($twig);

        $this->assertTrue($environment->isDebug());
        $this->assertFalse($environment->getCache());
        $this->assertTrue($environment->isStrictVariables());
        $this->assertTrue($environment->isAutoReload());
    }

    /**
     * @depends testCallingFactoryWithNoConfigReturnsTwigInstance
     */
    public function testDebugDisabledSetsUpEnvironmentForProduction(TwigRenderer $twig)
    {
        $environment = $this->fetchTwigEnvironment($twig);

        $this->assertFalse($environment->isDebug());
        $this->assertFalse($environment->isStrictVariables());
        $this->assertFalse($environment->isAutoReload());
    }

    public function testCanSpecifyCacheDirectoryViaConfiguration()
    {
        $config = ['templates' => ['cache_dir' => __DIR__]];
        $this->container->has('config')->willReturn(true);
        $this->container->get('config')->willReturn($config);
        $this->container->has(RouterInterface::class)->willReturn(false);
        $factory = new TwigRendererFactory();
        $twig = $factory($this->container->reveal());

        $environment = $this->fetchTwigEnvironment($twig);
        $this->assertEquals($config['templates']['cache_dir'], $environment->getCache());
    }

    public function testAddsTwigExtensionIfRouterIsInContainer()
    {
        $router = $this->prophesize(RouterInterface::class)->reveal();
        $this->container->has('config')->willReturn(false);
        $this->container->has(RouterInterface::class)->willReturn(true);
        $this->container->get(RouterInterface::class)->willReturn($router);
        $factory = new TwigRendererFactory();
        $twig = $factory($this->container->reveal());

        $environment = $this->fetchTwigEnvironment($twig);
        $this->assertTrue($environment->hasExtension('zend-expressive'));
    }

    public function testUsesAssetsConfigurationWhenAddingTwigExtension()
    {
        $config = [
            'templates' => [
                'assets_url'     => 'http://assets.example.com/',
                'assets_version' => 'XYZ',
            ],
        ];
        $router = $this->prophesize(RouterInterface::class)->reveal();
        $this->container->has('config')->willReturn(true);
        $this->container->get('config')->willReturn($config);
        $this->container->has(RouterInterface::class)->willReturn(true);
        $this->container->get(RouterInterface::class)->willReturn($router);
        $factory = new TwigRendererFactory();
        $twig = $factory($this->container->reveal());

        $environment = $this->fetchTwigEnvironment($twig);
        $extension = $environment->getExtension('zend-expressive');
        $this->assertInstanceOf(TwigExtension::class, $extension);
        $this->assertAttributeEquals($config['templates']['assets_url'], 'assetsUrl', $extension);
        $this->assertAttributeEquals($config['templates']['assets_version'], 'assetsVersion', $extension);
        $this->assertAttributeSame($router, 'router', $extension);
    }

    public function testConfiguresTemplateSuffix()
    {
        $config = [
            'templates' => [
                'extension' => 'tpl',
            ],
        ];
        $this->container->has('config')->willReturn(true);
        $this->container->get('config')->willReturn($config);
        $this->container->has(RouterInterface::class)->willReturn(false);
        $factory = new TwigRendererFactory();
        $twig = $factory($this->container->reveal());

        $this->assertAttributeSame($config['templates']['extension'], 'suffix', $twig);
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
        $this->container->has(RouterInterface::class)->willReturn(false);
        $factory = new TwigRendererFactory();
        $twig = $factory($this->container->reveal());

        $paths = $twig->getPaths();
        $this->assertPathsHasNamespace('foo', $paths);
        $this->assertPathsHasNamespace('bar', $paths);
        $this->assertPathsHasNamespace(null, $paths);

        $this->assertPathNamespaceCount(1, 'foo', $paths);
        $this->assertPathNamespaceCount(2, 'bar', $paths);
        $this->assertPathNamespaceCount(3, null, $paths);

        $this->assertPathNamespaceContains(__DIR__ . '/TestAsset/bar', 'foo', $paths);
        $this->assertPathNamespaceContains(__DIR__ . '/TestAsset/baz', 'bar', $paths);
        $this->assertPathNamespaceContains(__DIR__ . '/TestAsset/bat', 'bar', $paths);
        $this->assertPathNamespaceContains(__DIR__ . '/TestAsset/one', null, $paths);
        $this->assertPathNamespaceContains(__DIR__ . '/TestAsset/two', null, $paths);
        $this->assertPathNamespaceContains(__DIR__ . '/TestAsset/three', null, $paths);
    }
}
