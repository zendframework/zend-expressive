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
    use ArrayParametersTrait;

    /**
     * @var TwigFilesystem
     */
    protected $twigLoader;

    /**
     * @var TwigEnvironment
     */
    protected $template;

    /**
     *  Constructor
     *
     * @param TwigEnvironment $template
     */
    public function __construct(TwigEnvironment $template = null)
    {
        if (null === $template) {
            $template = $this->createTemplate($this->getDefaultLoader());
        }
        $this->template   = $template;
        $this->twigLoader = $template->getLoader();
    }

    /**
     * Create a default Twig environment
     *
     * @return TwigEnvironment
     */
    private function createTemplate(TwigFilesystem $loader)
    {
        return new TwigEnvironment($loader);
    }

    /**
     * Get the default loader for template
     *
     * @return TwigFilesystem
     */
    private function getDefaultLoader()
    {
        return new TwigFilesystem();
    }

    /**
     * Render
     *
     * @param string $name
     * @param array|object $params
     * @return string
     * @throws \Zend\Expressive\Exception\InvalidArgumentException for non-array, non-object parameters.
     */
    public function render($name, $params = [])
    {
        $params = $this->normalizeParams($params);
        return $this->template->render($name, $params);
    }

    /**
     * Add a path for template
     *
     * @param string $path
     * @param string $namespace
     */
    public function addPath($path, $namespace = null)
    {
        $namespace = $namespace ?: TwigFilesystem::MAIN_NAMESPACE;
        $this->twigLoader->addPath($path, $namespace);
    }

    /**
     * Get the template directories
     *
     * @return TemplatePath[]
     */
    public function getPaths()
    {
        $paths = [];
        foreach ($this->twigLoader->getNamespaces() as $namespace) {
            $name = ($namespace !== TwigFilesystem::MAIN_NAMESPACE) ? $namespace : null;

            foreach ($this->twigLoader->getPaths($namespace) as $path) {
                $paths[] = new TemplatePath($path, $name);
            }
        }
        return $paths;
    }
}
