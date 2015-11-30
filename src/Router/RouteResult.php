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
 * Value object representing the results of routing.
 *
 * RouterInterface::match() is defined as returning a RouteResult instance,
 * which will contain the following state:
 *
 * - isSuccess()/isFailure() indicate whether routing succeeded or not.
 * - On success, it will contain:
 *   - the matched route name (typically the path)
 *   - the matched route middleware
 *   - any parameters matched by routing
 * - On failure:
 *   - isMethodFailure() further qualifies a routing failure to indicate that it
 *     was due to using an HTTP method not allowed for the given path.
 *   - If the failure was due to HTTP method negotiation, it will contain the
 *     list of allowed HTTP methods.
 *
 * RouteResult instances are consumed by the Application in the routing
 * middleware.
 */
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
     * Create an instance representing a route failure.
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
     * Retrieve the matched route name, if possible.
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
     * @return false|callable|string|array Returns false if the result represents a
     *     failure; otherwise, a callable or a string service name.
     */
    public function getMatchedMiddleware()
    {
        if ($this->isFailure()) {
            return false;
        }

        return $this->matchedMiddleware;
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
        return (! $this->success);
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
     */
    public function getAllowedMethods()
    {
        if ($this->isSuccess()) {
            return [];
        }

        if (null === $this->allowedMethods) {
            return [];
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
