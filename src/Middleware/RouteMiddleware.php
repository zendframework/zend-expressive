<?php
/**
 * @see       https://github.com/zendframework/zend-expressive for the canonical source repository
 * @copyright Copyright (c) 2017 Zend Technologies USA Inc. (http://www.zend.com)
 * @license   https://github.com/zendframework/zend-expressive/blob/master/LICENSE.md New BSD License
 */
declare(strict_types=1);

namespace Zend\Expressive\Middleware;

use Fig\Http\Message\StatusCodeInterface as StatusCode;
use Interop\Http\Server\MiddlewareInterface;
use Interop\Http\Server\RequestHandlerInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
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
}
