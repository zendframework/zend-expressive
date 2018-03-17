<?php
/**
 * @see       https://github.com/zendframework/zend-expressive for the canonical source repository
 * @copyright Copyright (c) 2018 Zend Technologies USA Inc. (http://www.zend.com)
 * @license   https://github.com/zendframework/zend-expressive/blob/master/LICENSE.md New BSD License
 */

namespace Zend\Expressive;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\StreamInterface;
use Zend\Stratigility\Middleware\ErrorHandler;

/**
 * Provide initial configuration for zend-expressive.
 *
 * This class provides initial _production_ configuration for zend-expressive.
 */
class ConfigProvider
{
    /**
     * @return array
     */
    public function __invoke()
    {
        return [
            'dependencies' => $this->getDependencies(),
        ];
    }

    /**
     * @return array
     */
    public function getDependencies()
    {
        // @codingStandardsIgnoreStart
        return [
            'aliases' => [
                Delegate\NotFoundDelegate::class            => Handler\NotFoundHandler::class,
                Middleware\DispatchMiddleware::class        => Router\Middleware\DispatchMiddleware::class,
                Middleware\ImplicitHeadMiddleware::class    => Router\Middleware\ImplicitHeadMiddleware::class,
                Middleware\ImplicitOptionsMiddleware::class => Router\Middleware\ImplicitOptionsMiddleware::class,
                Middleware\RouteMiddleware::class           => Router\Middleware\RouteMiddleware::class,
                'Zend\Expressive\Delegate\DefaultDelegate'  => Handler\NotFoundHandler::class,
            ],
            'factories' => [
                Application::class                       => Container\ApplicationFactory::class,
                ErrorHandler::class                      => Container\ErrorHandlerFactory::class,
                Handler\NotFoundHandler::class           => Container\NotFoundDelegateFactory::class,
                // Change the following in development to the WhoopsErrorResponseGeneratorFactory:
                Middleware\ErrorResponseGenerator::class => Container\ErrorResponseGeneratorFactory::class,
                Middleware\NotFoundHandler::class        => Container\NotFoundHandlerFactory::class,
                ResponseInterface::class                 => Container\ResponseFactoryFactory::class,
                StreamInterface::class                   => Container\StreamFactoryFactory::class,

                // These are duplicates, in case the zend-expressive-router package ConfigProvider is not wired:
                Router\Middleware\DispatchMiddleware::class        => Router\Middleware\DispatchMiddlewareFactory::class,
                Router\Middleware\ImplicitHeadMiddleware::class    => Router\Middleware\ImplicitHeadMiddlewareFactory::class,
                Router\Middleware\ImplicitOptionsMiddleware::class => Router\Middleware\ImplicitOptionsMiddlewareFactory::class,
                Router\Middleware\RouteMiddleware::class           => Router\Middleware\RouteMiddlewareFactory::class,
            ],
        ];
        // @codingStandardsIgnoreEnd
    }
}
