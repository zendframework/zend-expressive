<?php
/**
 * Zend Framework (http://framework.zend.com/)
 *
 * @see       http://github.com/zendframework/zend-diactoros for the canonical source repository
 * @copyright Copyright (c) 2015 Zend Technologies USA Inc. (http://www.zend.com)
 * @license   https://github.com/zendframework/zend-diactoros/blob/master/LICENSE.md New BSD License
 */

namespace Zend\Expressive;

use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\ResponseInterface;
use Interop\Container\ContainerInterface;
use Interop\Container\Exception\NotFoundException;
use Interop\Container\Exception\ContainerException;

class Dispatcher
{
    /**
     * @var Router\RouterInterface
     */
    protected $router;

    /**
     * @var ContainerInterface
     */
    protected $container;

    /**
     * Constructor
     *
     * @param Router\RouterInterface $router
     * @param ContainerInterface $container
     */
    public function __construct(Router\RouterInterface $router, ContainerInterface $container = null)
    {
        $this->setRouter($router);
        if (null !== $container) {
            $this->setContainer($container);
        }
    }

    /**
     * Invoke
     *
     * @param  ServerRequestInterface $request
     * @param  ResponseInterface $response
     * @param  callable $next
     * @throws Exception\InvalidArgumentException
     * @return callable
     */
    public function __invoke(ServerRequestInterface $request, ResponseInterface $response, callable $next)
    {
        $path   = $request->getUri()->getPath();
        $router = $this->getRouter();
        if (! $router->match($path, $request->getServerParams())) {
            return $next($request, $response);
        }
        foreach ($router->getMatchedParams() as $param => $value) {
            $request = $request->withAttribute($param, $value);
        }
        $action = $router->getMatchedAction();
        if (! $action) {
            throw new Exception\InvalidArgumentException(
                sprintf("The route %s doesn't have an action to dispatch", $this->router->getMatchedName())
            );
        }
        if (is_callable($action)) {
            return call_user_func_array($action, [
                $request,
                $response,
                $next,
            ]);
        } elseif (is_string($action)) {
            // try to get the action name from the container (if exists)
            $container = $this->getContainer();
            if ($container && $container->has($action)) {
                try {
                    $call = $container->get($action);
                    if (is_callable($call)) {
                        return call_user_func_array($call, [
                            $request,
                            $response,
                            $next,
                        ]);
                    }
                } catch (ContainerException $e) {
                    throw new Exception\InvalidArgumentException(
                        sprintf(
                            "The action class %s, from the container, has thrown the exception: %s",
                            $action,
                            $e->getMessage()
                        )
                    );
                }
            }
            // try to instanciate the class name (if exists) and invoke it (if invokables)
            if (class_exists($action)) {
                $call = new $action;
                if (is_callable($call)) {
                    return call_user_func_array($call, [
                        $request,
                        $response,
                        $next,
                    ]);
                }
            }
        }
        throw new Exception\InvalidArgumentException(
            sprintf("The action class specified %s is not invokable", $action)
        );
    }

    /**
     * Set Router
     *
     * @param Router\RouterInterface $router
     */
    public function setRouter(Router\RouterInterface $router)
    {
        $this->router = $router;
    }

    /**
     * Get Router
     *
     * @return Router\RouterInterface
     */
    public function getRouter()
    {
        return $this->router;
    }

    /**
     * Set Container
     *
     * @param ContainerInterface $container
     */
    public function setContainer(ContainerInterface $container)
    {
        $this->container = $container;
    }

    /**
     * Get Container
     *
     * @return ContainerInterface
     */
    public function getContainer()
    {
        return $this->container;
    }
}
