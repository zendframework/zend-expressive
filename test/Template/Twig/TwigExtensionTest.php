<?php
/**
 * Zend Framework (http://framework.zend.com/)
 *
 * @see       https://github.com/zendframework/zend-expressive for the canonical source repository
 * @copyright Copyright (c) 2015 Zend Technologies USA Inc. (http://www.zend.com)
 * @license   https://github.com/zendframework/zend-expressive/blob/master/LICENSE.md New BSD License
 */

namespace Zend\Expressive\Template\Twig;

use PHPUnit_Framework_TestCase as TestCase;
use Twig_SimpleFunction as SimpleFunction;
use Zend\Expressive\Router\RouterInterface;
use Zend\Expressive\Template\Twig\TwigExtension;

class TwigExtensionTest extends TestCase
{
    public function setUp()
    {
        $this->router = $this->prophesize(RouterInterface::class);
    }

    public function createExtension($assetsUrl, $assetsVersion)
    {
        return new TwigExtension($this->router->reveal(), $assetsUrl, $assetsVersion);
    }

    public function findFunction($name, array $functions)
    {
        foreach ($functions as $function) {
            $this->assertInstanceOf(SimpleFunction::class, $function);
            if ($function->getName() === $name) {
                return $function;
            }
        }
        return false;
    }

    public function assertFunctionExists($name, array $functions, $message = null)
    {
        $message  = $message ?: sprintf('Failed to identify function by name %s', $name);
        $function = $this->findFunction($name, $functions);
        $this->assertInstanceOf(SimpleFunction::class, $function, $message);
    }

    public function testExtensionIsNamed()
    {
        $extension = $this->createExtension('', '');
        $this->assertEquals('zend-expressive', $extension->getName());
    }

    public function testRegistersTwigFunctionsForPathAndAsset()
    {
        $extension = $this->createExtension('', '');
        $functions = $extension->getFunctions();
        $this->assertFunctionExists('path', $functions);
        $this->assertFunctionExists('asset', $functions);
    }

    public function testMapsTwigFunctionsToExpectedMethods()
    {
        $extension = $this->createExtension('', '');
        $functions = $extension->getFunctions();
        $this->assertSame(
            [$extension, 'renderUri'],
            $this->findFunction('path', $functions)->getCallable(),
            'Received different path function than expected'
        );
        $this->assertSame(
            [$extension, 'renderAssetUrl'],
            $this->findFunction('asset', $functions)->getCallable(),
            'Received different asset function than expected'
        );
    }

    public function testRenderUriDelegatesToComposedRouter()
    {
        $this->router->generateUri('foo', ['id' => 1])->willReturn('URL');
        $extension = $this->createExtension('', '');
        $this->assertSame('URL', $extension->renderUri('foo', ['id' => 1]));
    }

    public function testRenderAssetUrlUsesComposedAssetUrlAndVersionToGenerateUrl()
    {
        $extension = $this->createExtension('https://images.example.com/', 'XYZ');
        $this->assertSame('https://images.example.com/foo.png?v=XYZ', $extension->renderAssetUrl('foo.png'));
    }

    public function testRenderAssetUrlUsesProvidedVersionToGenerateUrl()
    {
        $extension = $this->createExtension('https://images.example.com/', 'XYZ');
        $this->assertSame(
            'https://images.example.com/foo.png?v=ABC',
            $extension->renderAssetUrl('foo.png', null, false, 'ABC')
        );
    }
}
