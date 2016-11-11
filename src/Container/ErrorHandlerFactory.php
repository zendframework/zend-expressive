<?php
/**
 * @see       https://github.com/zendframework/zend-expressive for the canonical source repository
 * @copyright Copyright (c) 2015-2016 Zend Technologies USA Inc. (http://www.zend.com)
 * @license   https://github.com/zendframework/zend-expressive/blob/master/LICENSE.md New BSD License
 */

namespace Zend\Expressive\Container;

use Interop\Container\ContainerInterface;
use Zend\Diactoros\Response;
use Zend\Expressive\TemplatedErrorResponseGenerator;
use Zend\Stratigility\Middleware\ErrorHandler;

class ErrorHandlerFactory
{
    public function __invoke(ContainerInterface $container)
    {
        return new ErrorHandler(
            new Response(),
            $container->get(TemplatedErrorResponseGenerator::class)
        );
    }
}
