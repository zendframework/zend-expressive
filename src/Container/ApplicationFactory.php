<?php
namespace Zend\Expressive\Container;

use Interop\Container\ContainerInterface;
use Zend\Expressive\Application;
use Zend\Expressive\Dispatcher;
use Zend\Expressive\Router\Route;

class ApplicationFactory
{
    public function __invoke(ContainerInterface $services)
    {
        $router     = $services->get('Zend\Expressive\Router\RouterInterface');
        $dispatcher = new Dispatcher($router, $services);
        $app        = new Application(
            $dispatcher,
            $services->get('Zend\Expressive\FinalHandler'),
            $services->get('Zend\Diactoros\Response\EmitterInterface')
        );

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
}
