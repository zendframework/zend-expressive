<?php
/**
 * @see       https://github.com/zendframework/zend-expressive for the canonical source repository
 * @copyright Copyright (c) 2018 Zend Technologies USA Inc. (https://www.zend.com)
 * @license   https://github.com/zendframework/zend-expressive/blob/master/LICENSE.md New BSD License
 */

declare(strict_types=1);

namespace Zend\Expressive\Container;

use Psr\Container\ContainerInterface;
use Zend\Diactoros\ServerRequestFactory;

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
        return function () {
            return ServerRequestFactory::fromGlobals();
        };
    }
}
