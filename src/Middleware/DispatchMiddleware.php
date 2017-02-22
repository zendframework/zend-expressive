<?php
/**
 * @see       https://github.com/zendframework/zend-expressive for the canonical source repository
 * @copyright Copyright (c) 2017 Zend Technologies USA Inc. (http://www.zend.com)
 * @license   https://github.com/zendframework/zend-expressive/blob/master/LICENSE.md New BSD License
 */

namespace Zend\Expressive\Middleware;

use Interop\Http\ServerMiddleware\DelegateInterface;
use Interop\Http\ServerMiddleware\MiddlewareInterface as ServerMiddlewareInterface;
use Psr\Container\ContainerInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Zend\Expressive\Router\RouteResult;

/**
 * Default dispatch middleware.
 *
 * Uses the composed container to marshal the middleware matched during
 * routing, and then dispatches it.
 *
 * If no route result is present in the request, delegates to the next
 * middleware.
 *
 * @internal
 */
class DispatchMiddleware implements ServerMiddlewareInterface
{
    /**
     * @var null|ContainerInterface
     */
    private $container;

    public function __construct(ContainerInterface $container = null)
    {
        $this->container = $container;
    }

    /**
     * @param ServerRequestInterface $request
     * @param DelegateInterface $delegate
     * @return ResponseInterface
     */
    public function process(ServerRequestInterface $request, DelegateInterface $delegate)
    {
        $routeResult = $request->getAttribute(RouteResult::class, false);
        if (! $routeResult) {
            return $delegate->process($request);
        }

        $middleware = $routeResult->getMatchedMiddleware();

        if (is_callable($middleware)) {
            $middleware = $middleware();
        }

        if (is_string($middleware) && $this->container && $this->container->has($middleware)) {
            $middleware = $this->container->get($middleware);
        }

        return $middleware->process($request, $delegate);
    }
}
