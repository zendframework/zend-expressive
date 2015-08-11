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
use Whoops\Handler\PrettyPageHandler;

/**
 * Create and return an instance of the whoops PrettyPageHandler.
 *
 * Register this factory as the service `Zend\Expressive\WhoopsPageHandler` in
 * the container of your choice.
 */
class WhoopsPageHandlerFactory
{
    /**
     * @param ContainerInterface $container
     * @returns PrettyPageHandler
     */
    public function __invoke(ContainerInterface $container)
    {
        error_log(sprintf("In %s", __CLASS__));
        return new PrettyPageHandler();
    }
}
