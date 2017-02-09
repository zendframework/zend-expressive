<?php
/**
 * @see       https://github.com/zendframework/zend-expressive for the canonical source repository
 * @copyright Copyright (c) 2016 Zend Technologies USA Inc. (http://www.zend.com)
 * @license   https://github.com/zendframework/zend-expressive/blob/master/LICENSE.md New BSD License
 */

namespace Zend\Expressive\Middleware;

use Fig\Http\Message\RequestMethodInterface as RequestMethod;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Zend\Diactoros\Response;
use Zend\Diactoros\Stream;
use Zend\Expressive\Router\RouteResult;

/**
 * Handle implicit HEAD requests.
 *
 * Place this middleware after the routing middleware so that it can handle
 * implicit HEAD requests -- requests where HEAD is used, but the route does
 * not explicitly handle that request method.
 *
 * When invoked, it will create an empty response with status code 200.
 *
 * You may optionally pass a response prototype to the constructor; when
 * present, that instance will be returned instead.
 *
 * The middleware is only invoked in these specific conditions:
 *
 * - a HEAD request
 * - with a `RouteResult` present
 * - where the `RouteResult` contains a `Route` instance
 * - and the `Route` instance defines implicit HEAD.
 *
 * In all other circumstances, it will return the result of the delegate.
 *
 * If the route instance supports GET requests, the middleware dispatches
 * the next layer, but alters the request passed to use the GET method;
 * it then provides an empty response body to the returned response.
 */
class ImplicitHeadMiddleware
{
    /**
     * @var null|ResponseInterface
     */
    private $response;

    /**
     * @param null|ResponseInterface $response Response prototype to return
     *     for implicit HEAD requests; if none provided, an empty zend-diactoros
     *     instance will be created.
     */
    public function __construct(ResponseInterface $response = null)
    {
        $this->response = $response;
    }

    /**
     * Handle an implicit HEAD request.
     *
     * If the route allows GET requests, dispatches as a GET request and
     * resets the response body to be empty; otherwise, creates a new empty
     * response.
     *
     * @param ServerRequestInterface $request
     * @param ResponseInterface $response
     * @param callable $next
     * @return ResponseInterface
     */
    public function __invoke(ServerRequestInterface $request, ResponseInterface $response, callable $next)
    {
        if ($request->getMethod() !== RequestMethod::METHOD_HEAD) {
            return $next($request, $response);
        }

        if (false === ($result = $request->getAttribute(RouteResult::class, false))) {
            return $next($request, $response);
        }

        $route = $result->getMatchedRoute();
        if (! $route || ! $route->implicitHead()) {
            return $next($request, $response);
        }

        if (! $route->allowsMethod(RequestMethod::METHOD_GET)) {
            return $this->getResponse();
        }

        $response = $next(
            $request->withMethod(RequestMethod::METHOD_GET),
            $response
        );

        return $response->withBody(new Stream('php://temp/', 'wb+'));
    }

    /**
     * Return the response prototype to use for an implicit HEAD request.
     *
     * @return ResponseInterface
     */
    private function getResponse()
    {
        if ($this->response) {
            return $this->response;
        }

        return new Response();
    }
}
