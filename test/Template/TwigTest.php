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
use Twig_Loader_Filesystem;
use Twig_Environment;
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

    public function testCanPassEngineToConstructor()
    {
        $template = new TwigTemplate($this->twigEnvironment);
        $this->assertInstanceOf(TwigTemplate::class, $template);
        $this->assertEmpty($template->getPaths());
    }

    public function testInstantiatingWithoutEngineLazyLoadsOne()
    {
        $template = new TwigTemplate();
        $this->assertInstanceOf(TwigTemplate::class, $template);
        $this->assertEmpty($template->getPaths());
    }

    public function testCanAddPathWithEmptyNamespace()
    {
        $template = new TwigTemplate();
        $template->addPath(__DIR__ . '/TestAsset');
        $paths = $template->getPaths();
        $this->assertInternalType('array', $paths);
        $this->assertEquals(1, count($paths));
        $this->assertTemplatePath(__DIR__ . '/TestAsset', $paths[0]);
        $this->assertTemplatePathString(__DIR__ . '/TestAsset', $paths[0]);
        $this->assertEmptyTemplatePathNamespace($paths[0]);
    }

    public function testCanAddPathWithNamespace()
    {
        $template = new TwigTemplate();
        $template->addPath(__DIR__ . '/TestAsset', 'test');
        $paths = $template->getPaths();
        $this->assertInternalType('array', $paths);
        $this->assertEquals(1, count($paths));
        $this->assertTemplatePath(__DIR__ . '/TestAsset', $paths[0]);
        $this->assertTemplatePathString(__DIR__ . '/TestAsset', $paths[0]);
        $this->assertTemplatePathNamespace('test', $paths[0]);
    }

    public function testDelegatesRenderingToUnderlyingImplementation()
    {
        $template = new TwigTemplate();
        $template->addPath(__DIR__ . '/TestAsset');
        $name = 'Twig';
        $result = $template->render('twig.html', [ 'name' => $name ]);
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
        $template = new TwigTemplate();
        $this->setExpectedException(Exception\InvalidArgumentException::class);
        $template->render('foo', $params);
    }

    public function testCanRenderWithNullParams()
    {
        $template = new TwigTemplate();
        $template->addPath(__DIR__ . '/TestAsset');
        $result = $template->render('twig-null.html', null);
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
        $template = new TwigTemplate();
        $template->addPath(__DIR__ . '/TestAsset');
        $result = $template->render('twig.html', $params);
        $this->assertContains($search, $result);
        $content = file_get_contents(__DIR__ . '/TestAsset/twig.html');
        $content = str_replace('{{ name }}', $search, $content);
        $this->assertEquals($content, $result);
    }
}
