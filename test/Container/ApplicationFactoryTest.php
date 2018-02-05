<?php
/**
 * @see       https://github.com/zendframework/zend-expressive for the canonical source repository
 * @copyright Copyright (c) 2018 Zend Technologies USA Inc. (https://www.zend.com)
 * @license   https://github.com/zendframework/zend-expressive/blob/master/LICENSE.md New BSD License
 */

declare(strict_types=1);

namespace ZendTest\Expressive\Container;

use PHPUnit\Framework\TestCase;
use Psr\Container\ContainerInterface;
use Zend\Expressive\Application;
use Zend\Expressive\ApplicationPipeline;
use Zend\Expressive\Container\ApplicationFactory;
use Zend\Expressive\MiddlewareFactory;
use Zend\Expressive\Router\PathBasedRoutingMiddleware;
use Zend\HttpHandlerRunner\RequestHandlerRunner;
use Zend\Stratigility\MiddlewarePipeInterface;

class ApplicationFactoryTest extends TestCase
{
    public function testFactoryProducesAnApplication()
    {
        $middlewareFactory = $this->prophesize(MiddlewareFactory::class)->reveal();
        $pipeline = $this->prophesize(MiddlewarePipeInterface::class)->reveal();
        $routeMiddleware = $this->prophesize(PathBasedRoutingMiddleware::class)->reveal();
        $runner = $this->prophesize(RequestHandlerRunner::class)->reveal();

        $container = $this->prophesize(ContainerInterface::class);
        $container->get(MiddlewareFactory::class)->willReturn($middlewareFactory);
        $container->get(ApplicationPipeline::class)->willReturn($pipeline);
        $container->get(PathBasedRoutingMiddleware::class)->willReturn($routeMiddleware);
        $container->get(RequestHandlerRunner::class)->willReturn($runner);

        $factory = new ApplicationFactory();

        $application = $factory($container->reveal());

        $this->assertInstanceOf(Application::class, $application);
        $this->assertAttributeSame($middlewareFactory, 'factory', $application);
        $this->assertAttributeSame($pipeline, 'pipeline', $application);
        $this->assertAttributeSame($routeMiddleware, 'routes', $application);
        $this->assertAttributeSame($runner, 'runner', $application);
    }
}
