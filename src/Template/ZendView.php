<?php
/**
 * Zend Framework (http://framework.zend.com/)
 *
 * @see       https://github.com/zendframework/zend-expressive for the canonical source repository
 * @copyright Copyright (c) 2015 Zend Technologies USA Inc. (http://www.zend.com)
 * @license   https://github.com/zendframework/zend-expressive/blob/master/LICENSE.md New BSD License
 */

namespace Zend\Expressive\Template;

use Zend\View\Renderer\RendererInterface;
use Zend\View\Renderer\PhpRenderer;
use Zend\View\Resolver\ResolverInterface;
use Zend\View\Resolver\TemplatePathStack;
use Zend\Expressive\Exception;

/**
 * Template implementation bridging zendframework/zend-view
 */
class ZendView implements TemplateInterface
{
    use ArrayParametersTrait;

    /**
     * Paths and namespaces data store
     */
    private $paths = [];

    /**
     * @var RendererInterface
     */
    private $template;

    /**
     * @var ResolverInterface
     */
    private $resolver;

    /**
     * Constructor
     *
     * @param RendererInterface $template
     */
    public function __construct(RendererInterface $template = null)
    {
        if (null === $template) {
            $template = $this->createRender();
        }
        $this->template = $template;
        $this->resolver = $template->resolver();
    }

    /**
     * Returns a PhpRenderer object
     *
     * @return PhpRenderer
     */
    private function createRender()
    {
        $render = new PhpRenderer();
        $render->setResolver($this->getDefaultResolver());
        return $render;
    }

    /**
     * Get the default resolver
     *
     * @return TemplatePathStack
     */
    private function getDefaultResolver()
    {
        return new TemplatePathStack();
    }

    /**
     * Render
     *
     * @param string $name
     * @param array|object $params
     * @return string
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
        $this->resolver->addPath($path);
        // Normalize the path to be compliant with zend view's resolver
        $this->paths[TemplatePathStack::normalizePath($path)] = $namespace;
    }

    /**
     * Get the template directories
     *
     * @return TemplatePath[]
     */
    public function getPaths()
    {
        $paths = [];
        foreach ($this->resolver->getPaths() as $path) {
            $namespace = array_key_exists($path, $this->paths) ? $this->paths[$path] : null;
            $paths[]   = new TemplatePath($path, $namespace);
        }
        return $paths;
    }
}
