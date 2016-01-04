<?php
/**
 * Zend Framework (http://framework.zend.com/)
 *
 * @see       https://github.com/zendframework/zend-expressive for the canonical source repository
 * @copyright Copyright (c) 2015-2016 Zend Technologies USA Inc. (http://www.zend.com)
 * @license   https://github.com/zendframework/zend-expressive/blob/master/LICENSE.md New BSD License
 */

namespace Zend\Expressive\Container;

use Interop\Container\ContainerInterface;
use Zend\Expressive\WhoopsErrorHandler;

/**
 * Create and return an instance of the whoops error handler.
 *
 * Register this factory as the service `Zend\Expressive\FinalHandler` in
 * the container of your choice.
 *
 * This factory has optional dependencies on the following services:
 *
 * - 'Zend\Expressive\Template\TemplateRendererInterface', which should return an
 *   implementation of that interface. If not present, the error handler
 *   will not create templated responses.
 * - 'config' (which should return an array or array-like object with a
 *   "zend-expressive" top-level key, and an "error_handler" subkey,
 *   containing the configuration for the error handler).
 *
 * This factory has required dependencies on the following services:
 *
 * - Zend\Expressive\Whoops, which should return a Whoops\Run instance.
 * - Zend\Expressive\WhoopsPageHandler, which should return a
 *   Whoops\Handler\PrettyPageHandler instance.
 *
 * Configuration should look like the following:
 *
 * <code>
 * 'zend-expressive' => [
 *     'error_handler' => [
 *         'template_404'   => 'name of 404 template',
 *         'template_error' => 'name of error template',
 *     ],
 * ]
 * </code>
 *
 * If any of the keys are missing, default values will be used.
 */
class WhoopsErrorHandlerFactory
{
    public function __invoke(ContainerInterface $container)
    {
        $template = $container->has('Zend\Expressive\Template\TemplateRendererInterface')
            ? $container->get('Zend\Expressive\Template\TemplateRendererInterface')
            : null;

        $config = $container->has('config')
            ? $container->get('config')
            : [];

        $config = isset($config['zend-expressive']['error_handler'])
            ? $config['zend-expressive']['error_handler']
            : [];

        return new WhoopsErrorHandler(
            $container->get('Zend\Expressive\Whoops'),
            $container->get('Zend\Expressive\WhoopsPageHandler'),
            $template,
            (isset($config['template_404']) ? $config['template_404'] : 'error/404'),
            (isset($config['template_error']) ? $config['template_error'] : 'error/error')
        );
    }
}
