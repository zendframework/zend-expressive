<?php
/**
 * Zend Framework (http://framework.zend.com/)
 *
 * @see       https://github.com/zendframework/zend-expressive for the canonical source repository
 * @copyright Copyright (c) 2015 Zend Technologies USA Inc. (http://www.zend.com)
 * @license   https://github.com/zendframework/zend-expressive/blob/master/LICENSE.md New BSD License
 */

namespace Zend\Expressive\Container;

use Interop\Container\ContainerInterface;
use Zend\Expressive\ErrorHandler;

/**
 * Create and return an instance of the error handler.
 *
 * Register this factory as the service `Zend\Expressive\FinalHandler` in
 * the container of your choice.
 *
 * This factory has optional dependencies on the following services:
 *
 * - Zend\Expressive\Template\TemplateInterface, which should return an
 *   implementation of that interface. If not present, the error handler
 *   will not create templated responses.
 * - Config (which should return an array or array-like object with a
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
class ErrorHandlerFactory
{
    public function __invoke(ContainerInterface $container)
    {
        $template = $container->has('Zend\Expressive\Template\TemplateInterface')
            ? $container->get('Zend\Expressive\Template\TemplateInterface')
            : null;

        $config = $container->has('Config')
            ? $container->get('Config')
            : [];

        $config = isset($config['zend-expressive']['error_handler'])
            ? $config['zend-expressive']['error_handler']
            : [];

        return new ErrorHandler(
            $container->get('Zend\Expressive\Whoops'),
            $container->get('Zend\Expressive\WhoopsPageHandler'),
            $template,
            (isset($config['template_404']) ? $config['template_404'] : 'error/404'),
            (isset($config['template_error']) ? $config['template_error'] : 'error/error')
        );
    }
}
