<?php
/**
 * @see       https://github.com/zendframework/zend-expressive for the canonical source repository
 * @copyright Copyright (c) 2016 Zend Technologies USA Inc. (http://www.zend.com)
 * @license   https://github.com/zendframework/zend-expressive/blob/master/LICENSE.md New BSD License
 */

namespace Zend\Expressive\Middleware;

use Fig\Http\Message\RequestMethodInterface as RequestMethod;
use Fig\Http\Message\StatusCodeInterface as StatusCode;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Zend\Diactoros\Response;
use Zend\Expressive\Router\RouteResult;

/**
 * Handle implicit OPTIONS requests.
 *
 * Place this middleware after the routing middleware so that it can handle
 * implicit OPTIONS requests -- requests where OPTIONS is used, but the route
 * does not explicitly handle that request method.
 *
 * When invoked, it will create a response with status code 200 and an Allow
 * header that defines all accepted request methods.
 *
 * You may optionally pass a response prototype to the constructor; when
 * present, that prototype will be used to create a new response with the
 * Allow header.
 *
 * The middleware is only invoked in these specific conditions:
 *
 * - an OPTIONS request
 * - with a `RouteResult` present
 * - where the `RouteResult` contains a `Route` instance
 * - and the `Route` instance defines implicit OPTIONS.
 *
 * In all other circumstances, it will return the result of the delegate.
 */
class ImplicitOptionsMiddleware
{
    /**
     * @var null|ResponseInterface
     */
    private $response;

    /**
     * @param null|ResponseInterface $response Response prototype to use for
     *     implicit OPTIONS requests; if not provided a zend-diactoros Response
     *     instance will be created and used.
     */
    public function __construct(ResponseInterface $response = null)
    {
        $this->response = $response;
    }

    /**
     * Handle an implicit OPTIONS request.
     *
     * @param ServerRequestInterface $request
     * @param ResponseInterface $response
     * @param callable $next
     * @return ResponseInterface
     */
    public function __invoke(ServerRequestInterface $request, ResponseInterface $response, callable $next)
    {
        if ($request->getMethod() !== RequestMethod::METHOD_OPTIONS) {
            return $next($request, $response);
        }

        if (false === ($result = $request->getAttribute(RouteResult::class, false))) {
            return $next($request, $response);
        }

        $route = $result->getMatchedRoute();
        if (! $route || ! $route->implicitOptions()) {
            return $next($request, $response);
        }

        $methods = implode(',', $route->getAllowedMethods());
        return $this->getResponse()->withHeader('Allow', $methods);
    }

    /**
     * Return the response prototype to use for an implicit OPTIONS request.
     *
     * @return ResponseInterface
     */
    private function getResponse()
    {
        if ($this->response) {
            return $this->response;
        }

        return new Response('php://temp', StatusCode::STATUS_OK);
    }
}
