<?php
/**
 * @see       https://github.com/zendframework/zend-expressive for the canonical source repository
 * @copyright Copyright (c) 2015-2017 Zend Technologies USA Inc. (http://www.zend.com)
 * @license   https://github.com/zendframework/zend-expressive/blob/master/LICENSE.md New BSD License
 */

namespace Zend\Expressive\Container;

use Psr\Container\ContainerInterface;
use Whoops\Handler\JsonResponseHandler;
use Whoops\Run as Whoops;
use Whoops\Util\Misc as WhoopsUtil;

/**
 * Create and return an instance of the Whoops runner.
 *
 * Register this factory as the service `Zend\Expressive\Whoops` in the
 * container of your choice. This service depends on two others:
 *
 * - 'config' (which should return an array or array-like object with a "whoops"
 *   key, containing the configuration for whoops).
 * - 'Zend\Expressive\WhoopsPageHandler', which should return a
 *   Whoops\Handler\PrettyPageHandler instance to register on the whoops
 *   instance.
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
class WhoopsFactory
{
    /**
     * Create and return an instance of the Whoops runner.
     *
     * @param ContainerInterface $container
     * @return Whoops
     */
    public function __invoke(ContainerInterface $container)
    {
        $config = $container->has('config') ? $container->get('config') : [];
        $config = isset($config['whoops']) ? $config['whoops'] : [];

        $whoops = new Whoops();
        $whoops->writeToOutput(false);
        $whoops->allowQuit(false);
        $whoops->pushHandler($container->get('Zend\Expressive\WhoopsPageHandler'));
        $this->registerJsonHandler($whoops, $config);
        $whoops->register();
        return $whoops;
    }

    /**
     * If configuration indicates a JsonResponseHandler, configure and register it.
     *
     * @param Whoops $whoops
     * @param array|\ArrayAccess $config
     * @return void
     */
    private function registerJsonHandler(Whoops $whoops, $config)
    {
        if (empty($config['json_exceptions']['display'])) {
            return;
        }

        $handler = new JsonResponseHandler();

        if (! empty($config['json_exceptions']['show_trace'])) {
            $handler->addTraceToOutput(true);
        }

        if (! empty($config['json_exceptions']['ajax_only'])) {
            if (method_exists(WhoopsUtil::class, 'isAjaxRequest')) {
                // Whoops 2.x; don't push handler on stack unless we are in
                // an XHR request.
                if (! WhoopsUtil::isAjaxRequest()) {
                    return;
                }
            } elseif (method_exists($handler, 'onlyForAjaxRequests')) {
                // Whoops 1.x
                $handler->onlyForAjaxRequests(true);
            }
        }

        $whoops->pushHandler($handler);
    }
}
