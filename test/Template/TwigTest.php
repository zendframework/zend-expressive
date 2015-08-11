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
use Zend\Expressive\Template\TemplatePath;

class TwigTest extends TestCase
{
    public function setUp()
    {
        $this->twigFilesystem  = new Twig_Loader_Filesystem;
        $this->twigEnvironment = new Twig_Environment($this->twigFilesystem);
    }

    public function testConstructorWithEngine()
    {
        $template = new TwigTemplate($this->twigEnvironment);
        $this->assertTrue($template instanceof TwigTemplate);
        $this->assertEmpty($template->getPaths());
    }

    public function testConstructorWithoutEngine()
    {
        $template = new TwigTemplate();
        $this->assertTrue($template instanceof TwigTemplate);
        $this->assertEmpty($template->getPaths());
    }

    public function testAddPath()
    {
        $template = new TwigTemplate();
        $template->addPath(__DIR__ . '/TestAsset');
        $paths = $template->getPaths();
        $this->assertTrue(is_array($paths));
        $this->assertEquals(1, count($paths));
        $this->assertTrue($paths[0] instanceof TemplatePath);
        $this->assertEquals(__DIR__ . '/TestAsset', (string) $paths[0]);
        $this->assertEquals(__DIR__ . '/TestAsset', $paths[0]->getPath());
        $this->assertEmpty($paths[0]->getNamespace());
    }

    public function testAddPathWithNamespace()
    {
        $template = new TwigTemplate();
        $template->addPath(__DIR__ . '/TestAsset', 'test');
        $paths = $template->getPaths();
        $this->assertTrue(is_array($paths));
        $this->assertEquals(1, count($paths));
        $this->assertTrue($paths[0] instanceof TemplatePath);
        $this->assertEquals(__DIR__ . '/TestAsset', (string) $paths[0]);
        $this->assertEquals(__DIR__ . '/TestAsset', $paths[0]->getPath());
        $this->assertEquals('test', $paths[0]->getNamespace());
    }

    public function testRender()
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
}
