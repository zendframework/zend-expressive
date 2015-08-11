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
use Zend\Expressive\Template\TemplatePath;

class TemplatePathTest extends TestCase
{
    public function testConstructWithNamespace()
    {
        $templatePath = new TemplatePath('/tmp', 'test');
        $this->assertTrue($templatePath instanceof TemplatePath);
        $this->assertEquals('/tmp', $templatePath->getPath());
        $this->assertEquals('test', $templatePath->getNamespace());
    }

    public function testConstructWithoutNamespace()
    {
        $templatePath = new TemplatePath('/tmp');
        $this->assertTrue($templatePath instanceof TemplatePath);
        $this->assertEquals('/tmp', $templatePath->getPath());
        $this->assertEmpty($templatePath->getNamespace());
    }

    public function testToString()
    {
        $templatePath = new TemplatePath('/tmp');
        $this->assertEquals('/tmp', (string) $templatePath);

        $templatePath = new TemplatePath('/tmp', 'test');
        $this->assertEquals('/tmp', (string) $templatePath);
    }
}
