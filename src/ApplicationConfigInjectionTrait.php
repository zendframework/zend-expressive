<?php
/**
 * @see       https://github.com/zendframework/zend-expressive for the canonical source repository
 * @copyright Copyright (c) 2016-2017 Zend Technologies USA Inc. (http://www.zend.com)
 * @license   https://github.com/zendframework/zend-expressive/blob/master/LICENSE.md New BSD License
 */

namespace Zend\Expressive;

use SplPriorityQueue;
use Zend\Expressive\Container\ApplicationConfigInjectionDelegator;

trait ApplicationConfigInjectionTrait
{
    /**
     * Inject a middleware pipeline from the middleware_pipeline configuration.
     *
     * Proxies to ApplicationConfigInjectionDelegator::injectPipelineFromConfig
     *
     * @param null|array $config If null, attempts to pull the 'config' service
     *     from the composed container.
     * @return void
     */
    public function injectPipelineFromConfig(array $config = null)
    {
        if (! is_array($config)
            && (! $this->container || ! $this->container->has('config'))
        ) {
            return;
        }

        ApplicationConfigInjectionDelegator::injectPipelineFromConfig(
            $this,
            is_array($config) ? $config : $this->container->get('config')
        );
    }

    /**
     * Inject routes from configuration.
     *
     * Proxies to ApplicationConfigInjectionDelegator::injectRoutesFromConfig
     *
     * @param null|array $config If null, attempts to pull the 'config' service
     *     from the composed container.
     * @return void
     * @throws Exception\InvalidArgumentException
     */
    public function injectRoutesFromConfig(array $config = null)
    {
        if (! is_array($config)
            && (! $this->container || ! $this->container->has('config'))
        ) {
            return;
        }

        ApplicationConfigInjectionDelegator::injectRoutesFromConfig(
            $this,
            is_array($config) ? $config : $this->container->get('config')
        );
    }
}
