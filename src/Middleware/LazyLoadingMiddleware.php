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
use Zend\Expressive\Exception\InvalidMiddlewareException;
use Zend\Expressive\IsCallableInteropMiddlewareTrait;

class LazyLoadingMiddleware implements ServerMiddlewareInterface
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
     * @param DelegateInterface $delegate
     * @return ResponseInterface
     * @throws InvalidMiddlewareException for invalid middleware types pulled
     *     from the container.
     */
    public function process(ServerRequestInterface $request, DelegateInterface $delegate)
    {
        $middleware = $this->container->get($this->middlewareName);

        // http-interop middleware
        if ($middleware instanceof ServerMiddlewareInterface) {
            return $middleware->process($request, $delegate);
        }

        // Unknown - invalid!
        if (! is_callable($middleware)) {
            throw new InvalidMiddlewareException(sprintf(
                'Lazy-loaded middleware "%s" is neither invokable nor implements %s',
                $this->middlewareName,
                ServerMiddlewareInterface::class
            ));
        }

        // Callable http-interop middleware
        if ($this->isCallableInteropMiddleware($middleware)) {
            return $middleware($request, $delegate);
        }

        // Legacy double-pass signature
        return $middleware($request, $this->responsePrototype, function ($request, $response) use ($delegate) {
            return $delegate->process($request);
        });
    }
}
