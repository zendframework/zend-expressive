<?php
/**
 * Zend Framework (http://framework.zend.com/)
 *
 * @see       https://github.com/zendframework/zend-expressive for the canonical source repository
 * @copyright Copyright (c) 2015 Zend Technologies USA Inc. (http://www.zend.com)
 * @license   https://github.com/zendframework/zend-expressive/blob/master/LICENSE.md New BSD License
 */

namespace Zend\Expressive\Template;

use League\Plates\Engine;

/**
 * Template implementation bridging league/plates
 */
class Plates implements TemplateInterface
{
    /**
     * @var Engine
     */
    protected $template;

    public function __construct(Engine $template = null)
    {
        if (null === $template) {
            $template = $this->createTemplate();
        }
        $this->template = $template;
    }

    /**
     * Create a default Plates engine
     *
     * @params string $path
     * @return Engine
     */
    private function createTemplate()
    {
        return new Engine();
    }

    /**
     * Render
     *
     * @param string $name
     * @param array $params
     * @return string
     */
    public function render($name, array $params)
    {
        return $this->template->render($name, $params);
    }

    /**
     * Set the template directory
     *
     * @param string $path
     */
    public function setPath($path)
    {
        $this->template->setDirectory($path);
    }

    /**
     * Get the template directory
     *
     * @return string
     */
    public function getPath()
    {
        return $this->template->getDirectory();
    }
}
