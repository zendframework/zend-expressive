<?php
/**
 * @see       https://github.com/zendframework/zend-expressive for the canonical source repository
 * @copyright Copyright (c) 2018 Zend Technologies USA Inc. (https://www.zend.com)
 * @license   https://github.com/zendframework/zend-expressive/blob/master/LICENSE.md New BSD License
 */

declare(strict_types=1);

namespace Zend\Expressive\Container;

use Psr\Container\ContainerInterface;
use Zend\Diactoros\Response\EmitterInterface;
use Zend\Expressive\Application;
use Zend\Expressive\ApplicationRunner;
use Zend\Expressive\ServerRequestFactory;
use Zend\Expressive\ServerRequestErrorResponseGenerator;

class ApplicationRunnerFactory
{
    public function __invoke(ContainerInterface $container) : ApplicationRunner
    {
        return new ApplicationRunner(
            $container->get(Application::class),
            $container->get(EmitterInterface::class),
            $container->get(ServerRequestFactory::class),
            $container->get(ServerRequestErrorResponseGenerator::class)
        );
    }
}
