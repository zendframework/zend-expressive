<?php
/**
 * @see       https://github.com/zendframework/zend-expressive for the canonical source repository
 * @copyright Copyright (c) 2015-2017 Zend Technologies USA Inc. (http://www.zend.com)
 * @license   https://github.com/zendframework/zend-expressive/blob/master/LICENSE.md New BSD License
 */

namespace ZendTest\Expressive;

use Prophecy\Argument;
use Prophecy\Prophecy\ObjectProphecy;
use Psr\Container\ContainerInterface;

/**
 * Helper methods for mock Psr\Container\ContainerInterface.
 */
trait ContainerTrait
{
    /**
     * Returns a prophecy for ContainerInterface.
     *
     * By default returns false for unknown `has('service')` method.
     *
     * @return ObjectProphecy
     */
    protected function mockContainerInterface()
    {
        $container = $this->prophesize(ContainerInterface::class);
        $container->has(Argument::type('string'))->willReturn(false);

        return $container;
    }

    /**
     * Inject a service into the container mock.
     *
     * Adjust `has('service')` and `get('service')` returns.
     *
     * @param ObjectProphecy $container
     * @param string $serviceName
     * @param mixed $service
     * @return void
     */
    protected function injectServiceInContainer(ObjectProphecy $container, $serviceName, $service)
    {
        $container->has($serviceName)->willReturn(true);
        $container->get($serviceName)->willReturn($service);
    }
}
