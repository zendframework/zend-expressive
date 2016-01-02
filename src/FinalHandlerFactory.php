<?php
/**
 * Zend Framework (http://framework.zend.com/)
 *
 * @see       http://github.com/zendframework/zend-expressive for the canonical source repository
 * @copyright Copyright (c) 2015 Zend Technologies USA Inc. (http://www.zend.com)
 * @license   https://github.com/zendframework/zend-expressive/blob/master/LICENSE.md New BSD License
 */

namespace Zend\Expressive;

use Interop\Container\ContainerInterface;
use Zend\Stratigility\FinalHandler;

class FinalHandlerFactory
{
    public function __invoke(ContainerInterface $container)
    {
        $config = $container->has('config') ? $container->get('config') : [];

        $options = isset($config['final_handler']['options']) ? $config['final_handler']['options'] : [];

        return new FinalHandler($options);
    }
}
