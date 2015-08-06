<?php
/**
 * Zend Framework (http://framework.zend.com/)
 *
 * @see       https://github.com/zendframework/zend-diactoros for the canonical source repository
 * @copyright Copyright (c) 2015 Zend Technologies USA Inc. (http://www.zend.com)
 * @license   https://github.com/zendframework/zend-diactoros/blob/master/LICENSE.md New BSD License
 */

namespace Zend\Expressive;

use Interop\Container\ContainerInterface;
use Zend\Diactoros\Response\SapiEmitter;
use Zend\ServiceManager\ServiceManager;

/**
 * Create and return an Application instance.
 *
 * This factory acts as the general entry point for using Application in a
 * programmatic vs service-driven environment.
 *
 * The Application instance returned is guaranteed to have a router, a
 * container, and an emitter stack; by default, the Aura router and the ZF2
 * ServiceManager are used.
 */
final class AppFactory
{
    /**
     * Create and return an Application instance.
     *
     * Will inject the instance with the container and/or router when provided;
     * otherwise, it will use a ZF2 ServiceManager instance and the Aura router
     * bridge.
     *
     * The factory also injects the Application with an Emitter\EmitterStack that
     * composes a SapiEmitter at the bottom of the stack (i.e., will execute last
     * when the stack is iterated).
     *
     * @param null|ContainerInterface $container IoC container from which to
     *     fetch middleware defined as services; defaults to a ServiceManager
     *     instance
     * @param null|Router\RouterInterface $router Router implementation to use;
     *     defaults to the Aura router bridge.
     * @return Application
     */
    public static function create(
        ContainerInterface $container = null,
        Router\RouterInterface $router = null
    ) {
        $container = $container ?: new ServiceManager();
        $router    = $router    ?: new Router\Aura();
        $emitter   = new Emitter\EmitterStack();
        $emitter->push(new SapiEmitter());

        return new Application($router, $container, null, $emitter);
    }

    /**
     * Do not allow instantiation.
     */
    private function __construct()
    {
    }
}
