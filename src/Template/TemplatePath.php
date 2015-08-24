<?php
/**
 * Zend Framework (http://framework.zend.com/)
 *
 * @see       https://github.com/zendframework/zend-expressive for the canonical source repository
 * @copyright Copyright (c) 2015 Zend Technologies USA Inc. (http://www.zend.com)
 * @license   https://github.com/zendframework/zend-expressive/blob/master/LICENSE.md New BSD License
 */

namespace Zend\Expressive\Template;

class TemplatePath
{
    /**
     * @var string
     */
    protected $path;

    /**
     * @var null|string
     */
    protected $namespace;

    /**
     * Constructor
     *
     * @param string $path
     * @param null|string $namespace
     */
    public function __construct($path, $namespace = null)
    {
        $this->path      = $path;
        $this->namespace = $namespace;
    }

    /**
     * Casts to string by returning the path only.
     *
     * @return string
     */
    public function __toString()
    {
        return $this->path;
    }

    /**
     * Get the namespace
     *
     * @return null|string
     */
    public function getNamespace()
    {
        return $this->namespace;
    }

    /**
     * Get the path
     *
     * @return string
     */
    public function getPath()
    {
        return $this->path;
    }
}
