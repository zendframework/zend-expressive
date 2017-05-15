<?php
/**
 * @see       https://github.com/zendframework/zend-expressive for the canonical source repository
 * @copyright Copyright (c) 2015-2017 Zend Technologies USA Inc. (http://www.zend.com)
 * @license   https://github.com/zendframework/zend-expressive/blob/master/LICENSE.md New BSD License
 */

namespace Zend\Expressive;

use Interop\Http\ServerMiddleware\MiddlewareInterface as ServerMiddlewareInterface;
use Psr\Container\ContainerInterface;
use Psr\Http\Message\ResponseInterface;
use Zend\Stratigility\Middleware\CallableInteropMiddlewareWrapper;
use Zend\Stratigility\Middleware\CallableMiddlewareWrapper;
use Zend\Stratigility\MiddlewarePipe;

/**
 * Trait defining methods for verifying and/or generating middleware to pipe to
 * an application.
 */
trait MarshalMiddlewareTrait
{
    use IsCallableInteropMiddlewareTrait;

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
     * @param Router\RouterInterface $router
     * @param ResponseInterface $responsePrototype
     * @param null|ContainerInterface $container
     * @return ServerMiddlewareInterface
     * @throws Exception\InvalidMiddlewareException
     */
    private function prepareMiddleware(
        $middleware,
        Router\RouterInterface $router,
        ResponseInterface $responsePrototype,
        ContainerInterface $container = null
    ) {
        if ($middleware === Application::ROUTING_MIDDLEWARE) {
            return new Middleware\RouteMiddleware($router, $responsePrototype);
        }

        if ($middleware === Application::DISPATCH_MIDDLEWARE) {
            return new Middleware\DispatchMiddleware($router, $responsePrototype, $container);
        }

        if ($middleware instanceof ServerMiddlewareInterface) {
            return $middleware;
        }

        if ($this->isCallableInteropMiddleware($middleware)) {
            return new CallableInteropMiddlewareWrapper($middleware);
        }

        if (is_callable($middleware)) {
            return new CallableMiddlewareWrapper($middleware, $responsePrototype);
        }

        if (is_array($middleware)) {
            return $this->marshalMiddlewarePipe($middleware, $router, $responsePrototype, $container);
        }

        if (is_string($middleware) && $container && $container->has($middleware)) {
            return new Middleware\LazyLoadingMiddleware($container, $responsePrototype, $middleware);
        }

        if (is_string($middleware)) {
            return $this->marshalInvokableMiddleware($middleware, $responsePrototype);
        }

        throw new Exception\InvalidMiddlewareException(sprintf(
            'Unable to resolve middleware "%s" to a callable or %s',
            is_object($middleware) ? get_class($middleware) . '[Object]' : gettype($middleware) . '[Scalar]',
            ServerMiddlewareInterface::class
        ));
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
     * @param Router\RouterInterface $router
     * @param ResponseInterface $responsePrototype
     * @param null|ContainerInterface $container
     * @return MiddlewarePipe
     * @throws Exception\InvalidMiddlewareException for any invalid middleware items.
     */
    private function marshalMiddlewarePipe(
        array $middlewares,
        Router\RouterInterface $router,
        ResponseInterface $responsePrototype,
        ContainerInterface $container = null
    ) {
        $middlewarePipe = new MiddlewarePipe();
        $middlewarePipe->setResponsePrototype($responsePrototype);

        foreach ($middlewares as $middleware) {
            $middlewarePipe->pipe(
                $this->prepareMiddleware($middleware, $router, $responsePrototype, $container)
            );
        }

        return $middlewarePipe;
    }

    /**
     * Attempt to instantiate the given middleware.
     *
     * @param string $middleware
     * @param ResponseInterface $responsePrototype
     * @return ServerMiddlewareInterface
     * @throws Exception\InvalidMiddlewareException if $middleware is not a class.
     * @throws Exception\InvalidMiddlewareException if $middleware does not resolve
     *     to either an invokable class or ServerMiddlewareInterface instance.
     */
    private function marshalInvokableMiddleware($middleware, ResponseInterface $responsePrototype)
    {
        if (! class_exists($middleware)) {
            throw new Exception\InvalidMiddlewareException(sprintf(
                'Unable to create middleware "%s"; not a valid class or service name',
                $middleware
            ));
        }

        $instance = new $middleware();

        if ($instance instanceof ServerMiddlewareInterface) {
            return $instance;
        }

        if ($this->isCallableInteropMiddleware($instance)) {
            return new CallableInteropMiddlewareWrapper($instance);
        }

        if (! is_callable($instance)) {
            throw new Exception\InvalidMiddlewareException(sprintf(
                'Middleware of class "%s" is invalid; neither invokable nor %s',
                $middleware,
                ServerMiddlewareInterface::class
            ));
        }

        return new CallableMiddlewareWrapper($instance, $responsePrototype);
    }
}
