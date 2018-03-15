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
use Zend\Expressive\Response\ServerRequestErrorResponseGenerator;
use Zend\Expressive\Template\TemplateRendererInterface;

class ServerRequestErrorResponseGeneratorFactory
{
    public function __invoke(ContainerInterface $container) : ServerRequestErrorResponseGenerator
    {
        $config = $container->has('config') ? $container->get('config') : [];
        $debug  = $config['debug'] ?? false;

        $renderer = $container->has(TemplateRendererInterface::class)
            ? $container->get(TemplateRendererInterface::class)
            : null;

        $template = $config['zend-expressive']['error_handler']['template_error']
            ?? ServerRequestErrorResponseGenerator::TEMPLATE_DEFAULT;

        return new ServerRequestErrorResponseGenerator(
            $container->get(ResponseInterface::class),
            $debug,
            $renderer,
            $template
        );
    }
}
