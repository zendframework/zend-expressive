<?php
/**
 * @see       https://github.com/zendframework/zend-expressive for the canonical source repository
 * @copyright Copyright (c) 2015-2016 Zend Technologies USA Inc. (http://www.zend.com)
 * @license   https://github.com/zendframework/zend-expressive/blob/master/LICENSE.md New BSD License
 */

namespace Zend\Expressive\Container;

use Interop\Container\ContainerInterface;
use Zend\Expressive\Template\TemplateRendererInterface;
use Zend\Expressive\WhoopsErrorHandler;
use Whoops\Handler\JsonResponseHandler;
use Whoops\Run as Whoops;

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
 *
 * The whoops configuration can contain:
 *
 * <code>
 * 'whoops' => [
 *     'json_exceptions' => [
 *         'display'    => true,
 *         'show_trace' => true,
 *         'ajax_only'  => true,
 *     ]
 * ]
 * </code>
 *
 * All values are booleans; omission of any implies boolean false.
 */
class WhoopsErrorHandlerFactory
{
    public function __invoke(ContainerInterface $container)
    {
        $template = $container->has(TemplateRendererInterface::class)
            ? $container->get(TemplateRendererInterface::class)
            : null;

        $config = $container->has('config')
            ? $container->get('config')
            : [];

        $expressiveConfig = isset($config['zend-expressive']['error_handler'])
            ? $config['zend-expressive']['error_handler']
            : [];

        $whoopsConfig = isset($config['whoops'])
            ? $config['whoops']
            : [];

        $whoops = $container->get('Zend\Expressive\Whoops');
        $whoops->pushHandler($container->get('Zend\Expressive\WhoopsPageHandler'));
        $this->registerJsonHandler($whoops, $whoopsConfig);

        return new WhoopsErrorHandler(
            $whoops,
            null,
            $template,
            (isset($expressiveConfig['template_404']) ? $expressiveConfig['template_404'] : 'error/404'),
            (isset($expressiveConfig['template_error']) ? $expressiveConfig['template_error'] : 'error/error')
        );
    }

    /**
     * If configuration indicates a JsonResponseHandler, configure and register it.
     *
     * @param Whoops $whoops
     * @param array|\ArrayAccess $config
     */
    private function registerJsonHandler(Whoops $whoops, $config)
    {
        if (! isset($config['json_exceptions']['display'])
            || empty($config['json_exceptions']['display'])
        ) {
            return;
        }

        $handler = new JsonResponseHandler();

        if (isset($config['json_exceptions']['ajax_only'])) {
            if (method_exists(\Whoops\Util\Misc::class, 'isAjaxRequest')) {
                // Whoops 2.x
                if (! \Whoops\Util\Misc::isAjaxRequest()) {
                    return;
                }
            } elseif (method_exists($handler, 'onlyForAjaxRequests')) {
                // Whoops 1.x
                $handler->onlyForAjaxRequests(true);
            }
        }

        if (isset($config['json_exceptions']['show_trace'])) {
            $handler->addTraceToOutput(true);
        }

        $whoops->pushHandler($handler);
    }
}
