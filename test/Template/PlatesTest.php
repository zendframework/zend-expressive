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
use Zend\Expressive\Template\Plates as PlatesTemplate;

class PlatesTest extends TestCase
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
        $templateRenderer = new PlatesTemplate($this->platesEngine);
        $this->assertInstanceOf(PlatesTemplate::class, $templateRenderer);
        $this->assertEmpty($templateRenderer->getPaths());
    }

    public function testLazyLoadsEngineAtInstantiationIfNoneProvided()
    {
        $templateRenderer = new PlatesTemplate();
        $this->assertInstanceOf(PlatesTemplate::class, $templateRenderer);
        $this->assertEmpty($templateRenderer->getPaths());
    }

    public function testCanAddPath()
    {
        $templateRenderer = new PlatesTemplate();
        $templateRenderer->addPath(__DIR__ . '/TestAsset');
        $paths = $templateRenderer->getPaths();
        $this->assertInternalType('array', $paths);
        $this->assertEquals(1, count($paths));
        $this->assertTemplatePath(__DIR__ . '/TestAsset', $paths[0]);
        $this->assertTemplatePathString(__DIR__ . '/TestAsset', $paths[0]);
        $this->assertEmptyTemplatePathNamespace($paths[0]);
        return $templateRenderer;
    }

    /**
     * @param PlatesTemplate $templateRenderer
     * @depends testCanAddPath
     */
    public function testAddingSecondPathWithoutNamespaceIsANoopAndRaisesWarning($templateRenderer)
    {
        $paths = $templateRenderer->getPaths();
        $path  = array_shift($paths);

        set_error_handler(function ($error, $message) {
            $this->error = true;
            $this->assertContains('duplicate', $message);
            return true;
        }, E_USER_WARNING);
        $templateRenderer->addPath(__DIR__);
        restore_error_handler();

        $this->assertTrue($this->error, 'Error handler was not triggered when calling addPath() multiple times');

        $paths = $templateRenderer->getPaths();
        $this->assertInternalType('array', $paths);
        $this->assertEquals(1, count($paths));
        $test = array_shift($paths);
        $this->assertEqualTemplatePath($path, $test);
    }

    public function testCanAddPathWithNamespace()
    {
        $templateRenderer = new PlatesTemplate();
        $templateRenderer->addPath(__DIR__ . '/TestAsset', 'test');
        $paths = $templateRenderer->getPaths();
        $this->assertInternalType('array', $paths);
        $this->assertEquals(1, count($paths));
        $this->assertTemplatePath(__DIR__ . '/TestAsset', $paths[0]);
        $this->assertTemplatePathString(__DIR__ . '/TestAsset', $paths[0]);
        $this->assertTemplatePathNamespace('test', $paths[0]);
    }

    public function testDelegatesRenderingToUnderlyingImplementation()
    {
        $templateRenderer = new PlatesTemplate();
        $templateRenderer->addPath(__DIR__ . '/TestAsset');
        $name = 'Plates';
        $result = $templateRenderer->render('plates', [ 'name' => $name ]);
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
        $templateRenderer = new PlatesTemplate();
        $this->setExpectedException(Exception\InvalidArgumentException::class);
        $templateRenderer->render('foo', $params);
    }

    public function testCanRenderWithNullParams()
    {
        $templateRenderer = new PlatesTemplate();
        $templateRenderer->addPath(__DIR__ . '/TestAsset');
        $result = $templateRenderer->render('plates-null', null);
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
        $templateRenderer = new PlatesTemplate();
        $templateRenderer->addPath(__DIR__ . '/TestAsset');
        $result = $templateRenderer->render('plates', $params);
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
        $templateRenderer = new PlatesTemplate();
        $templateRenderer->addPath(__DIR__ . '/TestAsset/test', 'test');

        $expected = file_get_contents(__DIR__ . '/TestAsset/test/test.php');
        $test     = $templateRenderer->render('test::test');

        $this->assertSame($expected, $test);
    }
}
