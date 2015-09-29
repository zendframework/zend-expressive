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
interface TemplateRendererInterface
{
    /**
     * Render a template, optionally with parameters.
     *
     * Implementations MUST support the `namespace::template` naming convention,
     * and allow omitting the filename extension.
     *
     * @param string $name
     * @param array|object $params
     * @return string
     */
    public function render($name, $params = []);

    /**
     * Add a template path to the engine.
     *
     * Adds a template path, with optional namespace the templates in that path
     * provide.
     *
     * @param string $path
     * @param string $namespace
     */
    public function addPath($path, $namespace = null);

    /**
     * Retrieve configured paths from the engine.
     *
     * @return TemplatePath[]
     */
    public function getPaths();

    /**
     * Add parameters to template
     *
     * If no template name is given, the parameters will be added to all templates rendered
     *
     * @param array|object $params
     * @param string|null $name
     */
    public function addParameters($params, $name = null);
}
