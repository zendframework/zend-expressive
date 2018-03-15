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
use Zend\Diactoros\ServerRequestFactory;

use function class_exists;
use function sprintf;

/**
 * Return a factory for generating a server request.
 *
 * We cannot return just `ServerRequestFactory::fromGlobals` or
 * `[ServerRequestFactory::class, 'fromGlobals']` as not all containers
 * allow vanilla PHP callable services. Instead, we wrap it in an
 * anonymous function here, which is allowed by all containers tested
 * at this time.
 */
class ServerRequestFactoryFactory
{
    public function __invoke(ContainerInterface $container) : callable
    {
        if (! class_exists(ServerRequestFactory::class)) {
            throw new Exception\InvalidServiceException(sprintf(
                'The %1$s service must map to a factory capable of returning an'
                . ' implementation instance. By default, we assume usage of'
                . ' zend-diactoros for PSR-7, but it does not appear to be'
                . ' present on your system. Please install zendframework/zend-diactoros'
                . ' or provide an alternate factory for the %1$s service that'
                . ' can produce an appropriate %1$s instance.',
                ServerRequestInterface::class
            ));
        }

        return function () {
            return ServerRequestFactory::fromGlobals();
        };
    }
}
