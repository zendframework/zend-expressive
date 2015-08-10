<?php
/**
 * Zend Framework (http://framework.zend.com/)
 *
 * @see       https://github.com/zendframework/zend-expressive for the canonical source repository
 * @copyright Copyright (c) 2015 Zend Technologies USA Inc. (http://www.zend.com)
 * @license   https://github.com/zendframework/zend-expressive/blob/master/LICENSE.md New BSD License
 */

namespace Zend\Expressive\Template;

use Twig_Loader_Filesystem as TwigFilesystem;
use Twig_Environment as TwigEnvironment;

/**
 * Template implementation bridging league/plates
 */
class Twig implements TemplateInterface
{
    /**
     * @var TwigFilesystem
     */
    protected $twigLoader;

    /**
     * @var TwigEnvironment
     */
    protected $template;

    public function __construct(TwigEnvironment $template = null)
    {
        if (null === $template) {
            $template = $this->createTemplate();
        } else {
            if (!$template->getLoader()) {
                $this->twigLoader = new TwigFilesystem();
                $template->setLoader($this->twigLoader);
            } else {
                $this->twigLoader = $template->getLoader();
            }
        }
        $this->template = $template;
    }

    /**
     * Create a default Twig environment
     *
     * @params string $path
     * @return TwigEnvironment
     */
    private function createTemplate()
    {
        $this->twigLoader = new TwigFilesystem();
        return new TwigEnvironment($this->twigLoader);
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
        $this->twigLoader->setPaths($path);
    }

    /**
     * Get the template directory
     *
     * @return string
     */
    public function getPath()
    {
        $path = $this->twigLoader->getPaths();
        if (empty($path)) {
            return null;
        }
        return is_array($path) ? $path[0] : $path;
    }
}
