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
use Zend\Expressive\Template\TemplatePath;

class PlatesTest extends TestCase
{
    use TemplatePathAssertionsTrait;

    public function setUp()
    {
        $this->error = false;
        $this->platesEngine = new Engine();
    }

    public function testCanProvideEngineAtInstantiation()
    {
        $template = new PlatesTemplate($this->platesEngine);
        $this->assertInstanceOf(PlatesTemplate::class, $template);
        $this->assertEmpty($template->getPaths());
    }

    public function testLazyLoadsEngineAtInstantiationIfNoneProvided()
    {
        $template = new PlatesTemplate();
        $this->assertInstanceOf(PlatesTemplate::class, $template);
        $this->assertEmpty($template->getPaths());
    }

    public function testCanAddPath()
    {
        $template = new PlatesTemplate();
        $template->addPath(__DIR__ . '/TestAsset');
        $paths = $template->getPaths();
        $this->assertInternalType('array', $paths);
        $this->assertEquals(1, count($paths));
        $this->assertTemplatePath(__DIR__ . '/TestAsset', $paths[0]);
        $this->assertTemplatePathString(__DIR__ . '/TestAsset', $paths[0]);
        $this->assertEmptyTemplatePathNamespace($paths[0]);
        return $template;
    }

    /**
     * @depends testCanAddPath
     */
    public function testAddingSecondPathWithoutNamespaceIsANoopAndRaisesWarning($template)
    {
        $paths = $template->getPaths();
        $path  = array_shift($paths);

        set_error_handler(function ($error, $message) {
            $this->error = true;
            $this->assertContains('duplicate', $message);
            return true;
        }, E_USER_WARNING);
        $template->addPath(__DIR__);
        restore_error_handler();

        $this->assertTrue($this->error, 'Error handler was not triggered when calling addPath() multiple times');

        $paths = $template->getPaths();
        $this->assertInternalType('array', $paths);
        $this->assertEquals(1, count($paths));
        $test = array_shift($paths);
        $this->assertEqualTemplatePath($path, $test);
    }

    public function testCanAddPathWithNamespace()
    {
        $template = new PlatesTemplate();
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
        $template = new PlatesTemplate();
        $template->addPath(__DIR__ . '/TestAsset');
        $name = 'Plates';
        $result = $template->render('plates', [ 'name' => $name ]);
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
        $template = new PlatesTemplate();
        $this->setExpectedException(Exception\InvalidArgumentException::class);
        $template->render('foo', $params);
    }

    public function testCanRenderWithNullParams()
    {
        $template = new PlatesTemplate();
        $template->addPath(__DIR__ . '/TestAsset');
        $result = $template->render('plates-null', null);
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
        $template = new PlatesTemplate();
        $template->addPath(__DIR__ . '/TestAsset');
        $result = $template->render('plates', $params);
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
        $template = new PlatesTemplate();
        $template->addPath(__DIR__ . '/TestAsset/test', 'test');

        $expected = file_get_contents(__DIR__ . '/TestAsset/test/test.php');
        $test     = $template->render('test::test');

        $this->assertSame($expected, $test);
    }

    public function testAddParameterToOneTemplate()
    {
        $template = new PlatesTemplate();
        $template->addPath(__DIR__ . '/TestAsset');
        $name = 'Plates';
        $template->addParameters(['name' => $name], 'plates');
        $result = $template->render('plates');
        $content = file_get_contents(__DIR__ . '/TestAsset/plates.php');
        $content= str_replace('<?=$this->e($name)?>', $name, $content);
        $this->assertEquals($content, $result);

        // @fixme hack to work around https://github.com/thephpleague/plates/issues/60, remove if ever merged
        set_error_handler(function ($error, $message) {
            $this->assertContains('Undefined variable: name', $message);
            return true;
        }, E_NOTICE);
        $template->render('plates-2');
        restore_error_handler();

        $content= str_replace('<?=$this->e($name)?>', '', $content);
        $this->assertEquals($content, $result);
    }

    public function testAddSharedParameters()
    {
        $template = new PlatesTemplate();
        $template->addPath(__DIR__ . '/TestAsset');
        $name = 'Plates';
        $template->addParameters(['name' => $name]);
        $result = $template->render('plates');
        $content = file_get_contents(__DIR__ . '/TestAsset/plates.php');
        $content= str_replace('<?=$this->e($name)?>', $name, $content);
        $this->assertEquals($content, $result);
        $result = $template->render('plates-2');
        $content = file_get_contents(__DIR__ . '/TestAsset/plates-2.php');
        $content= str_replace('<?=$this->e($name)?>', $name, $content);
        $this->assertEquals($content, $result);
    }

    public function testOverrideSharedParametersPerTemplate()
    {
        $template = new PlatesTemplate();
        $template->addPath(__DIR__ . '/TestAsset');
        $name = 'Plates';
        $name2 = 'Saucers';
        $template->addParameters(['name' => $name]);
        $template->addParameters(['name' => $name2], 'plates-2');
        $result = $template->render('plates');
        $content = file_get_contents(__DIR__ . '/TestAsset/plates.php');
        $content= str_replace('<?=$this->e($name)?>', $name, $content);
        $this->assertEquals($content, $result);
        $result = $template->render('plates-2');
        $content = file_get_contents(__DIR__ . '/TestAsset/plates-2.php');
        $content= str_replace('<?=$this->e($name)?>', $name2, $content);
        $this->assertEquals($content, $result);
    }

    public function testOverrideSharedParametersAtRender()
    {
        $template = new PlatesTemplate();
        $template->addPath(__DIR__ . '/TestAsset');
        $name = 'Plates';
        $name2 = 'Saucers';
        $template->addParameters(['name' => $name]);
        $result = $template->render('plates', ['name' => $name2]);
        $content = file_get_contents(__DIR__ . '/TestAsset/plates.php');
        $content= str_replace('<?=$this->e($name)?>', $name2, $content);
        $this->assertEquals($content, $result);
    }
}
