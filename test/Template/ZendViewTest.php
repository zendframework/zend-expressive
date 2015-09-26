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
use Zend\Expressive\Exception;
use Zend\Expressive\Template\ZendView;
use Zend\View\Model\ViewModel;
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
        $templateRenderer = new ZendView($this->render);
        $this->assertInstanceOf(ZendView::class, $templateRenderer);
        $this->assertAttributeSame($this->render, 'renderer', $templateRenderer);
    }

    public function testInstantiatingWithoutEngineLazyLoadsOne()
    {
        $templateRenderer = new ZendView();
        $this->assertInstanceOf(ZendView::class, $templateRenderer);
        $this->assertAttributeInstanceOf(PhpRenderer::class, 'renderer', $templateRenderer);
    }

    public function testCanAddPathWithEmptyNamespace()
    {
        $templateRenderer = new ZendView();
        $templateRenderer->addPath(__DIR__ . '/TestAsset');
        $paths = $templateRenderer->getPaths();
        $this->assertInternalType('array', $paths);
        $this->assertEquals(1, count($paths));
        $this->assertTemplatePath(__DIR__ . '/TestAsset/', $paths[0]);
        $this->assertTemplatePathString(__DIR__ . '/TestAsset/', $paths[0]);
        $this->assertEmptyTemplatePathNamespace($paths[0]);
    }

    public function testCanAddPathWithNamespace()
    {
        $templateRenderer = new ZendView();
        $templateRenderer->addPath(__DIR__ . '/TestAsset', 'test');
        $paths = $templateRenderer->getPaths();
        $this->assertInternalType('array', $paths);
        $this->assertEquals(1, count($paths));
        $this->assertTemplatePath(__DIR__ . '/TestAsset/', $paths[0]);
        $this->assertTemplatePathString(__DIR__ . '/TestAsset/', $paths[0]);
        $this->assertTemplatePathNamespace('test', $paths[0]);
    }

    public function testDelegatesRenderingToUnderlyingImplementation()
    {
        $templateRenderer = new ZendView();
        $templateRenderer->addPath(__DIR__ . '/TestAsset');
        $name = 'ZendView';
        $result = $templateRenderer->render('zendview', [ 'name' => $name ]);
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
        $templateRenderer = new ZendView();
        $this->setExpectedException(Exception\InvalidArgumentException::class);
        $templateRenderer->render('foo', $params);
    }

    public function testCanRenderWithNullParams()
    {
        $templateRenderer = new ZendView();
        $templateRenderer->addPath(__DIR__ . '/TestAsset');
        $result = $templateRenderer->render('zendview-null', null);
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
        $templateRenderer = new ZendView();
        $templateRenderer->addPath(__DIR__ . '/TestAsset');
        $result = $templateRenderer->render('zendview', $params);
        $this->assertContains($search, $result);
        $content = file_get_contents(__DIR__ . '/TestAsset/zendview.phtml');
        $content = str_replace('<?php echo $name ?>', $search, $content);
        $this->assertEquals($content, $result);
    }

    /**
     * @group layout
     */
    public function testWillRenderContentInLayoutPassedToConstructor()
    {
        $templateRenderer = new ZendView(null, 'zendview-layout');
        $templateRenderer->addPath(__DIR__ . '/TestAsset');
        $name = 'ZendView';
        $result = $templateRenderer->render('zendview', [ 'name' => $name ]);
        $this->assertContains($name, $result);
        $content = file_get_contents(__DIR__ . '/TestAsset/zendview.phtml');
        $content = str_replace('<?php echo $name ?>', $name, $content);
        $this->assertContains($content, $result);
        $this->assertContains('<title>Layout Page</title>', $result, sprintf("Received %s", $result));
    }

    /**
     * @group layout
     */
    public function testWillRenderContentInLayoutPassedDuringRendering()
    {
        $templateRenderer = new ZendView(null);
        $templateRenderer->addPath(__DIR__ . '/TestAsset');
        $name = 'ZendView';
        $result = $templateRenderer->render('zendview', [ 'name' => $name, 'layout' => 'zendview-layout' ]);
        $this->assertContains($name, $result);
        $content = file_get_contents(__DIR__ . '/TestAsset/zendview.phtml');
        $content = str_replace('<?php echo $name ?>', $name, $content);
        $this->assertContains($content, $result);

        $this->assertContains('<title>Layout Page</title>', $result);
    }

    /**
     * @group layout
     */
    public function testLayoutPassedWhenRenderingOverridesLayoutPassedToConstructor()
    {
        $templateRenderer = new ZendView(null, 'zendview-layout');
        $templateRenderer->addPath(__DIR__ . '/TestAsset');
        $name = 'ZendView';
        $result = $templateRenderer->render('zendview', [ 'name' => $name, 'layout' => 'zendview-layout2' ]);
        $this->assertContains($name, $result);
        $content = file_get_contents(__DIR__ . '/TestAsset/zendview.phtml');
        $content = str_replace('<?php echo $name ?>', $name, $content);
        $this->assertContains($content, $result);

        $this->assertContains('<title>ALTERNATE LAYOUT PAGE</title>', $result);
    }

    /**
     * @group layout
     */
    public function testCanPassViewModelForLayoutToConstructor()
    {
        $layout = new ViewModel();
        $layout->setTemplate('zendview-layout');

        $templateRenderer = new ZendView(null, $layout);
        $templateRenderer->addPath(__DIR__ . '/TestAsset');
        $name = 'ZendView';
        $result = $templateRenderer->render('zendview', [ 'name' => $name ]);
        $this->assertContains($name, $result);
        $content = file_get_contents(__DIR__ . '/TestAsset/zendview.phtml');
        $content = str_replace('<?php echo $name ?>', $name, $content);
        $this->assertContains($content, $result);
        $this->assertContains('<title>Layout Page</title>', $result, sprintf("Received %s", $result));
    }

    /**
     * @group layout
     */
    public function testCanPassViewModelForLayoutParameterWhenRendering()
    {
        $layout = new ViewModel();
        $layout->setTemplate('zendview-layout2');

        $templateRenderer = new ZendView(null, 'zendview-layout');
        $templateRenderer->addPath(__DIR__ . '/TestAsset');
        $name = 'ZendView';
        $result = $templateRenderer->render('zendview', [ 'name' => $name, 'layout' => $layout ]);
        $this->assertContains($name, $result);
        $content = file_get_contents(__DIR__ . '/TestAsset/zendview.phtml');
        $content = str_replace('<?php echo $name ?>', $name, $content);
        $this->assertContains($content, $result);
        $this->assertContains('<title>ALTERNATE LAYOUT PAGE</title>', $result);
    }

    /**
     * @group namespacing
     */
    public function testProperlyResolvesNamespacedTemplate()
    {
        $templateRenderer = new ZendView();
        $templateRenderer->addPath(__DIR__ . '/TestAsset/test', 'test');

        $expected = file_get_contents(__DIR__ . '/TestAsset/test/test.phtml');
        $test     = $templateRenderer->render('test::test');

        $this->assertSame($expected, $test);
    }
}
