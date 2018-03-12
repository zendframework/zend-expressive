<?php
/**
 * @see       https://github.com/zendframework/zend-expressive for the canonical source repository
 * @copyright Copyright (c) 2018 Zend Technologies USA Inc. (https://www.zend.com)
 * @license   https://github.com/zendframework/zend-expressive/blob/master/LICENSE.md New BSD License
 */

declare(strict_types=1);

namespace Zend\Expressive\Container;

use Psr\Container\ContainerInterface;
use Zend\Expressive\MiddlewareContainer;
use Zend\Expressive\MiddlewareFactory;

class MiddlewareFactoryFactory
{
    public function __invoke(ContainerInterface $container) : MiddlewareFactory
    {
        return new MiddlewareFactory(
            $container->get(MiddlewareContainer::class)
        );
    }
}
