<?php
/**
 * @see       http://github.com/zendframework/zend-expressive for the canonical source repository
 * @copyright Copyright (c) 2015 Zend Technologies USA Inc. (http://www.zend.com)
 * @license   https://github.com/zendframework/zend-expressive/blob/master/LICENSE.md New BSD License
 */

namespace Zend\Expressive\Template\ZendView;

use Psr\Http\Message\UriInterface;
use Zend\View\Helper\AbstractHelper;

/**
 * Alternate ServerUrl helper for use in Expressive.
 */
class ServerUrlHelper extends AbstractHelper
{
    /**
     * @var UriInterface
     */
    private $uri;

    /**
     * Return a path relative to the current request URI.
     *
     * If no request URI has been injected, it returns an absolute path
     * only; relative paths are made absolute, and absolute paths are returned
     * verbatim (null paths are returned as root paths).
     *
     * Otherwise, returns a fully-qualified URI based on the injected request
     * URI; absolute paths replace the request URI path, while relative paths
     * are appended to it (and null paths are considered the current path).
     *
     * The $path may optionally contain the query string and/or fragment to
     * use.
     *
     * @param null|string $path
     * @return string
     */
    public function __invoke($path = null)
    {
        if ($this->uri instanceof UriInterface) {
            return $this->createUrlFromUri($path);
        }

        if (empty($path)) {
            return '/';
        }

        if ('/' === $path[0]) {
            return $path;
        }

        return '/' . $path;
    }

    /**
     * @param UriInterface $uri
     */
    public function setUri(UriInterface $uri)
    {
        $this->uri = $uri;
    }

    /**
     * @param string $specification
     * @return string
     */
    private function createUrlFromUri($specification)
    {
        preg_match('%^(?P<path>[^?#]*)(?:(?:\?(?P<query>[^#]*))?(?:\#(?P<fragment>.*))?)$%', (string) $specification, $matches);
        $path     = $matches['path'];
        $query    = isset($matches['query']) ? $matches['query'] : '';
        $fragment = isset($matches['fragment']) ? $matches['fragment'] : '';

        $uri = $this->uri
            ->withQuery('')
            ->withFragment('');

        // Relative path
        if (! empty($path) && '/' !== $path[0]) {
            $path = rtrim($this->uri->getPath(), '/') . '/' . $path;
        }

        // Path present; set on URI
        if (! empty($path)) {
            $uri  = $uri->withPath($path);
        }

        // Query present; set on URI
        if (! empty($query)) {
            $uri = $uri->withQuery($query);
        }

        // Fragment present; set on URI
        if (! empty($fragment)) {
            $uri = $uri->withFragment($fragment);
        }

        return (string) $uri;
    }
}
