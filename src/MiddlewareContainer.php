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

use function class_exists;

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
     * Returns true if the service is in the container, or resolves to an
     * autoloadable class name.
     *
     * @param string $service
     */
    public function has($service) : bool
    {
        if ($this->container->has($service)) {
            return true;
        }

        return class_exists($service);
    }

    /**
     * Returns middleware pulled from container, or directly instantiated if
     * not managed by the container.
     *
     * @param string $service
     * @throws Exception\MissingDependencyException if the service does not
     *     exist, or is not a valid class name.
     * @throws Exception\InvalidMiddlewareException if the service is not
     *     an instance of MiddlewareInterface.
     */
    public function get($service) : MiddlewareInterface
    {
        if (! $this->has($service)) {
            throw Exception\MissingDependencyException::forMiddlewareService($service);
        }

        $middleware = $this->container->has($service)
            ? $this->container->get($service)
            : new $service();

        if ($middleware instanceof RequestHandlerInterface
            && ! $middleware instanceof MiddlewareInterface
        ) {
            $middleware = new RequestHandlerMiddleware($middleware);
        }

        if (! $middleware instanceof MiddlewareInterface) {
            throw Exception\InvalidMiddlewareException::forMiddlewareService($service, $middleware);
        }

        return $middleware;
    }
}
