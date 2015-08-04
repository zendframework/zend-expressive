<?php
/**
 * Zend Framework (http://framework.zend.com/)
 *
 * @see       http://github.com/zendframework/zend-diactoros for the canonical source repository
 * @copyright Copyright (c) 2015 Zend Technologies USA Inc. (http://www.zend.com)
 * @license   https://github.com/zendframework/zend-diactoros/blob/master/LICENSE.md New BSD License
 */

namespace Zend\Expressive\Container;

use Interop\Container\ContainerInterface;
use Zend\Diactoros\Response\SapiEmitter;
use Zend\Expressive\Application;
use Zend\Expressive\Emitter\EmitterStack;
use Zend\Expressive\Router\Aura as AuraRouter;
use Zend\Expressive\Router\Route;

/**
 * Factory to use with an IoC container in order to return an Application instance.
 *
 * This factory uses the following services, if available:
 *
 * - Zend\Expressive\Router\RouterInterface. If missing, an Aura router bridge
 *   will be instantiated and used.
 * - Zend\Expressive\FinalHandler. The service should be a callable to use as
 *   the final handler when the middleware pipeline is exhausted.
 * - Zend\Diactoros\Response\EmitterInterface. If missing, an EmitterStack is
 *   created, adding a SapiEmitter to the bottom of the stack.
 * - Config (an array or ArrayAccess object). If present, and it contains route
 *   definitions, these will be used to seed routes in the Application instance
 *   before returning it.
 *
 * When introspecting the `Config` service, the following structure can be used
 * to define routes:
 *
 * <code>
 * return [
 *     'routes' => [
 *         [
 *             'path' => '/path/to/match',
 *             'middleware' => 'Middleware Service Name or Callable',
 *             'allowed_methods' => [ 'GET', 'POST', 'PATCH' ],
 *             'options' => [
 *                 'stuff' => 'to',
 *                 'pass'  => 'to',
 *                 'the'   => 'underlying router',
 *             ],
 *         ],
 *         // etc.
 *     ],
 * ];
 * </code>
 *
 * Each route MUST have a path and middleware key at the minimum.
 *
 * The "allowed_methods" key may be omitted, can be either an array or the
 * value of the Zend\Expressive\Router\Route::HTTP_METHOD_ANY constant; any
 * valid HTTP method token is allowed, which means you can specify custom HTTP
 * methods as well.
 *
 * The "options" key may also be omitted, and its interpretation will be
 * dependent on the underlying router used.
 */
class ApplicationFactory
{
    /**
     * Create and return an Application instance.
     *
     * See the class level docblock for information on what services this
     * factory will optionally consume.
     *
     * @param ContainerInterface $services
     * @return Application
     */
    public function __invoke(ContainerInterface $services)
    {
        $router = $services->has('Zend\Expressive\Router\RouterInterface')
            ? $services->get('Zend\Expressive\Router\RouterInterface')
            : new AuraRouter();

        $finalHandler = $services->has('Zend\Expressive\FinalHandler')
            ? $services->get('Zend\Expressive\FinalHandler')
            : null;

        $emitter = $services->has('Zend\Diactoros\Response\EmitterInterface')
            ? $services->get('Zend\Diactoros\Response\EmitterInterface')
            : $this->createEmitterStack();

        $app = new Application($router, $services, $finalHandler, $emitter);

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
