<?php
/**
 * Zend Framework (http://framework.zend.com/)
 *
 * @see       https://github.com/zendframework/zend-expressive for the canonical source repository
 * @copyright Copyright (c) 2015 Zend Technologies USA Inc. (http://www.zend.com)
 * @license   https://github.com/zendframework/zend-expressive/blob/master/LICENSE.md New BSD License
 */

namespace Zend\Expressive\Template;

/**
 * Interface defining required template capabilities.
 */
interface TemplateInterface
{
    /**
     * @param string $name
     * @param array $params
     * @return string
     */
    public function render($name, array $params);

    /**
     * @param string $path
     * @param string $namespace
     */
    public function addPath($path, $namespace = null);

    /**
     * @return TemplatePath[]
     */
    public function getPaths();
}
