<?php
/**
 * Zend Framework (http://framework.zend.com/)
 *
 * @see       https://github.com/zendframework/zend-expressive for the canonical source repository
 * @copyright Copyright (c) 2015 Zend Technologies USA Inc. (http://www.zend.com)
 * @license   https://github.com/zendframework/zend-expressive/blob/master/LICENSE.md New BSD License
 */

namespace Zend\Expressive\Router;

/**
 * Value object representing a single route.
 *
 * Routes are a combination of path, middleware, and HTTP methods; two routes
 * representing the same path and overlapping HTTP methods are not allowed,
 * while two routes representing the same path and non-overlapping HTTP methods
 * can be used (and should typically resolve to different middleware).
 *
 * Internally, only those three properties are required. However, underlying
 * router implementations may allow or require additional information, such as
 * information defining how to generate a URL from the given route, qualifiers
 * for how segments of a route match, or even default values to use. These may
 * be provided after instantiation via the "options" property and related
 * setOptions() method.
 */
class Route
{
    const HTTP_METHOD_ANY = 0xff;
    const HTTP_METHOD_SEPARATOR = ':';

    /**
     * @var int|string[] HTTP methods allowed with this route.
     */
    private $methods = self::HTTP_METHOD_ANY;

    /**
     * @var callable|string Middleware or service name of middleware associated with route.
     */
    private $middleware;

    /**
     * @var array Options related to this route to pass to the routing implementation.
     */
    private $options = [];

    /**
     * @var string
     */
    private $path;

    /**
     * @var string
     */
    private $name;

    /**
     * @param string $path Path to match.
     * @param string|callable $middleware Middleware to use when this route is matched.
     * @param int|array Allowed HTTP methods; defaults to HTTP_METHOD_ANY.
     * @param string|null $name the route name
     * @throws Exception\InvalidArgumentException for invalid path type.
     * @throws Exception\InvalidArgumentException for invalid middleware type.
     * @throws Exception\InvalidArgumentException for any invalid HTTP method names.
     */
    public function __construct($path, $middleware, $methods = self::HTTP_METHOD_ANY, $name = null)
    {
        if (! is_string($path)) {
            throw new Exception\InvalidArgumentException('Invalid path; must be a string');
        }

        if (! is_callable($middleware) && ! is_string($middleware) && ! is_array($middleware)) {
            throw new Exception\InvalidArgumentException('Invalid middleware; must be callable or a service name');
        }

        if ($methods !== self::HTTP_METHOD_ANY && ! is_array($methods)) {
            throw new Exception\InvalidArgumentException(sprintf(
                'Invalid HTTP methods; must be an array or %s::HTTP_METHOD_ANY',
                __CLASS__
            ));
        }

        $this->path       = $path;
        $this->middleware = $middleware;
        $this->methods    = is_array($methods) ? $this->validateHttpMethods($methods) : $methods;

        if (empty($name)) {
            $name = ($this->methods === self::HTTP_METHOD_ANY)
                ? $path
                : $path . '^' . implode(self::HTTP_METHOD_SEPARATOR, $this->methods);
        }
        $this->name = $name;
    }

    /**
     * @return string
     */
    public function getPath()
    {
        return $this->path;
    }

    /**
     * Set the route name.
     *
     * @param string $name
     */
    public function setName($name)
    {
        $this->name = (string) $name;
    }

    /**
     * @return string
     */
    public function getName()
    {
        return $this->name;
    }

    /**
     * @return string|callable|array
     */
    public function getMiddleware()
    {
        return $this->middleware;
    }

    /**
     * @return int|string[] Returns HTTP_METHOD_ANY or array of allowed methods.
     */
    public function getAllowedMethods()
    {
        return $this->methods;
    }

    /**
     * Indicate whether the specified method is allowed by the route.
     *
     * @param string $method HTTP method to test.
     * @return bool
     */
    public function allowsMethod($method)
    {
        $method = strtoupper($method);
        if ('HEAD' === $method
            || 'OPTIONS' === $method
            || $this->methods === self::HTTP_METHOD_ANY
            || in_array($method, $this->methods, true)
        ) {
            return true;
        }

        return false;
    }

    /**
     * @param array $options
     */
    public function setOptions(array $options)
    {
        $this->options = $options;
    }

    /**
     * @return array
     */
    public function getOptions()
    {
        return $this->options;
    }

    /**
     * Validate the provided HTTP method names.
     *
     * Validates, and then normalizes to upper case.
     *
     * @param string[] An array of HTTP method names.
     * @return string[]
     * @throws Exception\InvalidArgumentException for any invalid method names.
     */
    private function validateHttpMethods(array $methods)
    {
        if (false === array_reduce($methods, function ($valid, $method) {
            if (false === $valid) {
                return false;
            }

            if (! is_string($method)) {
                return false;
            }

            if (! preg_match('/^[!#$%&\'*+.^_`\|~0-9a-z-]+$/i', $method)) {
                return false;
            }

            return $valid;
        }, true)) {
            throw new Exception\InvalidArgumentException('One or more HTTP methods were invalid');
        }

        return array_map('strtoupper', $methods);
    }
}
