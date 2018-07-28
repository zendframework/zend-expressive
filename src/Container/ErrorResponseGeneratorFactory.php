<?php
/**
 * @see       https://github.com/zendframework/zend-expressive for the canonical source repository
 * @copyright Copyright (c) 2016-2017 Zend Technologies USA Inc. (https://www.zend.com)
 * @license   https://github.com/zendframework/zend-expressive/blob/master/LICENSE.md New BSD License
 */

declare(strict_types=1);

namespace Zend\Expressive\Container;

use Psr\Container\ContainerInterface;
use Zend\Expressive\Middleware\ErrorResponseGenerator;
use Zend\Expressive\Template\TemplateRendererInterface;

class ErrorResponseGeneratorFactory
{
    public function __invoke(ContainerInterface $container) : ErrorResponseGenerator
    {
        $config = $container->has('config') ? $container->get('config') : [];

        $debug = $config['debug'] ?? false;

        $template = $config['zend-expressive']['error_handler']['template_error']
            ?? ErrorResponseGenerator::TEMPLATE_DEFAULT;

        $layout   = $config['zend-expressive']['error_handler']['layout']
            ?? ErrorResponseGenerator::LAYOUT_DEFAULT;

        $renderer = $container->has(TemplateRendererInterface::class)
            ? $container->get(TemplateRendererInterface::class)
            : null;

        return new ErrorResponseGenerator($debug, $renderer, $template, $layout);
    }
}
