<?php
/**
 * @see       https://github.com/zendframework/zend-expressive for the canonical source repository
 * @copyright Copyright (c) 2018 Zend Technologies USA Inc. (https://www.zend.com)
 * @license   https://github.com/zendframework/zend-expressive/blob/master/LICENSE.md New BSD License
 */

declare(strict_types=1);

namespace Zend\Expressive;

use Psr\Container\ContainerInterface;
use Psr\Http\Server\MiddlewareInterface;
use Zend\Stratigility\MiddlewarePipe;
use Zend\Stratigility\Middleware\PathMiddlewareDecorator;

/**
 * Marshal middleware for use in the application.
 *
 * This class provides a number of methods for preparing and returning
 * middleware for use within an application.
 *
 * If any middleware provided is already a MiddlewareInterface, it can be used
 * verbatim or decorated as-is. Other middleware types acceptable are:
 *
 * - string service names resolving to middleware
 * - arrays of service names and/or MiddlewareInterface instances
 * - PHP callables that follow the PSR-15 signature
 *
 * Additionally, the class provides several decorator/utility methods:
 *
 * - callable() will decorate the callable middleware passed to it using
 *   CallableMiddlewareDecorator.
 * - path() will decorate the path and middleware passed to it using
 *   PathMiddlewareDecorator, after first passing the middleware to the prepare()
 *   method.
 * - pipeline() will create a MiddlewarePipe instance from the array of
 *   middleware passed to it, after passing each first to prepare().
 */
class MiddlewareFactory
{
    /**
     * @var null|MiddlewareContainer
     */
    private $container;

    public function __construct(ContainerInterface $container = null)
    {
        $this->container = $container ? new MiddlewareContainer($container) : null;
    }

    /**
     * @param string|array|callable|MiddlewareInterface $middleware
     * @throws Exception\InvalidMiddlewareException if argument is not one of
     *    the specified types.
     */
    public function prepare($middleware) : MiddlewareInterface
    {
        if ($middleware instanceof MiddlewareInterface) {
            return $middleware;
        }

        if (is_callable($middleware)) {
            return $this->callable($middleware);
        }

        if (is_array($middleware)) {
            return $this->pipeline(...$middleware);
        }

        if ((! is_string($middleware) || empty($middleware))) {
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
     * Create lazy loading middleware based on a service name.
     */
    public function lazy(string $middleware) : Middleware\LazyLoadingMiddleware
    {
        if (! $this->container) {
            throw new Exception\ContainerNotRegisteredException(sprintf(
                'Cannot marshal middleware by service name "%s"; no container registered',
                $middleware
            ));
        }

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
        if (count($middleware) === 1
            && is_array($middleware[0])
        ) {
            $middleware = array_shift($middleware);
        }

        $pipeline = new MiddlewarePipe();
        foreach ($middleware as $m) {
            $pipeline->pipe($this->prepare($m));
        }
        return $pipeline;
    }

    /**
     * Segregate one or more middleware by path.
     *
     * Creates and returns a PathMiddlewareDecorator instance after first
     * passing the $middleware argument to prepare().
     *
     * @param string|array|MiddlewareInterface $middleware
     */
    public function path(string $path, $middleware) : PathMiddlewareDecorator
    {
        return new PathMiddlewareDecorator(
            $path,
            $this->prepare($middleware)
        );
    }
}
