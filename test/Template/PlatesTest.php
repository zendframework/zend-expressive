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

class PlatesTest extends TestCase
{
    public function setUp()
    {
        $this->platesEngine = $this->prophesize('League\Plates\Engine');
    }

    public function testConstructorWithEngine()
    {
        $template = new PlatesTemplate($this->platesEngine->reveal());
        $this->assertTrue($template instanceof PlatesTemplate);
        $this->assertEmpty($template->getPath());
    }

    public function testConstructorWithoutEngine()
    {
        $template = new PlatesTemplate();
        $this->assertTrue($template instanceof PlatesTemplate);
        $this->assertEmpty($template->getPath());
    }

    public function testSetPath()
    {
        $template = new PlatesTemplate();
        $template->setPath(__DIR__ . '/TestAsset');
        $this->assertTrue($template instanceof PlatesTemplate);
        $this->assertEquals(__DIR__ . '/TestAsset', $template->getPath());
    }

    public function testRender()
    {
        $template = new PlatesTemplate();
        $template->setPath(__DIR__ . '/TestAsset');
        $name = 'Plates';
        $result = $template->render('plates', [ 'name' => $name ]);
        $this->assertContains($name, $result);
        $content = file_get_contents(__DIR__ . '/TestAsset/plates.php');
        $content = str_replace('<?=$this->e($name)?>', $name, $content);
        $this->assertEquals($content, $result);
    }
}
