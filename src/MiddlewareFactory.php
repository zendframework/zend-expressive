<?php
/**
 * @see       https://github.com/zendframework/zend-expressive for the canonical source repository
 * @copyright Copyright (c) 2018 Zend Technologies USA Inc. (https://www.zend.com)
 * @license   https://github.com/zendframework/zend-expressive/blob/master/LICENSE.md New BSD License
 */

declare(strict_types=1);

namespace Zend\Expressive;

use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Zend\Stratigility\Middleware\CallableMiddlewareDecorator;
use Zend\Stratigility\Middleware\RequestHandlerMiddleware;
use Zend\Stratigility\MiddlewarePipe;

use function array_shift;
use function count;
use function is_array;
use function is_callable;
use function is_string;

/**
 * Marshal middleware for use in the application.
 *
 * This class provides a number of methods for preparing and returning
 * middleware for use within an application.
 *
 * If any middleware provided is already a MiddlewareInterface, it can be used
 * verbatim or decorated as-is. Other middleware types acceptable are:
 *
 * - PSR-15 RequestHandlerInterface instances; these will be decorated as
 *   RequestHandlerMiddleware instances.
 * - string service names resolving to middleware
 * - arrays of service names and/or MiddlewareInterface instances
 * - PHP callables that follow the PSR-15 signature
 *
 * Additionally, the class provides the following decorator/utility methods:
 *
 * - callable() will decorate the callable middleware passed to it using
 *   CallableMiddlewareDecorator.
 * - handler() will decorate the request handler passed to it using
 *   RequestHandlerMiddleware.
 * - lazy() will decorate the string service name passed to it, along with the
 *   factory instance, as a LazyLoadingMiddleware instance.
 * - pipeline() will create a MiddlewarePipe instance from the array of
 *   middleware passed to it, after passing each first to prepare().
 */
class MiddlewareFactory
{
    /**
     * @var MiddlewareContainer
     */
    private $container;

    public function __construct(MiddlewareContainer $container)
    {
        $this->container = $container;
    }

    /**
     * @param string|array|callable|MiddlewareInterface|RequestHandlerInterface $middleware
     * @throws Exception\InvalidMiddlewareException if argument is not one of
     *    the specified types.
     */
    public function prepare($middleware) : MiddlewareInterface
    {
        if ($middleware instanceof MiddlewareInterface) {
            return $middleware;
        }

        if ($middleware instanceof RequestHandlerInterface) {
            return $this->handler($middleware);
        }

        if (is_callable($middleware)) {
            return $this->callable($middleware);
        }

        if (is_array($middleware)) {
            return $this->pipeline(...$middleware);
        }

        if (! is_string($middleware) || $middleware === '') {
            throw Exception\InvalidMiddlewareException::forMiddleware($middleware);
        }

        return $this->lazy($middleware);
    }

    /**
     * Decorate callable standards-signature middleware via a CallableMiddlewareDecorator.
     */
    public function callable(callable $middleware) : CallableMiddlewareDecorator
    {
        return new CallableMiddlewareDecorator($middleware);
    }

    /**
     * Decorate a RequestHandlerInterface as middleware via RequestHandlerMiddleware.
     */
    public function handler(RequestHandlerInterface $handler) : RequestHandlerMiddleware
    {
        return new RequestHandlerMiddleware($handler);
    }

    /**
     * Create lazy loading middleware based on a service name.
     */
    public function lazy(string $middleware) : Middleware\LazyLoadingMiddleware
    {
        return new Middleware\LazyLoadingMiddleware($this->container, $middleware);
    }

    /**
     * Create a middleware pipeline from an array of middleware.
     *
     * This method allows passing an array of middleware as either:
     *
     * - discrete arguments
     * - an array of middleware, using the splat operator: pipeline(...$array)
     * - an array of middleware as the sole argument: pipeline($array)
     *
     * Each item is passed to prepare() before being passed to the
     * MiddlewarePipe instance the method returns.
     *
     * @param string|array|MiddlewarePipe $middleware
     */
    public function pipeline(...$middleware) : MiddlewarePipe
    {
        // Allow passing arrays of middleware or individual lists of middleware
        if (is_array($middleware[0])
            && count($middleware) === 1
        ) {
            $middleware = array_shift($middleware);
        }

        $pipeline = new MiddlewarePipe();
        foreach ($middleware as $m) {
            $pipeline->pipe($this->prepare($m));
        }
        return $pipeline;
    }
}
