<?php
/**
 * @see       https://github.com/zendframework/zend-expressive for the canonical source repository
 * @copyright Copyright (c) 2015-2018 Zend Technologies USA Inc. (https://www.zend.com)
 * @license   https://github.com/zendframework/zend-expressive/blob/master/LICENSE.md New BSD License
 */

declare(strict_types=1);

namespace Zend\Expressive\Container;

use Psr\Container\ContainerInterface;
use Zend\Expressive\Application;
use Zend\Expressive\ApplicationPipeline;
use Zend\Expressive\MiddlewareFactory;
use Zend\Expressive\Router\RouteCollector;
use Zend\HttpHandlerRunner\RequestHandlerRunner;

/**
 * Create an Application instance.
 *
 * This class consumes three other services, and one pseudo-service (service
 * that looks like a class name, but resolves to a different resource):
 *
 * - Zend\Expressive\MiddlewareFactory.
 * - Zend\Expressive\ApplicationPipeline, which should resolve to a
 *   Zend\Stratigility\MiddlewarePipeInterface instance.
 * - Zend\Expressive\Router\RouteCollector.
 * - Zend\HttpHandler\RequestHandlerRunner.
 */
class ApplicationFactory
{
    public function __invoke(ContainerInterface $container) : Application
    {
        return new Application(
            $container->get(MiddlewareFactory::class),
            $container->get(ApplicationPipeline::class),
            $container->get(RouteCollector::class),
            $container->get(RequestHandlerRunner::class)
        );
    }
}
