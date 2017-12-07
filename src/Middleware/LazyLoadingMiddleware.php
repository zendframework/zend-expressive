<?php
/**
 * @see       https://github.com/zendframework/zend-expressive for the canonical source repository
 * @copyright Copyright (c) 2017 Zend Technologies USA Inc. (https://www.zend.com)
 * @license   https://github.com/zendframework/zend-expressive/blob/master/LICENSE.md New BSD License
 */

declare(strict_types=1);

namespace Zend\Expressive\Middleware;

use Interop\Http\Server\MiddlewareInterface;
use Interop\Http\Server\RequestHandlerInterface;
use Psr\Container\ContainerInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Zend\Expressive\Exception\InvalidMiddlewareException;
use Zend\Expressive\IsCallableInteropMiddlewareTrait;

class LazyLoadingMiddleware implements MiddlewareInterface
{
    use IsCallableInteropMiddlewareTrait;

    /**
     * @var ContainerInterface
     */
    private $container;

    /**
     * @var string
     */
    private $middlewareName;

    /**
     * @var ResponseInterface
     */
    private $responsePrototype;

    public function __construct(
        ContainerInterface $container,
        ResponseInterface $responsePrototype,
        $middlewareName
    ) {
        $this->container = $container;
        $this->responsePrototype = $responsePrototype;
        $this->middlewareName = $middlewareName;
    }

    /**
     * @param ServerRequestInterface $request
     * @param RequestHandlerInterface $handler
     * @return ResponseInterface
     * @throws InvalidMiddlewareException for invalid middleware types pulled
     *     from the container.
     */
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler) : ResponseInterface
    {
        $middleware = $this->container->get($this->middlewareName);

        // http-interop middleware
        if ($middleware instanceof MiddlewareInterface) {
            return $middleware->process($request, $handler);
        }

        // Unknown - invalid!
        if (! is_callable($middleware)) {
            throw new InvalidMiddlewareException(sprintf(
                'Lazy-loaded middleware "%s" is neither invokable nor implements %s',
                $this->middlewareName,
                MiddlewareInterface::class
            ));
        }

        // Callable http-interop middleware
        if ($this->isCallableInteropMiddleware($middleware)) {
            return $middleware($request, $handler);
        }

        // Legacy double-pass signature
        return $middleware($request, $this->responsePrototype, function ($request, $response) use ($handler) {
            return $handler->handle($request);
        });
    }
}
