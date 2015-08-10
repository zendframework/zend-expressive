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
use Zend\Expressive\Template\Twig as TwigTemplate;
use Twig_Loader_Filesystem;
use Twig_Environment;

class TwigTest extends TestCase
{
    public function setUp()
    {
        $this->twigFilesystem  = $this->prophesize('Twig_Loader_Filesystem');
        $this->twigEnvironment = $this->prophesize('Twig_Environment');
    }

    public function testConstructorWithEngine()
    {
        $template = new TwigTemplate($this->twigEnvironment->reveal());
        $this->assertTrue($template instanceof TwigTemplate);
        $this->assertEmpty($template->getPath());
    }

    public function testConstructorWithoutEngine()
    {
        $template = new TwigTemplate();
        $this->assertTrue($template instanceof TwigTemplate);
        $this->assertEmpty($template->getPath());
    }

    public function testSetPath()
    {
        $template = new TwigTemplate();
        $template->setPath(__DIR__ . '/TestAsset');
        $this->assertTrue($template instanceof TwigTemplate);
        $this->assertEquals(__DIR__ . '/TestAsset', $template->getPath());
    }

    public function testRender()
    {
        $template = new TwigTemplate();
        $template->setPath(__DIR__ . '/TestAsset');
        $name = 'Twig';
        $result = $template->render('twig.html', [ 'name' => $name ]);
        $this->assertContains($name, $result);
        $content = file_get_contents(__DIR__ . '/TestAsset/twig.html');
        $content = str_replace('{{ name }}', $name, $content);
        $this->assertEquals($content, $result);
    }
}
