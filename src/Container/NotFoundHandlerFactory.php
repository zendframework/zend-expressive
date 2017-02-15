<?php
/**
 * @see       https://github.com/zendframework/zend-expressive for the canonical source repository
 * @copyright Copyright (c) 2016 Zend Technologies USA Inc. (http://www.zend.com)
 * @license   https://github.com/zendframework/zend-expressive/blob/master/LICENSE.md New BSD License
 */

namespace Zend\Expressive\Container;

use Psr\Container\ContainerInterface;
use Zend\Expressive\Delegate\NotFoundDelegate;
use Zend\Expressive\Middleware\NotFoundHandler;

class NotFoundHandlerFactory
{
    /**
     * @param ContainerInterface $container
     * @return NotFoundHandler
     */
    public function __invoke(ContainerInterface $container)
    {
        return new NotFoundHandler($container->get(NotFoundDelegate::class));
    }
}
