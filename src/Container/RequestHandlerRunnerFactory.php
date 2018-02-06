<?php
/**
 * @see       https://github.com/zendframework/zend-expressive for the canonical source repository
 * @copyright Copyright (c) 2018 Zend Technologies USA Inc. (https://www.zend.com)
 * @license   https://github.com/zendframework/zend-expressive/blob/master/LICENSE.md New BSD License
 */

declare(strict_types=1);

namespace Zend\Expressive\Container;

use Psr\Container\ContainerInterface;
use Zend\Expressive\ApplicationPipeline;
use Zend\Expressive\ServerRequestErrorResponseGenerator;
use Zend\Expressive\ServerRequestFactory;
use Zend\HttpHandlerRunner\Emitter\EmitterInterface;
use Zend\HttpHandlerRunner\RequestHandlerRunner;

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
 * It also consumes the service Zend\HttpHandlerRunner\Emitter\EmitterInterface.
 */
class RequestHandlerRunnerFactory
{
    public function __invoke(ContainerInterface $container) : RequestHandlerRunner
    {
        return new RequestHandlerRunner(
            $container->get(ApplicationPipeline::class),
            $container->get(EmitterInterface::class),
            $container->get(ServerRequestFactory::class),
            $container->get(ServerRequestErrorResponseGenerator::class)
        );
    }
}
