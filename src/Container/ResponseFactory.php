<?php
/**
 * @see       https://github.com/zendframework/zend-expressive for the canonical source repository
 * @copyright Copyright (c) 2018 Zend Technologies USA Inc. (https://www.zend.com)
 * @license   https://github.com/zendframework/zend-expressive/blob/master/LICENSE.md New BSD License
 */

declare(strict_types=1);

namespace Zend\Expressive\Container;

use Psr\Container\ContainerInterface;
use Psr\Http\Message\ResponseInterface;
use Zend\Diactoros\Response;

/**
 * Produces a response prototype for use with services that need to
 * produce a response. This service should be non-shared, if your
 * container supports that possibility, to ensure that the stream
 * it composes cannot be written to by any other consumer.
 */
class ResponseFactory
{
    public function __invoke(ContainerInterface $container) : ResponseInterface
    {
        if (! class_exists(Response::class)) {
            throw new Exception\InvalidServiceException(sprintf(
                'The %s service must map to a factory capable of returning an'
                . ' implementation instance. By default, we assume usage of'
                . ' zend-diactoros for PSR-7, but it does not appear to be'
                . ' present on your system. Please install zendframework/zend-diactoros'
                . ' or provide an alternate factory for the %s service that'
                . ' can produce an appropriate %s instance.',
                ResponseInterface::class,
                ResponseInterface::class,
                ResponseInterface::class
            ));
        }

        return new Response();
    }
}
