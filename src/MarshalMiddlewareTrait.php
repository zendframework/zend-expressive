<?php
/**
 * @see       https://github.com/zendframework/zend-expressive for the canonical source repository
 * @copyright Copyright (c) 2015-2017 Zend Technologies USA Inc. (http://www.zend.com)
 * @license   https://github.com/zendframework/zend-expressive/blob/master/LICENSE.md New BSD License
 */

namespace Zend\Expressive;

use Closure;
use Interop\Container\ContainerInterface;
use Interop\Http\Middleware\DelegateInterface;
use Interop\Http\Middleware\ServerMiddlewareInterface;
use ReflectionFunction;
use ReflectionMethod;
use Zend\Stratigility\Delegate\CallableDelegateDecorator;
use Zend\Stratigility\MiddlewarePipe;

/**
 * Trait defining methods for verifying and/or generating middleware to pipe to
 * an application.
 */
trait MarshalMiddlewareTrait
{
    /**
     * Prepare middleware for piping.
     *
     * Performs a number of checks on $middleware to prepare it for piping
     * to the application:
     *
     * - If it's callable, it's returned immediately.
     * - If it's a non-callable array, it's passed to marshalMiddlewarePipe().
     * - If it's a string service name, it's passed to marshalLazyMiddlewareService().
     * - If it's a string class name, it's passed to marshalInvokableMiddleware().
     * - If no callable is created, an exception is thrown.
     *
     * @param mixed $middleware
     * @param null|ContainerInterface $container
     * @param bool $forError Whether or not generated middleware is intended to
     *     represent error middleware; defaults to false.
     * @return callable
     * @throws Exception\InvalidMiddlewareException
     */
    private function prepareMiddleware($middleware, ContainerInterface $container = null, $forError = false)
    {
        if (is_callable($middleware)) {
            return $middleware;
        }

        if ($middleware === Application::ROUTING_MIDDLEWARE) {
            return [$this, 'routeMiddleware'];
        }

        if ($middleware === Application::DISPATCH_MIDDLEWARE) {
            return [$this, 'dispatchMiddleware'];
        }

        if (is_array($middleware)) {
            return $this->marshalMiddlewarePipe($middleware, $container, $forError);
        }

        if (is_string($middleware) && $container && $container->has($middleware)) {
            $method = $forError ? 'marshalLazyErrorMiddlewareService' : 'marshalLazyMiddlewareService';
            return $this->{$method}($middleware, $container);
        }

        $callable = $middleware;
        if (is_string($middleware)) {
            $callable = $this->marshalInvokableMiddleware($middleware);
        }

        if (! is_callable($callable)) {
            throw new Exception\InvalidMiddlewareException(
                sprintf(
                    'Unable to resolve middleware "%s" to a callable',
                    (is_object($middleware)
                    ? get_class($middleware) . "[Object]"
                    : gettype($middleware) . '[Scalar]')
                )
            );
        }

        return $callable;
    }

    /**
     * Marshal a middleware pipe from an array of middleware.
     *
     * Each item in the array can be one of the following:
     *
     * - A callable middleware
     * - A string service name of middleware to retrieve from the container
     * - A string class name of a constructor-less middleware class to
     *   instantiate
     *
     * As each middleware is verified, it is piped to the middleware pipe.
     *
     * @param array $middlewares
     * @param null|ContainerInterface $container
     * @param bool $forError Whether or not the middleware pipe generated is
     *     intended to be populated with error middleware; defaults to false.
     * @return MiddlewarePipe|ErrorMiddlewarePipe When $forError is true,
     *     returns an ErrorMiddlewarePipe.
     * @throws Exception\InvalidMiddlewareException for any invalid middleware items.
     */
    private function marshalMiddlewarePipe(array $middlewares, ContainerInterface $container = null, $forError = false)
    {
        $middlewarePipe = new MiddlewarePipe();

        if ($this->raiseThrowables) {
            $middlewarePipe->raiseThrowables();
        }

        foreach ($middlewares as $middleware) {
            $middlewarePipe->pipe(
                $this->prepareMiddleware($middleware, $container, $forError)
            );
        }

        if ($forError) {
            return new ErrorMiddlewarePipe($middlewarePipe);
        }

        return $middlewarePipe;
    }

    /**
     * Attempt to instantiate the given middleware.
     *
     * @param string $middleware
     * @return string|callable Returns $middleware intact on failure, and the
     *     middleware instance on success.
     */
    private function marshalInvokableMiddleware($middleware)
    {
        if (! class_exists($middleware)) {
            return $middleware;
        }

        return new $middleware();
    }

    /**
     * @param string $middleware
     * @param ContainerInterface $container
     * @return callable
     */
    private function marshalLazyMiddlewareService($middleware, ContainerInterface $container)
    {
        return function ($request, $response, $next = null) use ($container, $middleware) {
            $invokable = $container->get($middleware);

            // http-interop middleware
            if ($invokable instanceof ServerMiddlewareInterface
                && ! $invokable instanceof MiddlewarePipe
            ) {
                return $invokable->process(
                    $request,
                    $next instanceof DelegateInterface
                        ? $next
                        : new CallableDelegateDecorator($next, $response)
                );
            }

            // Middleware pipeline
            if ($invokable instanceof MiddlewarePipe) {
                if ($this->raiseThrowables) {
                    $invokable->raiseThrowables();
                }

                return $invokable($request, $response, $next);
            }

            // Unknown - invalid!
            if (! is_callable($invokable)) {
                throw new Exception\InvalidMiddlewareException(sprintf(
                    'Lazy-loaded middleware "%s" is not invokable',
                    $middleware
                ));
            }

            // Callable http-interop middleware
            if ($this->isCallableInteropMiddleware($invokable)) {
                return $invokable(
                    $request,
                    $next instanceof DelegateInterface
                        ? $next
                        : new CallableDelegateDecorator($next, $response)
                );
            }

            // Legacy double-pass signature
            return $invokable($request, $response, $next);
        };
    }

    /**
     * @param string $middleware
     * @param ContainerInterface $container
     * @return callable
     */
    private function marshalLazyErrorMiddlewareService($middleware, ContainerInterface $container)
    {
        return function ($error, $request, $response, $next) use ($container, $middleware) {
            $invokable = $container->get($middleware);
            if (! is_callable($invokable)) {
                throw new Exception\InvalidMiddlewareException(sprintf(
                    'Lazy-loaded middleware "%s" is not invokable',
                    $middleware
                ));
            }
            return $invokable($error, $request, $response, $next);
        };
    }

    /**
     * Is callable middleware interop middleware?
     *
     * @param callable $middleware
     * @return bool
     */
    private function isCallableInteropMiddleware(callable $middleware)
    {
        $r = $this->reflectMiddleware($middleware);
        $paramsCount = $r->getNumberOfParameters();

        return $paramsCount === 2;
    }

    /**
     * Reflect a callable middleware.
     *
     * Duplicates MiddlewarePipe::getReflectionFunction, but that method is not
     * callable due to private visibility.
     *
     * @param callable $middleware
     * @return \ReflectionFunctionAbstract
     */
    private function reflectMiddleware(callable $middleware)
    {
        if (is_array($middleware)) {
            $class = array_shift($middleware);
            $method = array_shift($middleware);
            return new ReflectionMethod($class, $method);
        }

        if ($middleware instanceof Closure || ! is_object($middleware)) {
            return new ReflectionFunction($middleware);
        }

        return new ReflectionMethod($middleware, '__invoke');
    }
}
