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
use League\Plates\Engine;
use PHPUnit_Framework_TestCase as TestCase;
use Zend\Expressive\Exception;
use Zend\Expressive\Template\PlatesRenderer;

class PlatesRendererTest extends TestCase
{
    /**
     * @var Engine
     */
    private $platesEngine;

    /**
     * @var bool
     */
    private $error;

    use TemplatePathAssertionsTrait;

    public function setUp()
    {
        $this->error = false;
        $this->platesEngine = new Engine();
    }

    public function testCanProvideEngineAtInstantiation()
    {
        $renderer = new PlatesRenderer($this->platesEngine);
        $this->assertInstanceOf(PlatesRenderer::class, $renderer);
        $this->assertEmpty($renderer->getPaths());
    }

    public function testLazyLoadsEngineAtInstantiationIfNoneProvided()
    {
        $renderer = new PlatesRenderer();
        $this->assertInstanceOf(PlatesRenderer::class, $renderer);
        $this->assertEmpty($renderer->getPaths());
    }

    public function testCanAddPath()
    {
        $renderer = new PlatesRenderer();
        $renderer->addPath(__DIR__ . '/TestAsset');
        $paths = $renderer->getPaths();
        $this->assertInternalType('array', $paths);
        $this->assertEquals(1, count($paths));
        $this->assertTemplatePath(__DIR__ . '/TestAsset', $paths[0]);
        $this->assertTemplatePathString(__DIR__ . '/TestAsset', $paths[0]);
        $this->assertEmptyTemplatePathNamespace($paths[0]);
        return $renderer;
    }

    /**
     * @param PlatesRenderer $renderer
     * @depends testCanAddPath
     */
    public function testAddingSecondPathWithoutNamespaceIsANoopAndRaisesWarning($renderer)
    {
        $paths = $renderer->getPaths();
        $path  = array_shift($paths);

        set_error_handler(function ($error, $message) {
            $this->error = true;
            $this->assertContains('duplicate', $message);
            return true;
        }, E_USER_WARNING);
        $renderer->addPath(__DIR__);
        restore_error_handler();

        $this->assertTrue($this->error, 'Error handler was not triggered when calling addPath() multiple times');

        $paths = $renderer->getPaths();
        $this->assertInternalType('array', $paths);
        $this->assertEquals(1, count($paths));
        $test = array_shift($paths);
        $this->assertEqualTemplatePath($path, $test);
    }

    public function testCanAddPathWithNamespace()
    {
        $renderer = new PlatesRenderer();
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
        $renderer = new PlatesRenderer();
        $renderer->addPath(__DIR__ . '/TestAsset');
        $name = 'Plates';
        $result = $renderer->render('plates', [ 'name' => $name ]);
        $this->assertContains($name, $result);
        $content = file_get_contents(__DIR__ . '/TestAsset/plates.php');
        $content = str_replace('<?=$this->e($name)?>', $name, $content);
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
        $renderer = new PlatesRenderer();
        $this->setExpectedException(Exception\InvalidArgumentException::class);
        $renderer->render('foo', $params);
    }

    public function testCanRenderWithNullParams()
    {
        $renderer = new PlatesRenderer();
        $renderer->addPath(__DIR__ . '/TestAsset');
        $result = $renderer->render('plates-null', null);
        $content = file_get_contents(__DIR__ . '/TestAsset/plates-null.php');
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
        $renderer = new PlatesRenderer();
        $renderer->addPath(__DIR__ . '/TestAsset');
        $result = $renderer->render('plates', $params);
        $this->assertContains($search, $result);
        $content = file_get_contents(__DIR__ . '/TestAsset/plates.php');
        $content = str_replace('<?=$this->e($name)?>', $search, $content);
        $this->assertEquals($content, $result);
    }

    /**
     * @group namespacing
     */
    public function testProperlyResolvesNamespacedTemplate()
    {
        $renderer = new PlatesRenderer();
        $renderer->addPath(__DIR__ . '/TestAsset/test', 'test');

        $expected = file_get_contents(__DIR__ . '/TestAsset/test/test.php');
        $test     = $renderer->render('test::test');

        $this->assertSame($expected, $test);
    }

    public function testAddParameterToOneTemplate()
    {
        $renderer = new PlatesRenderer();
        $renderer->addPath(__DIR__ . '/TestAsset');
        $name = 'Plates';
        $renderer->addDefaultParam('plates', 'name', $name);
        $result = $renderer->render('plates');
        $content = file_get_contents(__DIR__ . '/TestAsset/plates.php');
        $content= str_replace('<?=$this->e($name)?>', $name, $content);
        $this->assertEquals($content, $result);

        // @fixme hack to work around https://github.com/thephpleague/plates/issues/60, remove if ever merged
        set_error_handler(function ($error, $message) {
            $this->assertContains('Undefined variable: name', $message);
            return true;
        }, E_NOTICE);
        $renderer->render('plates-2');
        restore_error_handler();

        $content= str_replace('<?=$this->e($name)?>', '', $content);
        $this->assertEquals($content, $result);
    }

    public function testAddSharedParameters()
    {
        $renderer = new PlatesRenderer();
        $renderer->addPath(__DIR__ . '/TestAsset');
        $name = 'Plates';
        $renderer->addDefaultParam($renderer::TEMPLATE_ALL, 'name', $name);
        $result = $renderer->render('plates');
        $content = file_get_contents(__DIR__ . '/TestAsset/plates.php');
        $content= str_replace('<?=$this->e($name)?>', $name, $content);
        $this->assertEquals($content, $result);
        $result = $renderer->render('plates-2');
        $content = file_get_contents(__DIR__ . '/TestAsset/plates-2.php');
        $content= str_replace('<?=$this->e($name)?>', $name, $content);
        $this->assertEquals($content, $result);
    }

    public function testOverrideSharedParametersPerTemplate()
    {
        $renderer = new PlatesRenderer();
        $renderer->addPath(__DIR__ . '/TestAsset');
        $name = 'Plates';
        $name2 = 'Saucers';
        $renderer->addDefaultParam($renderer::TEMPLATE_ALL, 'name', $name);
        $renderer->addDefaultParam('plates-2', 'name', $name2);
        $result = $renderer->render('plates');
        $content = file_get_contents(__DIR__ . '/TestAsset/plates.php');
        $content= str_replace('<?=$this->e($name)?>', $name, $content);
        $this->assertEquals($content, $result);
        $result = $renderer->render('plates-2');
        $content = file_get_contents(__DIR__ . '/TestAsset/plates-2.php');
        $content= str_replace('<?=$this->e($name)?>', $name2, $content);
        $this->assertEquals($content, $result);
    }

    public function testOverrideSharedParametersAtRender()
    {
        $renderer = new PlatesRenderer();
        $renderer->addPath(__DIR__ . '/TestAsset');
        $name = 'Plates';
        $name2 = 'Saucers';
        $renderer->addDefaultParam($renderer::TEMPLATE_ALL, 'name', $name);
        $result = $renderer->render('plates', ['name' => $name2]);
        $content = file_get_contents(__DIR__ . '/TestAsset/plates.php');
        $content= str_replace('<?=$this->e($name)?>', $name2, $content);
        $this->assertEquals($content, $result);
    }
}
