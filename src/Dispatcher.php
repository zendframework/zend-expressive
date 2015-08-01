<?php
/**
 * Zend Framework (http://framework.zend.com/)
 *
 * @see       http://github.com/zendframework/zend-diactoros for the canonical source repository
 * @copyright Copyright (c) 2015 Zend Technologies USA Inc. (http://www.zend.com)
 * @license   https://github.com/zendframework/zend-diactoros/blob/master/LICENSE.md New BSD License
 */

namespace Zend\Expressive;

use Interop\Container\ContainerInterface;
use Interop\Container\Exception\ContainerException;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\ResponseInterface;

class Dispatcher
{
    /**
     * @var null|ContainerInterface
     */
    protected $container;

    /**
     * @var Router\RouterInterface
     */
    protected $router;

    /**
     * Constructor
     *
     * @param Router\RouterInterface $router
     * @param ContainerInterface $container
     */
    public function __construct(Router\RouterInterface $router, ContainerInterface $container = null)
    {
        $this->router    = $router;
        $this->container = $container;
    }

    /**
     * Invoke
     *
     * @param  ServerRequestInterface $request
     * @param  ResponseInterface $response
     * @param  callable $next
     * @return ResponseInterface
     * @throws Exception\InvalidArgumentException if the route result does not contain middleware
     * @throws Exception\InvalidArgumentException if unable to retrieve middleware from the container
     * @throws Exception\InvalidArgumentException if unable to resolve middleware to a callable
     */
    public function __invoke(ServerRequestInterface $request, ResponseInterface $response, callable $next)
    {
        $router = $this->getRouter();
        $result = $router->match($request);

        if ($result->isFailure()) {
            if ($result->isMethodFailure()) {
                $response = $response->withStatus(405)
                    ->withHeader('Allow', implode(',', $result->getAllowedMethods()));
            }
            return $next($request, $response);
        }

        foreach ($result->getMatchedParams() as $param => $value) {
            $request = $request->withAttribute($param, $value);
        }

        $middleware = $result->getMatchedMiddleware();
        if (! $middleware) {
            throw new Exception\InvalidMiddlewareException(sprintf(
                'The route %s does not have a middleware to dispatch',
                $result->getMatchedRouteName()
            ));
        }

        if (is_callable($middleware)) {
            return $middleware($request, $response, $next);
        }

        if (! is_string($middleware)) {
            throw new Exception\InvalidMiddlewareException(
                sprintf("The action class specified %s is not invokable", $action)
            );
        }

        // try to get the action name from the container (if exists)
        $callable = $this->marshalMiddlewareFromContainer($middleware);
        if (is_callable($callable)) {
            return $callable($request, $response, $next);
        }

        // try to instantiate the middleware directly, if possible
        $callable = $this->marshalInvokableMiddleware($middleware);
        if (! is_callable($callable)) {
            throw new Exception\InvalidMiddlewareException(sprintf(
                'Unable to resolve middleware "%s" to a callable',
                $middleware
            ));
        }

        return $callable($request, $response, $next);
    }

    /**
     * Get Router
     *
     * @return Router\RouterInterface
     */
    public function getRouter()
    {
        return $this->router;
    }

    /**
     * Get Container
     *
     * @return ContainerInterface
     */
    public function getContainer()
    {
        return $this->container;
    }

    /**
     * Attempt to retrieve the given middleware from the container.
     *
     * @param string $middleware
     * @return string|callable Returns $middleware intact on failure, and the
     *     middleware instance on success.
     * @throws Exception\InvalidArgumentException if a container exception occurs.
     */
    private function marshalMiddlewareFromContainer($middleware)
    {
        $container = $this->getContainer();
        if (! $container || ! $container->has($middleware)) {
            return $middleware;
        }

        try {
            return $container->get($middleware);
        } catch (ContainerException $e) {
            throw new Exception\InvalidMiddlewareException(sprintf(
                'Unable to retrieve middleware "%s" from the container',
                $middleware
            ), $e->getCode(), $e);
        }
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
}
