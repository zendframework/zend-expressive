<?php

namespace Zend\Expressive;

use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\ResponseInterface;
use Zend\Stratigility\MiddlewarePipe;
use Zend\Diactoros\Server;
use Zend\Stratigility\MiddlewareInterface;
use Router\RouterInterface;

class App
{
    const DEFAULT_ROUTER = 'Zend\Expressive\Router\Aura';

    /**
     * @var array
     */
    private $httpMethods = [ 'GET', 'POST', 'PUT', 'PATCH', 'DELETE', 'HEAD', 'OPTIONS' ];

    /**
     * @var array
     */
    protected $routes = [];

    /**
     * @var array
     */
    protected $config;

    /**
     * @var Zend\Stratigility\MiddlewarePipe
     */
    protected $pipeline;

    /**
     * @var Zend\Diactoros\Server
     */
    protected $server;

    /**
     * @var Router
     */
    protected $router;

    /**
     * Constructor
     *
     * @param array $config
     */
    public function __construct(array $config = [])
    {
        $this->config   = $config;
        $this->parseConfig($config);
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
        $path = $request->getUri()->getPath();
        if (!$this->router->match($path, $request->getServerParams())) {
            return $next($request, $response);
        }
        // Match the HTTP methods in the routes array
        $routeName = $this->router->getMatchedName();
        if (!in_array($request->getMethod(), $this->routes[$routeName]['methods'])) {
            return $response->withStatus(405, "Method now allowed");
        }
        foreach ($this->router->getMatchedParams() as $param => $value) {
            $request = $request->withAttribute($param, $value);
        }
        $callable = $this->router->getMatchedCallable();
        if (!$callable) {
            throw new Exception\InvalidArgumentException(
                sprintf("The route %s doesn't have an action to dispatch", $this->router->getMatchedName())
            );
        }
        if (is_callable($callable)) {
            return call_user_func_array($callable, [
                $request,
                $response,
                $next,
            ]);
        } elseif (is_string($callable)) {
            // try to get the action name from the container (if exists)
            if ($this->container && $this->container->has($callable)) {
                try {
                    $call = $this->container->get($callable);
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
                            "The class %s, from container, has thrown the exception: %s",
                            $callable,
                            $e->getMessage()
                        )
                    );
                }
            }
            // try to instantiate the class name (if exists) and invoke it (if invokables)
            if (class_exists($callable)) {
                $call = new $callable;
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
            sprintf("The callable specified in the route %s is not invokable", $this->router->getMatchedName())
        );
    }

    /**
     * Set Router
     *
     * @param Router\RouterInterface $router
     */
    public function setRouter(RouterInterface $router)
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
     * Parse the config and create the shared objects:
     * router, template, container
     *
     * @param array $config
     */
    protected function parseConfig(array $config)
    {
        // Router
        $adapter = isset($config['router']['adapter']) ?
                   $config['router']['adapter'] :
                   self::DEFAULT_ROUTER;
        $this->router = new $adapter();
    }

    /**
     * Add a custom HTTP method
     *
     * @param string $method
     */
    public function addHttpMethod($method)
    {
        $method = strtoupper($method);
        if (!in_array($method, $this->httpMethods)) {
            $this->httpMethods[] = $method;
        }
    }

    /**
     * Get the HTTP methods supported (including the customs)
     *
     * @return array
     */
    public function getHttpMethods()
    {
        return $this->httpMethods;
    }

    /**
     * Get the app configuration
     *
     * @return array
     */
    public function getConfig()
    {
        return $this->config;
    }

    /**
     * Add a middleware based on HTTP method
     *
     * @param string $name
     * @param array $arguments
     * @throws Exception\InvalidArgumentException, Exception\BadMethodCallException
     */
    public function __call($name, $arguments)
    {
        if (!in_array(strtoupper($name), $this->httpMethods)) {
            throw new Exception\BadMethodCallException(sprintf(
                "The %s() method is not defined in %s",
                $name,
                __CLASS__
            ));
        }
        if (2 > count($arguments) || 3 < count($arguments)) {
            throw new Exception\InvalidArgumentException(sprintf(
                "The %s() method requires at least 2 parameters, a URL to match and a callable",
                $name
            ));
        }
        $routeName = md5($name . '_' . $arguments[0]);
        $options   = isset($arguments[2]) ? $arguments[2] : [];
        $this->routes[$routeName] = [
            'url'      => $arguments[0],
            'methods'  => [ strtoupper($name) ],
            'options'  => $options
        ];
        $this->router->addRoute($routeName, $arguments[0], $arguments[1], $options);
    }

    /**
     * Add a route
     *
     * @param  string $path
     * @param  callable $callable
     * @param  array $methods
     * @throws Exception\InvalidArgumentException, Exception\RuntimeException
     */
    public function addRoute($name, $path, $callable, $methods = ['GET'], $options = [])
    {
        if (array_diff($methods, $this->httpMethods)) {
            throw new Exception\InvalidArgumentException(sprintf(
                "Only these HTTP methods are accepted: %s. You can add custom HTTP using addHttpMethod()",
                implode(',', $this->httpMethods)
            ));
        }
        $this->routes[$name] = [
            'url'      => $path,
            'methods'  => $methods,
            'options'  => $options
        ];
        $this->router->addRoute($name, $path, $callable, $options);
    }

    /**
     * Execute the application
     */
    public function run()
    {
        $this->pipeline = new MiddlewarePipe();
        $this->pipeline->pipe($this);
        $this->server = Server::createServer($this->pipeline, $_SERVER, $_GET, $_POST, $_COOKIE, $_FILES);
        $this->server->listen();
    }
}
