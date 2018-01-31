<?php
/**
 * @see       https://github.com/zendframework/zend-expressive for the canonical source repository
 * @copyright Copyright (c) 2018 Zend Technologies USA Inc. (https://www.zend.com)
 * @license   https://github.com/zendframework/zend-expressive/blob/master/LICENSE.md New BSD License
 */

declare(strict_types=1);

namespace Zend\Expressive;

use Psr\Http\Message\ResponseInterface;
use Zend\Diactoros\Response\EmitterInterface;
use Zend\Stratigility\Middleware\ErrorHandler;
use Zend\Stratigility\Middleware\ErrorResponseGenerator;

/**
 * Provide initial configuration for zend-expressive.
 *
 * This class provides initial _production_ configuration for zend-expressive.
 */
class ConfigProvider
{
    public function __invoke() : array
    {
        return [
            'dependencies' => $this->getDependencies(),
        ];
    }

    public function getDependencies() : array
    {
        // @codingStandardsIgnoreStart
        return [
            'aliases' => [
                Handler\DefaultHandler::class => Handler\NotFoundHandler::class,
            ],
            'factories' => [
                Application::class                         => Container\ApplicationFactory::class,
                ApplicationPipeline::class                 => Container\ApplicationPipelineFactory::class,
                ApplicationRunner::class                   => Container\ApplicationRunnerFactory::class,
                EmitterInterface::class                    => Container\EmitterFactory::class,
                ErrorHandler::class                        => Container\ErrorHandlerFactory::class,
                // Change the following in development to the WhoopsErrorResponseGeneratorFactory:
                ErrorResponseGenerator::class              => Container\ErrorResponseGeneratorFactory::class,
                Handler\NotFoundHandler::class             => Handler\NotFoundHandlerFactory::class,
                MiddlewareContainer::class                 => Container\MiddlewareContainerFactory::class,
                MiddlewareFactory::class                   => Container\MiddlewareFactoryFactory::class,
                Middleware\DispatchMiddleware::class       => Container\DispatchMiddlewareFactory::class,
                Middleware\NotFoundMiddleware::class       => Container\NotFoundMiddlewareFactory::class,
                Middleware\RouteMiddleware::class          => Container\RouteMiddlewareFactory::class,
                ResponseInterface::class                   => Container\ResponseFactory::class,
                ServerRequestErrorResponseGenerator::class => Container\ServerRequestErrorResponseGeneratorFactory::class,
                ServerRequestFactory::class                => Container\ServerRequestFactoryFactory::class,
            ],
            'shared' => [
                // Do not share response instances
                ResponseInterface::class => false,
            ],
        ];
        // @codingStandardsIgnoreEnd
    }
}