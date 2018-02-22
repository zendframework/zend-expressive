<?php
/**
 * @see       https://github.com/zendframework/zend-expressive for the canonical source repository
 * @copyright Copyright (c) 2018 Zend Technologies USA Inc. (https://www.zend.com)
 * @license   https://github.com/zendframework/zend-expressive/blob/master/LICENSE.md New BSD License
 */

declare(strict_types=1);

namespace Zend\Expressive\Container;

use Psr\Container\ContainerInterface;
use Psr\Http\Message\ServerRequestInterface;
use Zend\Expressive\ApplicationPipeline;
use Zend\Expressive\Response\ServerRequestErrorResponseGenerator;
use Zend\HttpHandlerRunner\Emitter\EmitterInterface;
use Zend\HttpHandlerRunner\RequestHandlerRunner;

/**
 * Create an ApplicationRunner instance.
 *
 * This class consumes two pseudo-services (services that look like class
 * names, but resolve to other artifacts) and two services provided within
 * this package:
 *
 * - Zend\Expressive\ApplicationPipeline, which should resolve to a
 *   Zend\Stratigility\MiddlewarePipeInterface and/or
 *   Psr\Http\Server\RequestHandlerInterface instance.
 * - Zend\HttpHandlerRunner\Emitter\EmitterInterface.
 * - Psr\Http\Message\ServerRequestInterface, which should resolve to a PHP
 *   callable that will return a Psr\Http\Message\ServerRequestInterface
 *   instance.
 * - Zend\Expressive\Response\ServerRequestErrorResponseGeneratorFactory,
 *
 */
class RequestHandlerRunnerFactory
{
    public function __invoke(ContainerInterface $container) : RequestHandlerRunner
    {
        return new RequestHandlerRunner(
            $container->get(ApplicationPipeline::class),
            $container->get(EmitterInterface::class),
            $container->get(ServerRequestInterface::class),
            $container->get(ServerRequestErrorResponseGenerator::class)
        );
    }
}
