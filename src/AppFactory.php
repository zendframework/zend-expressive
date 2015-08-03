<?php
namespace Zend\Expressive;

use Interop\Container\ContainerInterface;
use Zend\Diactoros\Response\SapiEmitter;
use Zend\ServiceManager\ServiceManager;

final class AppFactory
{
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
