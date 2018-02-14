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
                DEFAULT_DELEGATE     => Handler\NotFoundHandler::class,
                DISPATCH_MIDDLEWARE  => Router\Middleware\DispatchMiddleware::class,
                NOT_FOUND_MIDDLEWARE => Handler\NotFoundHandler::class,
                ROUTE_MIDDLEWARE     => Router\Middleware\PathBasedRoutingMiddleware::class,
            ],
            'factories' => [
                Application::class                             => Container\ApplicationFactory::class,
                ApplicationPipeline::class                     => Container\ApplicationPipelineFactory::class,
                EmitterInterface::class                        => Container\EmitterFactory::class,
                ErrorHandler::class                            => Container\ErrorHandlerFactory::class,
                Handler\NotFoundHandler::class                 => Container\NotFoundHandlerFactory::class,
                MiddlewareContainer::class                     => Container\MiddlewareContainerFactory::class,
                MiddlewareFactory::class                       => Container\MiddlewareFactoryFactory::class,
                // Change the following in development to the WhoopsErrorResponseGeneratorFactory:
                Middleware\ErrorResponseGenerator::class       => Container\ErrorResponseGeneratorFactory::class,
                NOT_FOUND_RESPONSE                             => Container\ResponseFactory::class,
                RequestHandlerRunner::class                    => Container\RequestHandlerRunnerFactory::class,
                Router\IMPLICIT_HEAD_MIDDLEWARE_RESPONSE       => Container\ResponseFactory::class,
                Router\IMPLICIT_HEAD_MIDDLEWARE_STREAM_FACTORY => Container\StreamFactoryFactory::class,
                Router\IMPLICIT_OPTIONS_MIDDLEWARE_RESPONSE    => Container\ResponseFactory::class,
                Router\METHOD_NOT_ALLOWED_MIDDLEWARE_RESPONSE  => Container\ResponseFactory::class,
                SERVER_REQUEST_ERROR_RESPONSE_GENERATOR        => Container\ServerRequestErrorResponseGeneratorFactory::class,
                SERVER_REQUEST_FACTORY                         => Container\ServerRequestFactoryFactory::class,
            ],
        ];
        // @codingStandardsIgnoreEnd
    }
}
