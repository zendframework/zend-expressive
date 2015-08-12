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
use Zend\Expressive\Template\ZendView;
use Zend\Expressive\Exception;
use Zend\View\Renderer\PhpRenderer;
use Zend\View\Resolver\TemplatePathStack;

class ZendViewTest extends TestCase
{
    use TemplatePathAssertionsTrait;

    public function setUp()
    {
        $this->resolver = new TemplatePathStack;
        $this->render = new PhpRenderer;
        $this->render->setResolver($this->resolver);
    }

    public function testCanPassRendererToConstructor()
    {
        $template = new ZendView($this->render);
        $this->assertInstanceOf(ZendView::class, $template);
        $this->assertAttributeSame($this->render, 'template', $template);
    }

    public function testInstantiatingWithoutEngineLazyLoadsOne()
    {
        $template = new ZendView();
        $this->assertInstanceOf(ZendView::class, $template);
        $this->assertAttributeInstanceOf(PhpRenderer::class, 'template', $template);
    }

    public function testCanAddPathWithEmptyNamespace()
    {
        $template = new ZendView();
        $template->addPath(__DIR__ . '/TestAsset');
        $paths = $template->getPaths();
        $this->assertInternalType('array', $paths);
        $this->assertEquals(1, count($paths));
        $this->assertTemplatePath(__DIR__ . '/TestAsset/', $paths[0]);
        $this->assertTemplatePathString(__DIR__ . '/TestAsset/', $paths[0]);
        $this->assertEmptyTemplatePathNamespace($paths[0]);
    }

    public function testCanAddPathWithNamespace()
    {
        $template = new ZendView();
        $template->addPath(__DIR__ . '/TestAsset', 'test');
        $paths = $template->getPaths();
        $this->assertInternalType('array', $paths);
        $this->assertEquals(1, count($paths));
        $this->assertTemplatePath(__DIR__ . '/TestAsset/', $paths[0]);
        $this->assertTemplatePathString(__DIR__ . '/TestAsset/', $paths[0]);
        $this->assertTemplatePathNamespace('test', $paths[0]);
    }

    public function testDelegatesRenderingToUnderlyingImplementation()
    {
        $template = new ZendView();
        $template->addPath(__DIR__ . '/TestAsset');
        $name = 'ZendView';
        $result = $template->render('zendview', [ 'name' => $name ]);
        $this->assertContains($name, $result);
        $content = file_get_contents(__DIR__ . '/TestAsset/zendview.phtml');
        $content = str_replace('<?php echo $name ?>', $name, $content);
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
        $template = new ZendView();
        $this->setExpectedException(Exception\InvalidArgumentException::class);
        $template->render('foo', $params);
    }

    public function testCanRenderWithNullParams()
    {
        $template = new ZendView();
        $template->addPath(__DIR__ . '/TestAsset');
        $result = $template->render('zendview-null', null);
        $content = file_get_contents(__DIR__ . '/TestAsset/zendview-null.phtml');
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
        $template = new ZendView();
        $template->addPath(__DIR__ . '/TestAsset');
        $result = $template->render('zendview', $params);
        $this->assertContains($search, $result);
        $content = file_get_contents(__DIR__ . '/TestAsset/zendview.phtml');
        $content = str_replace('<?php echo $name ?>', $search, $content);
        $this->assertEquals($content, $result);
    }
}
