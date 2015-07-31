<?php
namespace Zend\Expressive\Router;

use InvalidArgumentException;

class Route
{
    const HTTP_METHOD_ANY = 0xff;

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
     * @param string $path Path to match.
     * @param string|callable $middleware Middleware to use when this route is matched.
     * @throws InvalidArgumentException for invalid path type.
     * @throws InvalidArgumentException for invalid middleware type.
     */
    public function __construct($path, $middleware)
    {
        if (! is_string($path)) {
            throw new InvalidArgumentException('Invalid path; must be a string');
        }

        if (! is_callable($middleware) && ! is_string($middleware)) {
            throw new InvalidArgumentException('Invalid middleware; must be callable or a service name');
        }

        $this->path = $path;
        $this->middleware = $middleware;
    }

    /**
     * @return string
     */
    public function getPath()
    {
        return $this->path;
    }

    /**
     * @return string|callable
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
     * @param int|string[] HTTP_METHOD_ANY or an array of HTTP method names.
     * @throws InvalidArgumentException for any invalid method names.
     */
    public function setAllowedMethods($methods)
    {
        if ($methods !== self::HTTP_METHOD_ANY
            && ! is_array($methods)
        ) {
            throw new InvalidArgumentException(sprintf(
                'Invalid methods provided; must be an array or %s::HTTP_METHOD_ANY',
                __CLASS__
            ));
        }

        if ($methods === self::HTTP_METHOD_ANY) {
            $this->methods = $methods;
            return;
        }

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
            throw new InvalidArgumentException('One or more HTTP methods were invalid');
        }

        $this->methods = array_map('strtoupper', $methods);
    }

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
}
