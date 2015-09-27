<?php
/**
 * Zend Framework (http://framework.zend.com/)
 *
 * @see       https://github.com/zendframework/zend-expressive for the canonical source repository
 * @copyright Copyright (c) 2015 Zend Technologies USA Inc. (http://www.zend.com)
 * @license   https://github.com/zendframework/zend-expressive/blob/master/LICENSE.md New BSD License
 */

namespace ZendTest\Expressive\Template;

use ArrayObject;
use PHPUnit_Framework_TestCase as TestCase;
use Twig_Environment;
use Twig_Loader_Filesystem;
use Zend\Expressive\Exception;
use Zend\Expressive\Template\TemplatePath;
use Zend\Expressive\Template\Twig as TwigTemplate;

class TwigTest extends TestCase
{
    use TemplatePathAssertionsTrait;

    public function setUp()
    {
        $this->twigFilesystem  = new Twig_Loader_Filesystem;
        $this->twigEnvironment = new Twig_Environment($this->twigFilesystem);
    }

    public function testShouldInjectDefaultLoaderIfProvidedEnvironmentDoesNotComposeOne()
    {
        $twigEnvironment  = new Twig_Environment();
        $renderer = new TwigTemplate($twigEnvironment);
        $loader           = $twigEnvironment->getLoader();
        $this->assertInstanceOf('Twig_Loader_Filesystem', $loader);
    }

    public function testCanPassEngineToConstructor()
    {
        $renderer = new TwigTemplate($this->twigEnvironment);
        $this->assertInstanceOf(TwigTemplate::class, $renderer);
        $this->assertEmpty($renderer->getPaths());
    }

    public function testInstantiatingWithoutEngineLazyLoadsOne()
    {
        $renderer = new TwigTemplate();
        $this->assertInstanceOf(TwigTemplate::class, $renderer);
        $this->assertEmpty($renderer->getPaths());
    }

    public function testCanAddPathWithEmptyNamespace()
    {
        $renderer = new TwigTemplate();
        $renderer->addPath(__DIR__ . '/TestAsset');
        $paths = $renderer->getPaths();
        $this->assertInternalType('array', $paths);
        $this->assertEquals(1, count($paths));
        $this->assertTemplatePath(__DIR__ . '/TestAsset', $paths[0]);
        $this->assertTemplatePathString(__DIR__ . '/TestAsset', $paths[0]);
        $this->assertEmptyTemplatePathNamespace($paths[0]);
    }

    public function testCanAddPathWithNamespace()
    {
        $renderer = new TwigTemplate();
        $renderer->addPath(__DIR__ . '/TestAsset', 'test');
        $paths = $renderer->getPaths();
        $this->assertInternalType('array', $paths);
        $this->assertEquals(1, count($paths));
        $this->assertTemplatePath(__DIR__ . '/TestAsset', $paths[0]);
        $this->assertTemplatePathString(__DIR__ . '/TestAsset', $paths[0]);
        $this->assertTemplatePathNamespace('test', $paths[0]);
    }

    public function testDelegatesRenderingToUnderlyingImplementation()
    {
        $renderer = new TwigTemplate();
        $renderer->addPath(__DIR__ . '/TestAsset');
        $name = 'Twig';
        $result = $renderer->render('twig.html', [ 'name' => $name ]);
        $this->assertContains($name, $result);
        $content = file_get_contents(__DIR__ . '/TestAsset/twig.html');
        $content = str_replace('{{ name }}', $name, $content);
        $this->assertEquals($content, $result);
    }

    public function invalidParameterValues()
    {
        return [
            'true'       => [true],
            'false'      => [false],
            'zero'       => [0],
            'int'        => [1],
            'zero-float' => [0.0],
            'float'      => [1.1],
            'string'     => ['value'],
        ];
    }

    /**
     * @dataProvider invalidParameterValues
     */
    public function testRenderRaisesExceptionForInvalidParameterTypes($params)
    {
        $renderer = new TwigTemplate();
        $this->setExpectedException(Exception\InvalidArgumentException::class);
        $renderer->render('foo', $params);
    }

    public function testCanRenderWithNullParams()
    {
        $renderer = new TwigTemplate();
        $renderer->addPath(__DIR__ . '/TestAsset');
        $result = $renderer->render('twig-null.html', null);
        $content = file_get_contents(__DIR__ . '/TestAsset/twig-null.html');
        $this->assertEquals($content, $result);
    }

    public function objectParameterValues()
    {
        $names = [
            'stdClass'    => uniqid(),
            'ArrayObject' => uniqid(),
        ];

        return [
            'stdClass'    => [(object) ['name' => $names['stdClass']], $names['stdClass']],
            'ArrayObject' => [new ArrayObject(['name' => $names['ArrayObject']]), $names['ArrayObject']],
        ];
    }

    /**
     * @dataProvider objectParameterValues
     */
    public function testCanRenderWithParameterObjects($params, $search)
    {
        $renderer = new TwigTemplate();
        $renderer->addPath(__DIR__ . '/TestAsset');
        $result = $renderer->render('twig.html', $params);
        $this->assertContains($search, $result);
        $content = file_get_contents(__DIR__ . '/TestAsset/twig.html');
        $content = str_replace('{{ name }}', $search, $content);
        $this->assertEquals($content, $result);
    }

    /**
     * @group namespacing
     */
    public function testProperlyResolvesNamespacedTemplate()
    {
        $renderer = new TwigTemplate();
        $renderer->addPath(__DIR__ . '/TestAsset/test', 'test');

        $expected = file_get_contents(__DIR__ . '/TestAsset/test/test.html');
        $test     = $renderer->render('test::test');

        $this->assertSame($expected, $test);
    }

    /**
     * @group namespacing
     */
    public function testResolvesNamespacedTemplateWithSuffix()
    {
        $renderer = new TwigTemplate();
        $renderer->addPath(__DIR__ . '/TestAsset/test', 'test');

        $expected = file_get_contents(__DIR__ . '/TestAsset/test/test.js');
        $test     = $renderer->render('test::test.js');

        $this->assertSame($expected, $test);
    }
}
