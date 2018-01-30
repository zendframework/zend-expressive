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
use Throwable;
use Zend\Diactoros\Response;
use Zend\Diactoros\ServerRequest;
use Zend\Expressive\Middleware\ErrorResponseGenerator;

class ServerFactoryErrorResponseGeneratorFactory
{
    public function __invoke(ContainerInterface $container) : callable
    {
        return function (Throwable $e) use ($container) : ResponseInterface {
            $generator = $container->get(ErrorResponseGenerator::class);
            return $generator($e, new ServerRequest(), new Response());
        };
    }
}
