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
     * @const string Value indicating all templates; used with `addDefaultParam()`.
     */
    const TEMPLATE_ALL = '*';

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
     * Add a default parameter to use with a template.
     *
     * Use this method to provide a default parameter to use when a template is
     * rendered. The parameter may be overridden by providing it when calling
     * `render()`, or by calling this method again with a null value.
     *
     * The parameter will be specific to the template name provided. To make
     * the parameter available to any template, pass the TEMPLATE_ALL constant
     * for the template name.
     *
     * If the default parameter existed previously, subsequent invocations with
     * the same template name and parameter name will overwrite.
     *
     * @param string $templateName Name of template to which the param applies;
     *     use TEMPLATE_ALL to apply to all templates.
     * @param string $param Param name.
     * @param mixed $value
     */
    public function addDefaultParam($templateName, $param, $value);
}
