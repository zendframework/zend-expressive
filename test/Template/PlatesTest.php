<?php
/**
 * Zend Framework (http://framework.zend.com/)
 *
 * @see       https://github.com/zendframework/zend-expressive for the canonical source repository
 * @copyright Copyright (c) 2015 Zend Technologies USA Inc. (http://www.zend.com)
 * @license   https://github.com/zendframework/zend-expressive/blob/master/LICENSE.md New BSD License
 */

namespace ZendTest\Expressive\Template;

use PHPUnit_Framework_TestCase as TestCase;
use Zend\Expressive\Template\Plates as PlatesTemplate;
use League\Plates\Engine;
use Zend\Expressive\Template\TemplatePath;

class PlatesTest extends TestCase
{
    public function setUp()
    {
        $this->platesEngine = new Engine();
    }

    public function testConstructorWithEngine()
    {
        $template = new PlatesTemplate($this->platesEngine);
        $this->assertTrue($template instanceof PlatesTemplate);
        $this->assertEmpty($template->getPaths());
    }

    public function testConstructorWithoutEngine()
    {
        $template = new PlatesTemplate();
        $this->assertTrue($template instanceof PlatesTemplate);
        $this->assertEmpty($template->getPaths());
    }

    public function testSetPath()
    {
        $template = new PlatesTemplate();
        $template->addPath(__DIR__ . '/TestAsset');
        $paths = $template->getPaths();
        $this->assertTrue(is_array($paths));
        $this->assertEquals(1, count($paths));
        $this->assertTrue($paths[0] instanceof TemplatePath);
        $this->assertEquals($paths[0]->getPath(), __DIR__ . '/TestAsset');
        $this->assertEquals((string) $paths[0], __DIR__ . '/TestAsset');
        $this->assertEmpty($paths[0]->getNamespace());
    }

    public function testRender()
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
}
