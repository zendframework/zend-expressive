<?php
/**
 * @see       https://github.com/zendframework/zend-expressive for the canonical source repository
 * @copyright Copyright (c) 2016-2017 Zend Technologies USA Inc. (https://www.zend.com)
 * @license   https://github.com/zendframework/zend-expressive/blob/master/LICENSE.md New BSD License
 */

declare(strict_types=1);

namespace Zend\Expressive\Container;

use Psr\Container\ContainerInterface;
use Zend\Expressive\Middleware\WhoopsErrorResponseGenerator;

class WhoopsErrorResponseGeneratorFactory
{
    public function __invoke(ContainerInterface $container) : WhoopsErrorResponseGenerator
    {
        return new WhoopsErrorResponseGenerator(
            $container->get('Zend\Expressive\Whoops')
        );
    }
}
