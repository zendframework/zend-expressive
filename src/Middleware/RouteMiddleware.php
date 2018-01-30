<?php
/**
 * @see       https://github.com/zendframework/zend-expressive for the canonical source repository
 * @copyright Copyright (c) 2017 Zend Technologies USA Inc. (https://www.zend.com)
 * @license   https://github.com/zendframework/zend-expressive/blob/master/LICENSE.md New BSD License
 */

declare(strict_types=1);

namespace Zend\Expressive\Middleware;

use Fig\Http\Message\StatusCodeInterface as StatusCode;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Zend\Expressive\Exception;
use Zend\Expressive\Router\Route;
use Zend\Expressive\Router\RouteResult;
use Zend\Expressive\Router\RouterInterface;

/**
 * Default routing middleware.
 *
 * Uses the composed router to match against the incoming request.
 *
 * When routing failure occurs, if the failure is due to HTTP method, uses
 * the composed response prototype to generate a 405 response; otherwise,
 * it delegates to the next middleware.
 *
 * If routing succeeds, injects the route result into the request (under the
 * RouteResult class name), as well as any matched parameters, before
 * delegating to the next middleware.
 *
 * @internal
 */
class RouteMiddleware implements MiddlewareInterface
{
    /**
     * Response prototype for 405 responses.
     *
     * @var ResponseInterface
     */
    private $responsePrototype;

    /**
     * @var RouterInterface
     */
    private $router;

    /**
     * List of all routes registered directly with the application.
     *
     * @var Route[]
     */
    private $routes = [];

    public function __construct(RouterInterface $router, ResponseInterface $responsePrototype)
    {
        $this->router = $router;
        $this->responsePrototype = $responsePrototype;
    }

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler) : ResponseInterface
    {
        $result = $this->router->match($request);

        if ($result->isFailure()) {
            if ($result->isMethodFailure()) {
                return $this->responsePrototype->withStatus(StatusCode::STATUS_METHOD_NOT_ALLOWED)
                    ->withHeader('Allow', implode(',', $result->getAllowedMethods()));
            }
            return $handler->handle($request);
        }

        // Inject the actual route result, as well as individual matched parameters.
        $request = $request->withAttribute(RouteResult::class, $result);
        foreach ($result->getMatchedParams() as $param => $value) {
            $request = $request->withAttribute($param, $value);
        }

        return $handler->handle($request);
    }

    /**
     * Add a route for the route middleware to match.
     *
     * Accepts either a Route instance, or a combination of a path and
     * middleware, and optionally the HTTP methods allowed.
     *
     * @param null|array $methods HTTP method to accept; null indicates any.
     * @param null|string $name The name of the route.
     * @throws Exception\InvalidArgumentException if $path is not a Route AND middleware is null.
     * @throws Exception\InvalidMiddlewareException if $middleware is neither a
     *     string nor a MiddlewareInterface instance.
     */
    public function route(
        string $path,
        MiddlewareInterface $middleware,
        array $methods = null,
        string $name = null
    ) : Route {
        $this->checkForDuplicateRoute($path, $methods);

        $methods = null === $methods ? Route::HTTP_METHOD_ANY : $methods;
        $route   = new Route($path, $middleware, $methods, $name);

        $this->routes[] = $route;
        $this->router->addRoute($route);

        return $route;
    }

    /**
     * @param null|string $name The name of the route.
     */
    public function get(string $path, MiddlewareInterface $middleware, string $name = null) : Route
    {
        return $this->route($path, $middleware, ['GET'], $name);
    }

    /**
     * @param null|string $name The name of the route.
     */
    public function post(string $path, MiddlewareInterface $middleware, string $name = null) : Route
    {
        return $this->route($path, $middleware, ['POST'], $name);
    }

    /**
     * @param null|string $name The name of the route.
     */
    public function put(string $path, MiddlewareInterface $middleware, string $name = null) : Route
    {
        return $this->route($path, $middleware, ['PUT'], $name);
    }

    /**
     * @param null|string $name The name of the route.
     */
    public function patch(string $path, MiddlewareInterface $middleware, string $name = null) : Route
    {
        return $this->route($path, $middleware, ['PATCH'], $name);
    }

    /**
     * @param null|string $name The name of the route.
     */
    public function delete(string $path, MiddlewareInterface $middleware, string $name = null) : Route
    {
        return $this->route($path, $middleware, ['DELETE'], $name);
    }

    /**
     * @param null|string $name The name of the route.
     */
    public function any(string $path, MiddlewareInterface $middleware, string $name = null) : Route
    {
        return $this->route($path, $middleware, null, $name);
    }

    /**
     * Retrieve all directly registered routes with the application.
     *
     * @return Route[]
     */
    public function getRoutes() : array
    {
        return $this->routes;
    }

    /**
     * Determine if the route is duplicated in the current list.
     *
     * Checks if a route with the same name or path exists already in the list;
     * if so, and it responds to any of the $methods indicated, raises
     * a DuplicateRouteException indicating a duplicate route.
     *
     * @throws Exception\DuplicateRouteException on duplicate route detection.
     */
    private function checkForDuplicateRoute(string $path, array $methods = null) : void
    {
        if (null === $methods) {
            $methods = Route::HTTP_METHOD_ANY;
        }

        $matches = array_filter($this->routes, function (Route $route) use ($path, $methods) {
            if ($path !== $route->getPath()) {
                return false;
            }

            if ($methods === Route::HTTP_METHOD_ANY) {
                return true;
            }

            return array_reduce($methods, function ($carry, $method) use ($route) {
                return ($carry || $route->allowsMethod($method));
            }, false);
        });

        if (! empty($matches)) {
            throw new Exception\DuplicateRouteException(
                'Duplicate route detected; same name or path, and one or more HTTP methods intersect'
            );
        }
    }
}
