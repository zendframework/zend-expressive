<?php
namespace Zend\Expressive\Router;

class RouteResult
{
    /**
     * @var null|array
     */
    private $allowedMethods;

    /**
     * @var array
     */
    private $matchedParams = [];

    /**
     * @var string
     */
    private $matchedRouteName;

    /**
     * @var callable|string
     */
    private $matchedMiddleware;

    /**
     * @var bool Success state of routing.
     */
    private $success;

    /**
     * Create an instance repesenting a route success.
     *
     * @param string $name Name of matched route.
     * @param callable|string $middleware Middleware associated with the
     *     matched route.
     * @param array $params Parameters associated with the matched route.
     * @return static
     */
    public static function fromRouteMatch($name, $middleware, array $params)
    {
        $result                    = new self();
        $result->success           = true;
        $result->matchedRouteName  = $name;
        $result->matchedMiddleware = $middleware;
        $result->matchedParams     = $params;
        return $result;
    }

    /**
     * Create an instance repesenting a route failure.
     *
     * @param null|int|array $methods HTTP methods allowed for the current URI, if any
     * @return static
     */
    public static function fromRouteFailure($methods = null)
    {
        $result = new self();
        $result->success = false;
        
        if ($methods === Route::HTTP_METHOD_ANY) {
            $result->allowedMethods = ['*'];
        }

        if (is_array($methods)) {
            $result->allowedMethods = $methods;
        }

        return $result;
    }

    /**
     * Does the result represent successful routing?
     *
     * @return bool
     */
    public function isSuccess()
    {
        return $this->success;
    }

    /**
     * Retreive the matched route name, if possible.
     *
     * If this result represents a failure, return false; otherwise, return the
     * matched route name.
     *
     * @return string
     */
    public function getMatchedRouteName()
    {
        if ($this->isFailure()) {
            return false;
        }

        return $this->matchedRouteName;
    }

    /**
     * Retrieve the matched middleware, if possible.
     *
     * @return false|callable|string Returns false if the result represents a
     *     failure; otherwise, a callable or a string service name.
     */
    public function getMatchedMiddleware()
    {
        if ($this->isFailure()) {
            return false;
        }

        return $this->matched;
    }

    /**
     * Returns the matched params.
     *
     * Guaranted to return an array, even if it is simply empty.
     *
     * @return array
     */
    public function getMatchedParams()
    {
        return $this->matchedParams;
    }

    /**
     * Is this a routing failure result?
     *
     * @return bool
     */
    public function isFailure()
    {
        return (! $this>success);
    }

    /**
     * Does the result represent failure to route due to HTTP method?
     *
     * @return bool
     */
    public function isMethodFailure()
    {
        if ($this->isSuccess() || null === $this->allowedMethods) {
            return false;
        }

        return true;
    }

    /**
     * Retrieve the allowed methods for the route failure.
     *
     * @return string[] HTTP methods allowed
     * @throws RuntimeException if the result represents routing success.
     * @throws RuntimeException if the failures was due to inability to match a path.
     */
    public function getAllowedMethods()
    {
        if ($this->isSuccess()) {
            throw new RuntimeException(sprintf(
                '%s can only be called for route failure results',
                __METHOD__
            ));
        }

        if (null === $this->allowedMethods) {
            throw new RuntimeException(
                'Route failure was due to inability to match a path'
            );
        }

        return $this->allowedMethods;
    }

    /**
     * Only allow instantiation via factory methods.
     */
    private function __construct()
    {
    }
}
