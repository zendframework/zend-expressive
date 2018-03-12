<?php
/**
 * @see       https://github.com/zendframework/zend-expressive for the canonical source repository
 * @copyright Copyright (c) 2015-2017 Zend Technologies USA Inc. (http://www.zend.com)
 * @license   https://github.com/zendframework/zend-expressive/blob/master/LICENSE.md New BSD License
 */

namespace Zend\Expressive;

use Psr\Container\ContainerInterface;
use Psr\Http\Message\ResponseInterface;
use Webimpress\HttpMiddlewareCompatibility\MiddlewareInterface;
use Zend\Expressive\Router\Middleware\DispatchMiddleware;
use Zend\Expressive\Router\Middleware\RouteMiddleware;
use Zend\Stratigility\MiddlewarePipe;

use function Zend\Stratigility\middleware;
use function Zend\Stratigility\doublePassMiddleware;

/**
 * Trait defining methods for verifying and/or generating middleware to pipe to
 * an application.
 *
 * @deprecated since 2.2.0. This feature will be removed in version 3.0.0, and
 *     replaced with a combination of a PSR-11 container and a composable factory
 *     class.
 * @internal
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
     * @return MiddlewareInterface
     * @throws Exception\InvalidMiddlewareException
     */
    private function prepareMiddleware(
        $middleware,
        Router\RouterInterface $router,
        ResponseInterface $responsePrototype,
        ContainerInterface $container = null
    ) {
        if ($middleware === Application::ROUTING_MIDDLEWARE) {
            $this->triggerLegacyMiddlewareDeprecation($middleware);
            return $container && $container->has(RouteMiddleware::class)
                ? $container->get(RouteMiddleware::class)
                : new RouteMiddleware($router, $responsePrototype);
        }

        if ($middleware === Application::DISPATCH_MIDDLEWARE) {
            $this->triggerLegacyMiddlewareDeprecation($middleware);
            return $container && $container->has(DispatchMiddleware::class)
                ? $container->get(DispatchMiddleware::class)
                : new DispatchMiddleware();
        }

        if ($middleware instanceof MiddlewareInterface) {
            return $middleware;
        }

        if ($this->isCallableInteropMiddleware($middleware)) {
            return middleware($middleware);
        }

        if ($this->isCallable($middleware)) {
            $this->triggerDoublePassMiddlewareDeprecation($middleware);
            return doublePassMiddleware($middleware, $responsePrototype);
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
            'Unable to resolve middleware "%s" to a callable or MiddlewareInterface implementation',
            is_object($middleware) ? get_class($middleware) . '[Object]' : gettype($middleware) . '[Scalar]'
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

        if ($instance instanceof MiddlewareInterface) {
            return $instance;
        }

        if ($this->isCallableInteropMiddleware($instance)) {
            return middleware($instance);
        }

        if (! is_callable($instance)) {
            throw new Exception\InvalidMiddlewareException(sprintf(
                'Middleware of class "%s" is invalid; neither invokable nor a MiddlewareInterface instance',
                $middleware
            ));
        }

        $this->triggerDoublePassMiddlewareDeprecation($instance);
        return doublePassMiddleware($instance, $responsePrototype);
    }

    /**
     * @param string $middlewareType
     * @return void
     */
    private function triggerLegacyMiddlewareDeprecation($middlewareType)
    {
        switch ($middlewareType) {
            case (Application::ROUTING_MIDDLEWARE):
                $constant   = sprintf('%s::ROUTING_MIDDLEWARE', Application::class);
                $type       = 'routing';
                $useInstead = RouteMiddleware::class;
                break;
            case (Application::DISPATCH_MIDDLEWARE):
                $constant   = sprintf('%s::DISPATCH_MIDDLEWARE', Application::class);
                $type       = 'dispatch';
                $useInstead = DispatchMiddleware::class;
                break;
        }

        trigger_error(sprintf(
            'Usage of the %s constant for specifying %s middleware is deprecated;'
            . ' pipe() the middleware directly, or reference it by its service name "%s"',
            $constant,
            $type,
            $useInstead
        ), E_USER_DEPRECATED);
    }

    /**
     * @param callable $middleware
     * @return void
     */
    private function triggerDoublePassMiddlewareDeprecation(callable $middleware)
    {
        if (is_object($middleware)) {
            $type = get_class($middleware);
        } elseif (is_string($middleware)) {
            $type = 'callable:' . $middleware;
        } else {
            $type = 'callable';
        }

        trigger_error(sprintf(
            'Detected double-pass middleware (%s).'
            . ' Usage of callable double-pass middleware is deprecated. Before piping or routing'
            . ' such middleware, pass it to Zend\Stratigility\doublePassMiddleware(), along with'
            . ' a PSR-7 response instance.',
            $type
        ), E_USER_DEPRECATED);
    }
}
