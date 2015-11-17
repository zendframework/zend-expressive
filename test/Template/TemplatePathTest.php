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

/**
 * @covers Zend\Expressive\Template\TemplatePath
 */
class TemplatePathTest extends TestCase
{
    use TemplatePathAssertionsTrait;

    public function testCanProvideNamespaceAtInstantiation()
    {
        $templatePath = new TemplatePath('/tmp', 'test');
        $this->assertTemplatePath('/tmp', $templatePath);
        $this->assertTemplatePathNamespace('test', $templatePath);
    }

    public function testCanInstantiateWithoutANamespace()
    {
        $templatePath = new TemplatePath('/tmp');
        $this->assertTemplatePath('/tmp', $templatePath);
        $this->assertEmptyTemplatePathNamespace($templatePath);
    }

    public function testCastingToStringReturnsPathOnly()
    {
        $templatePath = new TemplatePath('/tmp');
        $this->assertTemplatePathString('/tmp', $templatePath);

        $templatePath = new TemplatePath('/tmp', 'test');
        $this->assertTemplatePathString('/tmp', $templatePath);
    }
}
