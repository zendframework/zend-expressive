<?php
/**
 * Zend Framework (http://framework.zend.com/)
 *
 * @see       https://github.com/zendframework/zend-expressive for the canonical source repository
 * @copyright Copyright (c) 2015 Zend Technologies USA Inc. (http://www.zend.com)
 * @license   https://github.com/zendframework/zend-expressive/blob/master/LICENSE.md New BSD License
 */

namespace Zend\Expressive\Template;

use LogicException;
use Twig_Environment as TwigEnvironment;
use Twig_Loader_Filesystem as TwigFilesystem;

/**
 * Template implementation bridging league/plates
 */
class Twig implements TemplateInterface
{
    use ArrayParametersTrait;

    /**
     * @var string
     */
    private $suffix;

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
    public function __construct(TwigEnvironment $template = null, $suffix = 'html')
    {
        if (null === $template) {
            $template = $this->createTemplate($this->getDefaultLoader());
        }

        try {
            $loader = $template->getLoader();
        } catch (LogicException $e) {
            $loader = $this->getDefaultLoader();
            $template->setLoader($loader);
        }

        $this->template   = $template;
        $this->twigLoader = $loader;
        $this->suffix     = is_string($suffix) ? $suffix : 'html';
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
        $name   = $this->normalizeTemplate($name);
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

    /**
     * Normalize namespaced template.
     *
     * Normalizes templates in the format "namespace::template" to
     * "@namespace/template".
     *
     * @param string $template
     * @return string
     */
    public function normalizeTemplate($template)
    {
        $template = preg_replace('#^([^:]+)::(.*)$#', '@$1/$2', $template);
        if (! preg_match('#\.[a-z]+$#i', $template)) {
            return sprintf('%s.%s', $template, $this->suffix);
        }

        return $template;
    }
}
