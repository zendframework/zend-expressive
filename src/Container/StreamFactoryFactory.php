<?php
/**
 * @see       https://github.com/zendframework/zend-expressive for the canonical source repository
 * @copyright Copyright (c) 2018 Zend Technologies USA Inc. (http://www.zend.com)
 * @license   https://github.com/zendframework/zend-expressive/blob/master/LICENSE.md New BSD License
 */

namespace Zend\Expressive\Container;

use Psr\Container\ContainerInterface;
use Psr\Http\Message\StreamInterface;
use Zend\Diactoros\Stream;

/**
 * Produces a callable capable of producing an empty stream for use with
 * services that need to produce a stream for use with a request or a response.
 */
class StreamFactoryFactory
{
    /**
     * @return callable
     */
    public function __invoke(ContainerInterface $container)
    {
        return function () {
            return new Stream('php://temp', 'wb+');
        };
    }
}
