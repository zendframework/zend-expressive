<?php
/**
 * @see       https://github.com/zendframework/zend-expressive for the canonical source repository
 * @copyright Copyright (c) 2016-2018 Zend Technologies USA Inc. (https://www.zend.com)
 * @license   https://github.com/zendframework/zend-expressive/blob/master/LICENSE.md New BSD License
 */

declare(strict_types=1);

namespace Zend\Expressive\Container;

use Psr\Container\ContainerInterface;
use Zend\Expressive\Middleware\NotFoundMiddleware;
use Zend\Expressive\NotFoundResponseInterface;
use Zend\Expressive\Template\TemplateRendererInterface;

class NotFoundMiddlewareFactory
{
    public function __invoke(ContainerInterface $container) : NotFoundMiddleware
    {
        $config   = $container->has('config') ? $container->get('config') : [];
        $renderer = $container->has(TemplateRendererInterface::class)
            ? $container->get(TemplateRendererInterface::class)
            : null;
        $template = $config['zend-expressive']['error_handler']['template_404']
                    ?? NotFoundMiddleware::TEMPLATE_DEFAULT;
        $layout   = $config['zend-expressive']['error_handler']['layout']
                    ?? NotFoundMiddleware::LAYOUT_DEFAULT;

        return new NotFoundMiddleware(
            $container->get(NotFoundResponseInterface::class),
            $renderer,
            $template,
            $layout
        );
    }
}
