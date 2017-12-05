<?php
/**
 * @see       https://github.com/zendframework/zend-expressive for the canonical source repository
 * @copyright Copyright (c) 2016 Zend Technologies USA Inc. (http://www.zend.com)
 * @license   https://github.com/zendframework/zend-expressive/blob/master/LICENSE.md New BSD License
 */
declare(strict_types=1);

namespace Zend\Expressive\Container;

use Psr\Container\ContainerInterface;
use Zend\Diactoros\Response;
use Zend\Expressive\Middleware\ErrorResponseGenerator;
use Zend\Stratigility\Middleware\ErrorHandler;

class ErrorHandlerFactory
{
    public function __invoke(ContainerInterface $container) : ErrorHandler
    {
        $generator = $container->has(ErrorResponseGenerator::class)
            ? $container->get(ErrorResponseGenerator::class)
            : null;

        return new ErrorHandler(new Response(), $generator);
    }
}
