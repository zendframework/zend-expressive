<?php
/**
 * @see       https://github.com/zendframework/zend-expressive for the canonical source repository
 * @copyright Copyright (c) 2018 Zend Technologies USA Inc. (https://www.zend.com)
 * @license   https://github.com/zendframework/zend-expressive/blob/master/LICENSE.md New BSD License
 */

declare(strict_types=1);

namespace Zend\Expressive\Container;

use Psr\Container\ContainerInterface;
use Zend\Diactoros\Response\EmitterInterface;
use Zend\Expressive\Application;
use Zend\Expressive\ApplicationPipeline;
use Zend\Expressive\ApplicationRunner;
use Zend\Expressive\ServerRequestFactory;
use Zend\Expressive\ServerRequestErrorResponseGenerator;

/**
 * Create an ApplicationRunner instance.
 *
 * This class consumes three pseudo-services (services that look like class
 * names, but resolve to other artifacts):
 *
 * - Zend\Expressive\ApplicationPipeline, which should resolve to a
 *   Zend\Stratigility\MiddlewarePipeInterface and/or
 *   Psr\Http\Server\RequestHandlerInterface instance.
 * - Zend\Expressive\ServerRequestFactory, which should resolve to a PHP
 *   callable that will return a Psr\Http\Message\ServerRequestInterface
 *   instance.
 * - Zend\Expressive\ServerRequestErrorResponseGenerator, which should resolve
 *   to a PHP callable that accepts a Throwable argument, and which will return
 *   a Psr\Http\Message\ResponseInterface instance.
 *
 * It also consumes the service Zend\Diactoros\Response\EmitterInterface.
 */
class ApplicationRunnerFactory
{

    public function __invoke(ContainerInterface $container) : ApplicationRunner
    {
        return new ApplicationRunner(
            $container->get(ApplicationPipeline::class),
            $container->get(EmitterInterface::class),
            $container->get(ServerRequestFactory::class),
            $container->get(ServerRequestErrorResponseGenerator::class)
        );
    }
}
