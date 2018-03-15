<?php
/**
 * @see       https://github.com/zendframework/zend-expressive for the canonical source repository
 * @copyright Copyright (c) 2015-2018 Zend Technologies USA Inc. (https://www.zend.com)
 * @license   https://github.com/zendframework/zend-expressive/blob/master/LICENSE.md New BSD License
 */

declare(strict_types=1);

namespace Zend\Expressive;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Zend\Expressive\Router\RouteCollector;
use Zend\HttpHandlerRunner\RequestHandlerRunner;
use Zend\Stratigility\MiddlewarePipeInterface;

use function Zend\Stratigility\path;

class Application implements MiddlewareInterface, RequestHandlerInterface
{
    /**
     * @var MiddlewareFactory
     */
    private $factory;

    /**
     * @var MiddlewarePipeInterface
     */
    private $pipeline;

    /**
     * @var RouteCollector
     */
    private $routes;

    /**
     * @var RequestHandlerRunner
     */
    private $runner;

    public function __construct(
        MiddlewareFactory $factory,
        MiddlewarePipeInterface $pipeline,
        RouteCollector $routes,
        RequestHandlerRunner $runner
    ) {
        $this->factory = $factory;
        $this->pipeline = $pipeline;
        $this->routes = $routes;
        $this->runner = $runner;
    }

    /**
     * Proxies to composed pipeline to handle.
     * {@inheritDocs}
     */
    public function handle(ServerRequestInterface $request) : ResponseInterface
    {
        return $this->pipeline->handle($request);
    }

    /**
     * Proxies to composed pipeline to process.
     * {@inheritDocs}
     */
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler) : ResponseInterface
    {
        return $this->pipeline->process($request, $handler);
    }

    /**
     * Run the application.
     *
     * Proxies to the RequestHandlerRunner::run() method.
     */
    public function run() : void
    {
        $this->runner->run();
    }

    /**
     * Pipe middleware to the pipeline.
     *
     * If two arguments are present, they are passed to pipe(), after first
     * passing the second argument to the factory's prepare() method.
     *
     * If only one argument is presented, it is passed to the factory prepare()
     * method.
     *
     * The resulting middleware, in both cases, is piped to the pipeline.
     *
     * @param string|array|callable|MiddlewareInterface|RequestHandlerInterface $middlewareOrPath
     *     Either the middleware to pipe, or the path to segregate the $middleware
     *     by, via a PathMiddlewareDecorator.
     * @param null|string|array|callable|MiddlewareInterface|RequestHandlerInterface $middleware
     *     If present, middleware or request handler to segregate by the path
     *     specified in $middlewareOrPath.
     */
    public function pipe($middlewareOrPath, $middleware = null) : void
    {
        $middleware = $middleware ?: $middlewareOrPath;
        $path = $middleware === $middlewareOrPath ? '/' : $middlewareOrPath;

        $middleware = $path !== '/'
            ? path($path, $this->factory->prepare($middleware))
            : $this->factory->prepare($middleware);

        $this->pipeline->pipe($middleware);
    }

    /**
     * Add a route for the route middleware to match.
     *
     * @param string|array|callable|MiddlewareInterface|RequestHandlerInterface $middleware
     *     Middleware or request handler (or service name resolving to one of
     *     those types) to associate with route.
     * @param null|array $methods HTTP method to accept; null indicates any.
     * @param null|string $name The name of the route.
     */
    public function route(string $path, $middleware, array $methods = null, string $name = null) : Router\Route
    {
        return $this->routes->route(
            $path,
            $this->factory->prepare($middleware),
            $methods,
            $name
        );
    }

    /**
     * @param string|array|callable|MiddlewareInterface|RequestHandlerInterface $middleware
     *     Middleware or request handler (or service name resolving to one of
     *     those types) to associate with route.
     * @param null|string $name The name of the route.
     */
    public function get(string $path, $middleware, string $name = null) : Router\Route
    {
        return $this->route($path, $middleware, ['GET'], $name);
    }

    /**
     * @param string|array|callable|MiddlewareInterface|RequestHandlerInterface $middleware
     *     Middleware or request handler (or service name resolving to one of
     *     those types) to associate with route.
     * @param null|string $name The name of the route.
     */
    public function post(string $path, $middleware, $name = null) : Router\Route
    {
        return $this->route($path, $middleware, ['POST'], $name);
    }

    /**
     * @param string|array|callable|MiddlewareInterface|RequestHandlerInterface $middleware
     *     Middleware or request handler (or service name resolving to one of
     *     those types) to associate with route.
     * @param null|string $name The name of the route.
     */
    public function put(string $path, $middleware, string $name = null) : Router\Route
    {
        return $this->route($path, $middleware, ['PUT'], $name);
    }

    /**
     * @param string|array|callable|MiddlewareInterface|RequestHandlerInterface $middleware
     *     Middleware or request handler (or service name resolving to one of
     *     those types) to associate with route.
     * @param null|string $name The name of the route.
     */
    public function patch(string $path, $middleware, string $name = null) : Router\Route
    {
        return $this->route($path, $middleware, ['PATCH'], $name);
    }

    /**
     * @param string|array|callable|MiddlewareInterface|RequestHandlerInterface $middleware
     *     Middleware or request handler (or service name resolving to one of
     *     those types) to associate with route.
     * @param null|string $name The name of the route.
     */
    public function delete(string $path, $middleware, string $name = null) : Router\Route
    {
        return $this->route($path, $middleware, ['DELETE'], $name);
    }

    /**
     * @param string|array|callable|MiddlewareInterface|RequestHandlerInterface $middleware
     *     Middleware or request handler (or service name resolving to one of
     *     those types) to associate with route.
     * @param null|string $name The name of the route.
     */
    public function any(string $path, $middleware, string $name = null) : Router\Route
    {
        return $this->route($path, $middleware, null, $name);
    }

    /**
     * Retrieve all directly registered routes with the application.
     *
     * @return Router\Route[]
     */
    public function getRoutes() : array
    {
        return $this->routes->getRoutes();
    }
}
