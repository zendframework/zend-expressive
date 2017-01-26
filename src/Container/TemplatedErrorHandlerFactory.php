<?php
/**
 * @see       https://github.com/zendframework/zend-expressive for the canonical source repository
 * @copyright Copyright (c) 2015-2016 Zend Technologies USA Inc. (http://www.zend.com)
 * @license   https://github.com/zendframework/zend-expressive/blob/master/LICENSE.md New BSD License
 */

namespace Zend\Expressive\Container;

use Interop\Container\ContainerInterface;
use Zend\Expressive\Template\TemplateRendererInterface;
use Zend\Expressive\TemplatedErrorHandler;

/**
 * Create and return an instance of the templated error handler.
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
 *
 * @deprecated Since 2.0, to remove in 3.0. Please migrate your code to use
 *     pipeline middleware for error handling, instead of the "final handler".
 *     Use the `Zend\Stratigility\Middleware\ErrorHandler` service (via the
 *     `Zend\Expressive\Container\ErrorHandlerFactory`), and the shipped
 *     error response generator (`Zend\Expressive\Middleware\ErrorResponseGenerator`
 *     via `Zend\Expressive\Container\ErrorResponseGeneratorFactory`), and
 *     pipe the `ErrorHandler` middleware towards the outermost layer of your
 *     application.
 */
class TemplatedErrorHandlerFactory
{
    public function __invoke(ContainerInterface $container)
    {
        $template = $container->has(TemplateRendererInterface::class)
            ? $container->get(TemplateRendererInterface::class)
            : null;

        $config = $container->has('config')
            ? $container->get('config')
            : [];

        $config = isset($config['zend-expressive']['error_handler'])
            ? $config['zend-expressive']['error_handler']
            : [];

        return new TemplatedErrorHandler(
            $template,
            (isset($config['template_404']) ? $config['template_404'] : 'error/404'),
            (isset($config['template_error']) ? $config['template_error'] : 'error/error')
        );
    }
}
