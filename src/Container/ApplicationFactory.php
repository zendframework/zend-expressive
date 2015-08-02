<?php
namespace Zend\Expressive\Container;

use Interop\Container\ContainerInterface;
use Zend\Diactoros\Response\SapiEmitter;
use Zend\Expressive\Application;
use Zend\Expressive\Dispatcher;
use Zend\Expressive\Emitter\EmitterStack;
use Zend\Expressive\Router\Aura as AuraRouter;
use Zend\Expressive\Router\Route;

class ApplicationFactory
{
    public function __invoke(ContainerInterface $services)
    {
        $router = $services->has('Zend\Expressive\Router\RouterInterface')
            ? $services->get('Zend\Expressive\Router\RouterInterface')
            : new AuraRouter();
        $dispatcher = new Dispatcher($router, $services);

        $finalHandler = $services->has('Zend\Expressive\FinalHandler')
            ? $services->get('Zend\Expressive\FinalHandler')
            : null;

        $emitter = $services->has('Zend\Diactoros\Response\EmitterInterface')
            ? $services->get('Zend\Diactoros\Response\EmitterInterface')
            : $this->createEmitterStack();

        $app = new Application($dispatcher, $finalHandler, $emitter);

        $this->injectRoutes($app, $services);

        return $app;
    }

    /**
     * Inject routes from configuration, if any.
     *
     * @param Application $app
     * @param ContainerInterface $services
     */
    private function injectRoutes(Application $app, ContainerInterface $services)
    {
        $config = $services->has('Config') ? $services->get('Config') : [];
        if (! isset($config['routes'])) {
            return;
        }

        foreach ($config['routes'] as $spec) {
            if (! isset($spec['path']) || ! isset($spec['middleware'])) {
                continue;
            }

            $methods = (isset($spec['allowed_methods']) && is_array($spec['allowed_methods']))
                ? $spec['allowed_methods']
                : Route::HTTP_METHOD_ANY;
            $route = $app->route($spec['path'], $spec['middleware'], $methods);

            if (isset($spec['options']) && is_array($spec['options'])) {
                $route->setOptions($spec['options']);
            }
        }
    }

    /**
     * Create the default emitter stack.
     *
     * @return EmitterStack
     */
    private function createEmitterStack()
    {
        $emitter = new EmitterStack();
        $emitter->push(new SapiEmitter());
        return $emitter;
    }
}
