<?php
/**
 * @see       https://github.com/zendframework/zend-expressive for the canonical source repository
 * @copyright Copyright (c) 2015-2016 Zend Technologies USA Inc. (http://www.zend.com)
 * @license   https://github.com/zendframework/zend-expressive/blob/master/LICENSE.md New BSD License
 */

namespace Zend\Expressive\Container;

use Interop\Container\ContainerInterface;
use Zend\Diactoros\Response\EmitterInterface;
use Zend\Expressive\Application;
use Zend\Expressive\ApplicationUtils;
use Zend\Expressive\Exception;
use Zend\Expressive\Router\FastRouteRouter;
use Zend\Expressive\Router\RouterInterface;
use Zend\Stratigility\FinalHandler;

/**
 * Factory to use with an IoC container in order to return an Application instance.
 *
 * This factory uses the following services, if available:
 *
 * - 'Zend\Expressive\Router\RouterInterface'. If missing, a FastRoute router
 *   bridge will be instantiated and used.
 * - 'Zend\Expressive\FinalHandler'. The service should be a callable to use as
 *   the final handler when the middleware pipeline is exhausted.
 * - 'Zend\Diactoros\Response\EmitterInterface'. If missing, an EmitterStack is
 *   created, adding a SapiEmitter to the bottom of the stack.
 * - 'config' (an array or ArrayAccess object). If present, and it contains route
 *   definitions, these will be used to seed routes in the Application instance
 *   before returning it.
 *
 * Please see the `Zend\Expressive\ApplicationUtils` class for details on how
 * to provide routing and middleware pipeline configuration; this factory
 * delegates to the methods in that static class in order to seed the
 * Application instance.
 *
 * You may disable injection of configured routing and the middleware pipeline
 * by enabling the `zend-expressive.programmatic_pipeline` configuration flag.
 */
class ApplicationFactory
{
    const DISPATCH_MIDDLEWARE = 'EXPRESSIVE_DISPATCH_MIDDLEWARE';
    const ROUTING_MIDDLEWARE = 'EXPRESSIVE_ROUTING_MIDDLEWARE';

    /**
     * @deprecated This constant will be removed in v1.1.
     */
    const ROUTE_RESULT_OBSERVER_MIDDLEWARE = 'EXPRESSIVE_ROUTE_RESULT_OBSERVER_MIDDLEWARE';

    /**
     * Create and return an Application instance.
     *
     * See the class level docblock for information on what services this
     * factory will optionally consume.
     *
     * @param ContainerInterface $container
     * @return Application
     */
    public function __invoke(ContainerInterface $container)
    {
        $router = $container->has(RouterInterface::class)
            ? $container->get(RouterInterface::class)
            : new FastRouteRouter();

        $finalHandler = $container->has('Zend\Expressive\FinalHandler')
            ? $container->get('Zend\Expressive\FinalHandler')
            : $this->marshalFinalHandler($container);

        $emitter = $container->has(EmitterInterface::class)
            ? $container->get(EmitterInterface::class)
            : null;

        $app = new Application($router, $container, $finalHandler, $emitter);

        $config = $container->has('config') ? $container->get('config') : [];

        if (! isset($config['zend-expressive']['programmatic_pipeline'])
            || ! $config['zend-expressive']['programmatic_pipeline']
        ) {
            $this->injectRoutesAndPipeline($app, $config);
        }

        return $app;
    }

    /**
     * Injects routes and the middleware pipeline into the application.
     *
     * @param Application $app
     * @param array $config
     */
    private function injectRoutesAndPipeline(Application $app, array $config)
    {
        ApplicationUtils::injectRoutesFromConfig($app, $config);
        ApplicationUtils::injectPipelineFromConfig($app, $config);
    }

    /**
     * Create default FinalHandler with options configured under the key final_handler.options.
     *
     * @param ContainerInterface $container
     *
     * @return FinalHandler
     */
    private function marshalFinalHandler(ContainerInterface $container)
    {
        $config = $container->has('config') ? $container->get('config') : [];
        $options = isset($config['final_handler']['options']) ? $config['final_handler']['options'] : [];
        return new FinalHandler($options, null);
    }
}
