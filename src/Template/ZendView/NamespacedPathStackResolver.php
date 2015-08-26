<?php
/**
 * @see       http://github.com/zendframework/zend-expressive for the canonical source repository
 * @copyright Copyright (c) 2015 Zend Technologies USA Inc. (http://www.zend.com)
 * @license   https://github.com/zendframework/zend-expressive/blob/master/LICENSE.md New BSD License
 */

namespace Zend\Expressive\Template\ZendView;

use SplFileInfo;
use SplStack;
use Traversable;
use Zend\View\Exception as ViewException;
use Zend\View\Renderer\RendererInterface;
use Zend\View\Resolver\TemplatePathStack;

/**
 * Variant of TemplatePathStack providing namespaced paths.
 *
 * Allows adding paths by namespace. When resolving a template, if a namespace
 * is provided, it will search first on paths with that namespace, and fall
 * back to those provided without a namespace (or with the the __DEFAULT__
 * namespace).
 *
 * Namespaces are specified with a `namespace::` prefix when specifying the
 * template.
 */
class NamespacedPathStackResolver extends TemplatePathStack
{
    const DEFAULT_NAMESPACE = '__DEFAULT__';

    /**
     * @var array
     */
    protected $paths = [];

    /**
     * Constructor
     *
     * Overrides parent constructor to allow specifying paths as an associative
     * array.
     *
     * @param null|array|Traversable $options
     */
    public function __construct($options = null)
    {
        $this->useViewStream = (bool) ini_get('short_open_tag');
        if ($this->useViewStream) {
            if (!in_array('zend.view', stream_get_wrappers())) {
                stream_wrapper_register('zend.view', 'Zend\View\Stream');
            }
        }

        if (null !== $options) {
            $this->setOptions($options);
        }
    }

    /**
     * Add a path to the stack with the given namespace.
     *
     * @param string $path
     * @param string $namespace
     * @throws ViewException\InvalidArgumentException for an invalid path
     * @throws ViewException\InvalidArgumentException for an invalid namespace
     */
    public function addPath($path, $namespace = self::DEFAULT_NAMESPACE)
    {
        if (! is_string($path)) {
            throw new ViewException\InvalidArgumentException(sprintf(
                'Invalid path provided; expected a string, received %s',
                gettype($path)
            ));
        }

        if (null === $namespace) {
            $namespace = self::DEFAULT_NAMESPACE;
        }

        if (! is_string($namespace) || empty($namespace)) {
            throw new ViewException\InvalidArgumentException(
                'Invalid namespace provided; must be a non-empty string'
            );
        }

        if (! array_key_exists($namespace, $this->paths)) {
            $this->paths[$namespace] = new SplStack();
        }

        $this->paths[$namespace]->push(static::normalizePath($path));
    }

    /**
     * Add many paths to the stack at once.
     *
     * @param array $paths
     */
    public function addPaths(array $paths)
    {
        foreach ($paths as $namespace => $path) {
            if (! is_string($namespace)) {
                $namespace = self::DEFAULT_NAMESPACE;
            }

            $this->addPath($path, $namespace);
        }
    }

    /**
     * Overwrite all existing paths with the provided paths.
     *
     * @param array|Traversable $paths
     * @throws ViewException\InvalidArgumentException for invalid path types.
     */
    public function setPaths($paths)
    {
        if ($paths instanceof Traversable) {
            $paths = iterator_to_array($paths);
        }

        if (! is_array($paths)) {
            throw new ViewException\InvalidArgumentException(sprintf(
                'Invalid paths provided; must be an array or Traversable, received %s',
                (is_object($paths) ? get_class($paths) : gettype($paths))
            ));
        }

        $this->clearPaths();
        $this->addPaths($paths);
    }

    /**
     * Clear all paths.
     */
    public function clearPaths()
    {
        $this->paths = [];
    }

    /**
     * Retrieve the filesystem path to a view script
     *
     * @param  string $name
     * @param  null|RendererInterface $renderer
     * @return string
     * @throws Exception\DomainException
     */
    public function resolve($name, RendererInterface $renderer = null)
    {
        $namespace = self::DEFAULT_NAMESPACE;
        $template  = $name;
        if (preg_match('#^(?P<namespace>[^:]+)::(?P<template>.*)$#', $template, $matches)) {
            $namespace = $matches['namespace'];
            $template  = $matches['template'];
        }

        $this->lastLookupFailure = false;

        if ($this->isLfiProtectionOn() && preg_match('#\.\.[\\\/]#', $template)) {
            throw new Exception\DomainException(
                'Requested scripts may not include parent directory traversal ("../", "..\\" notation)'
            );
        }

        if (!count($this->paths)) {
            $this->lastLookupFailure = static::FAILURE_NO_PATHS;
            return false;
        }

        // Ensure we have the expected file extension
        $defaultSuffix = $this->getDefaultSuffix();
        if (pathinfo($template, PATHINFO_EXTENSION) == '') {
            $template .= '.' . $defaultSuffix;
        }

        $path = false;
        if ($namespace !== self::DEFAULT_NAMESPACE) {
            $path = $this->getPathFromNamespace($template, $namespace);
        }

        $path = $path ?: $this->getPathFromNamespace($template, self::DEFAULT_NAMESPACE);

        if ($path) {
            return $path;
        }

        $this->lastLookupFailure = static::FAILURE_NOT_FOUND;
        return false;
    }

    /**
     * Fetch a template path from a given namespace.
     *
     * @param string $template
     * @param string $namespace
     * @return false|string String path on success; false on failure
     */
    private function getPathFromNamespace($template, $namespace)
    {
        if (! array_key_exists($namespace, $this->paths)) {
            return false;
        }

        foreach ($this->paths[$namespace] as $path) {
            $file = new SplFileInfo($path . $template);
            if ($file->isReadable()) {
                // Found! Return it.
                if (($filePath = $file->getRealPath()) === false && substr($path, 0, 7) === 'phar://') {
                    // Do not try to expand phar paths (realpath + phars == fail)
                    $filePath = $path . $template;
                    if (! file_exists($filePath)) {
                        break;
                    }
                }

                if ($this->useStreamWrapper()) {
                    // If using a stream wrapper, prepend the spec to the path
                    $filePath = 'zend.view://' . $filePath;
                }
                return $filePath;
            }
        }

        return false;
    }
}
