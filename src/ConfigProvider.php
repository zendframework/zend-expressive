<?php
/**
 * @see       https://github.com/zendframework/zend-expressive for the canonical source repository
 * @copyright Copyright (c) 2018 Zend Technologies USA Inc. (https://www.zend.com)
 * @license   https://github.com/zendframework/zend-expressive/blob/master/LICENSE.md New BSD License
 */

declare(strict_types=1);

namespace Zend\Expressive;

use Zend\Expressive\Response\NotFoundResponseInterface;
use Zend\Expressive\Response\RouterResponseInterface;
use Zend\HttpHandlerRunner\Emitter\EmitterInterface;
use Zend\HttpHandlerRunner\RequestHandlerRunner;
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
                Delegate\DefaultDelegate::class      => Middleware\NotFoundMiddleware::class,
                Middleware\DispatchMiddleware::class => Router\DispatchMiddleware::class,
                Middleware\RouteMiddleware::class    => Router\PathBasedRoutingMiddleware::class,
            ],
            'factories' => [
                Application::class                         => Container\ApplicationFactory::class,
                ApplicationPipeline::class                 => Container\ApplicationPipelineFactory::class,
                EmitterInterface::class                    => Container\EmitterFactory::class,
                ErrorHandler::class                        => Container\ErrorHandlerFactory::class,
                // Change the following in development to the WhoopsErrorResponseGeneratorFactory:
                ErrorResponseGenerator::class              => Container\ErrorResponseGeneratorFactory::class,
                Middleware\NotFoundMiddleware::class       => Container\NotFoundMiddlewareFactory::class,
                MiddlewareContainer::class                 => Container\MiddlewareContainerFactory::class,
                MiddlewareFactory::class                   => Container\MiddlewareFactoryFactory::class,
                NotFoundResponseInterface::class           => Container\ResponseFactory::class,
                RequestHandlerRunner::class                => Container\RequestHandlerRunnerFactory::class,
                Router\DispatchMiddleware::class           => Container\DispatchMiddlewareFactory::class,
                Router\PathBasedRoutingMiddleware::class   => Container\RouteMiddlewareFactory::class,
                RouterResponseInterface::class             => Container\ResponseFactory::class,
                ServerRequestErrorResponseGenerator::class => Container\ServerRequestErrorResponseGeneratorFactory::class,
                ServerRequestFactory::class                => Container\ServerRequestFactoryFactory::class,
            ],
        ];
        // @codingStandardsIgnoreEnd
    }
}
