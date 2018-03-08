<?php
/**
 * @see       https://github.com/zendframework/zend-expressive for the canonical source repository
 * @copyright Copyright (c) 2017 Zend Technologies USA Inc. (http://www.zend.com)
 * @license   https://github.com/zendframework/zend-expressive/blob/master/LICENSE.md New BSD License
 */

namespace Zend\Expressive\Container;

use Psr\Container\ContainerInterface;
use Zend\Diactoros\Response;
use Zend\Expressive\Delegate\NotFoundDelegate;
use Zend\Expressive\Template\TemplateRendererInterface;

/**
 * @deprecated since 2.2.0; to be removed in 3.0.0. Use NotFoundHandlerFactory
 *     in version 3, as it will return its replacement, the
 *     Zend\Expressive\Handler\NotFoundHandler.
 */
class NotFoundDelegateFactory
{
    /**
     * @param ContainerInterface $container
     * @return NotFoundDelegate
     */
    public function __invoke(ContainerInterface $container)
    {
        $config   = $container->has('config') ? $container->get('config') : [];
        $renderer = $container->has(TemplateRendererInterface::class)
            ? $container->get(TemplateRendererInterface::class)
            : null;
        $template = isset($config['zend-expressive']['error_handler']['template_404'])
            ? $config['zend-expressive']['error_handler']['template_404']
            : NotFoundDelegate::TEMPLATE_DEFAULT;
        $layout = isset($config['zend-expressive']['error_handler']['layout'])
            ? $config['zend-expressive']['error_handler']['layout']
            : NotFoundDelegate::LAYOUT_DEFAULT;

        return new NotFoundDelegate(new Response(), $renderer, $template, $layout);
    }
}
