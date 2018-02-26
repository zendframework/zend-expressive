<?php
/**
 * @see       https://github.com/zendframework/zend-expressive for the canonical source repository
 * @copyright Copyright (c) 2018 Zend Technologies USA Inc. (https://www.zend.com)
 * @license   https://github.com/zendframework/zend-expressive/blob/master/LICENSE.md New BSD License
 */

declare(strict_types=1);

namespace Zend\Expressive;

use Psr\Container\ContainerInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Zend\Stratigility\Middleware\RequestHandlerMiddleware;

class MiddlewareContainer implements ContainerInterface
{
    /**
     * @var ContainerInterface
     */
    private $container;

    public function __construct(ContainerInterface $container)
    {
        $this->container = $container;
    }

    /**
     * Returns true if the service is in the container.
     * {@inheritDocs}
     */
    public function has($service) : bool
    {
        return $this->container->has($service);
    }

    /**
     * Returns middleware pulled from container.
     * {@inheritDocs}
     * @throws Exception\MissingDependencyException if the service does not
     *     exist, or is not a valid class name.
     */
    public function get($service) : MiddlewareInterface
    {
        if (! $this->has($service)) {
            throw Exception\MissingDependencyException::forMiddlewareService($service);
        }

        $middleware = $this->container->get($service);

        if ($middleware instanceof RequestHandlerInterface) {
            return new RequestHandlerMiddleware($middleware);
        }

        if (! $middleware instanceof MiddlewareInterface) {
            throw Exception\InvalidMiddlewareException::forMiddlewareService($service, $middleware);
        }

        return $middleware;
    }
}
