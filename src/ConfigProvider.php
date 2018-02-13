<?php
/**
 * @see       https://github.com/zendframework/zend-expressive for the canonical source repository
 * @copyright Copyright (c) 2018 Zend Technologies USA Inc. (https://www.zend.com)
 * @license   https://github.com/zendframework/zend-expressive/blob/master/LICENSE.md New BSD License
 */

declare(strict_types=1);

namespace Zend\Expressive;

use Zend\HttpHandlerRunner\Emitter\EmitterInterface;
use Zend\HttpHandlerRunner\RequestHandlerRunner;
use Zend\Stratigility\Middleware\ErrorHandler;

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
                Delegate\DefaultDelegate::class      => Handler\NotFoundHandler::class,
                Middleware\DispatchMiddleware::class => Router\Middleware\DispatchMiddleware::class,
                Middleware\RouteMiddleware::class    => Router\Middleware\PathBasedRoutingMiddleware::class,
            ],
            'factories' => [
                Application::class                         => Container\ApplicationFactory::class,
                ApplicationPipeline::class                 => Container\ApplicationPipelineFactory::class,
                EmitterInterface::class                    => Container\EmitterFactory::class,
                ErrorHandler::class                        => Container\ErrorHandlerFactory::class,
                Handler\NotFoundHandler::class             => Container\NotFoundHandlerFactory::class,
                MiddlewareContainer::class                 => Container\MiddlewareContainerFactory::class,
                MiddlewareFactory::class                   => Container\MiddlewareFactoryFactory::class,
                // Change the following in development to the WhoopsErrorResponseGeneratorFactory:
                Middleware\ErrorResponseGenerator::class   => Container\ErrorResponseGeneratorFactory::class,
                RequestHandlerRunner::class                => Container\RequestHandlerRunnerFactory::class,
                Response\NotFoundResponseInterface::class  => Container\ResponseFactory::class,
                Response\RouterResponseInterface::class    => Container\ResponseFactory::class,
                ServerRequestErrorResponseGenerator::class => Container\ServerRequestErrorResponseGeneratorFactory::class,
                ServerRequestFactory::class                => Container\ServerRequestFactoryFactory::class,
            ],
        ];
        // @codingStandardsIgnoreEnd
    }
}
