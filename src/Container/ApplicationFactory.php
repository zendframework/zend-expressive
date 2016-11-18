<?php
/**
 * @see       https://github.com/zendframework/zend-expressive for the canonical source repository
 * @copyright Copyright (c) 2015-2016 Zend Technologies USA Inc. (http://www.zend.com)
 * @license   https://github.com/zendframework/zend-expressive/blob/master/LICENSE.md New BSD License
 */

namespace Zend\Expressive\Container;

use ArrayObject;
use Interop\Container\ContainerInterface;
use Zend\Diactoros\Response\EmitterInterface;
use Zend\Expressive\Application;
use Zend\Expressive\Exception;
use Zend\Expressive\Router\FastRouteRouter;
use Zend\Expressive\Router\RouterInterface;
use Zend\Stratigility\FinalHandler;
use Zend\Stratigility\NoopFinalHandler;

/**
 * Factory to use with an IoC container in order to return an Application instance.
 *
 * This factory uses the following services, if available:
 *
 * - 'Zend\Expressive\Router\RouterInterface'. If missing, a FastRoute router
 *   bridge will be instantiated and used.
 * - 'Zend\Expressive\FinalHandler'. The service should be a callable to use as
 *   the final handler when the middleware pipeline is exhausted.
 *   If the 'zend-expressive.raise_throwables' flag is enabled, the factory will
 *   instead look for the 'Zend\Stratigility\NoopFinalHandler' service,
 *   creating an empty instance if none is found.
 * - 'Zend\Diactoros\Response\EmitterInterface'. If missing, an EmitterStack is
 *   created, adding a SapiEmitter to the bottom of the stack.
 * - 'config' (an array or ArrayAccess object). If present, and it contains route
 *   definitions, these will be used to seed routes in the Application instance
 *   before returning it.
 *
 * Please see `Zend\Expressive\ApplicationConfigInjectionTrait` for details on how
 * to provide routing and middleware pipeline configuration; this factory
 * delegates to the methods in that trait in order to seed the
 * `Application` instance (which composes the trait).
 *
 * You may disable injection of configured routing and the middleware pipeline
 * by enabling the `zend-expressive.programmatic_pipeline` configuration flag.
 *
 * You may opt-in to providing a middleware-based error handler by enabling the
 * `zend-expressive.raise_throwables` configuration flag. If you do this, the
 * application will no longer catch exceptions and invoke Stratigility error
 * middleware; you will instead need to provide custom middleware that provides
 * the try/catch block. You may use Zend\Stratigility\Middleware\ErrorHandler,
 * with our provided Zend\Expressive\Middleware\ErrorResponseGenerator and/or
 * Zend\Expressive\Middleware\WhoopsErrorResponseGenerator classes.
 *
 * Additionally, when raise_throwables is enabled, you will need to provide an
 * innermost middleware to invoke when the queue is exhausted; we suggest
 * Zend\Expressive\Middleware\NotFoundHandler.
 */
class ApplicationFactory
{
    const DISPATCH_MIDDLEWARE = 'EXPRESSIVE_DISPATCH_MIDDLEWARE';
    const ROUTING_MIDDLEWARE = 'EXPRESSIVE_ROUTING_MIDDLEWARE';

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
        $config = $container->has('config') ? $container->get('config') : [];
        $config = $config instanceof ArrayObject ? $config->getArrayCopy() : $config;

        $router = $container->has(RouterInterface::class)
            ? $container->get(RouterInterface::class)
            : new FastRouteRouter();

        $finalHandler = (isset($config['zend-expressive']['raise_throwables'])
            && $config['zend-expressive']['raise_throwables'])
            ? $this->marshalNoopFinalHandler($container)
            : $this->marshalLegacyFinalHandler($container, $config);

        $emitter = $container->has(EmitterInterface::class)
            ? $container->get(EmitterInterface::class)
            : null;

        $app = new Application($router, $container, $finalHandler, $emitter);

        if (isset($config['zend-expressive']['raise_throwables'])
            && $config['zend-expressive']['raise_throwables']
        ) {
            $app->raiseThrowables();
        }

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
        $app->injectRoutesFromConfig($config);
        $app->injectPipelineFromConfig($config);
    }

    /**
     * @param ContainerInterface $container
     * @return callable|NoopFinalHandler
     */
    private function marshalNoopFinalHandler(ContainerInterface $container)
    {
        return $container->has(NoopFinalHandler::class)
            ? $container->get(NoopFinalHandler::class)
            : new NoopFinalHandler();
    }

    /**
     * Create default FinalHandler with options configured under the key final_handler.options.
     *
     * @param ContainerInterface $container
     * @param array|\ArrayObject $config
     * @return callable|FinalHandler
     */
    private function marshalLegacyFinalHandler(ContainerInterface $container, $config)
    {
        if ($container->has('Zend\Expressive\FinalHandler')) {
            return $container->get('Zend\Expressive\FinalHandler');
        }

        $options = isset($config['final_handler']['options']) ? $config['final_handler']['options'] : [];
        return new FinalHandler($options, null);
    }
}
