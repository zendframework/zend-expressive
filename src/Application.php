<?php
/**
 * Zend Framework (http://framework.zend.com/)
 *
 * @see       https://github.com/zendframework/zend-expressive for the canonical source repository
 * @copyright Copyright (c) 2015 Zend Technologies USA Inc. (http://www.zend.com)
 * @license   https://github.com/zendframework/zend-expressive/blob/master/LICENSE.md New BSD License
 */

namespace Zend\Expressive;

use BadMethodCallException;
use DomainException;
use Interop\Container\ContainerInterface;
use Interop\Container\Exception\ContainerException;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Zend\Diactoros\Request;
use Zend\Diactoros\Response;
use Zend\Diactoros\Response\EmitterInterface;
use Zend\Diactoros\Response\SapiEmitter;
use Zend\Diactoros\ServerRequestFactory;
use Zend\Stratigility\FinalHandler;
use Zend\Stratigility\MiddlewarePipe;

/**
 * Middleware application providing routing based on paths and HTTP methods.
 *
 * @method Router\Route get($path, $middleware)
 * @method Router\Route post($path, $middleware)
 * @method Router\Route put($path, $middleware)
 * @method Router\Route patch($path, $middleware)
 * @method Router\Route delete($path, $middleware)
 */
class Application extends MiddlewarePipe
{
    /**
     * @var null|ContainerInterface
     */
    private $container;

    /**
     * @var EmitterInterface
     */
    private $emitter;

    /**
     * @var callable
     */
    private $finalHandler;

    /**
     * @var string[] HTTP methods that can be used for routing
     */
    private $httpRouteMethods = [
        'GET',
        'POST',
        'PUT',
        'PATCH',
        'DELETE',
    ];

    /**
     * @var bool Flag indicating whether or not the route middleware is
     *     registered in the middleware pipeline.
     */
    private $routeMiddlewareIsRegistered = false;

    /**
     * @var Router\RouterInterface
     */
    private $router;

    /**
     * List of all routes registered directly with the application.
     *
     * @var Route[]
     */
    private $routes = [];

    /**
     * Constructor
     *
     * Calls on the parent constructor, and then uses the provided arguments
     * to set internal properties.
     *
     * @param Router\RouterInterface $router
     * @param null|ContainerInterface $container IoC container from which to pull services, if any.
     * @param null|callable $finalHandler Final handler to use when $out is not
     *     provided on invocation.
     * @param null|EmitterInterface $emitter Emitter to use when `run()` is
     *     invoked.
     */
    public function __construct(
        Router\RouterInterface $router,
        ContainerInterface $container = null,
        callable $finalHandler = null,
        EmitterInterface $emitter = null
    ) {
        parent::__construct();
        $this->router       = $router;
        $this->container    = $container;
        $this->finalHandler = $finalHandler;
        $this->emitter      = $emitter;
    }

    /**
     * Overload middleware invocation.
     *
     * If $out is not provided, uses the result of `getFinalHandler()`.
     *
     * @param ServerRequestInterface $request
     * @param ResponseInterface $response
     * @param callable|null $out
     * @return ResponseInterface
     */
    public function __invoke(ServerRequestInterface $request, ResponseInterface $response, callable $out = null)
    {
        $out = $out ?: $this->getFinalHandler();
        return parent::__invoke($request, $response, $out);
    }

    /**
     * @param string $method
     * @param array $args
     * @return Router\Route
     * @throws BadMethodCallException if the $method is not in $httpRouteMethods.
     * @throws BadMethodCallException if receiving more or less than 2 arguments.
     */
    public function __call($method, $args)
    {
        if (! in_array(strtoupper($method), $this->httpRouteMethods, true)) {
            throw new BadMethodCallException('Unsupported method');
        }

        if (count($args) !== 2) {
            throw new BadMethodCallException(sprintf(
                '%s::%s requires exactly 2 arguments; received %d',
                __CLASS__,
                $method,
                count($args)
            ));
        }

        $args[] = [$method];

        // @TODO: we can use variadic parameters when dependency is raised to PHP 5.6
        return call_user_func_array([$this, 'route'], $args);
    }

    /**
     * Overload pipe() operation
     *
     * Ensures that the route middleware is only ever registered once.
     *
     * @param string|callable|object $path Either a URI path prefix, or middleware.
     * @param null|callable|object $middleware Middleware
     * @return self
     */
    public function pipe($path, $middleware = null)
    {
        if (null === $middleware && is_callable($path)) {
            $middleware = $path;
            $path       = '/';
        }

        if ($middleware === [$this, 'routeMiddleware'] && $this->routeMiddlewareIsRegistered) {
            return $this;
        }

        parent::pipe($path, $middleware);

        if ($middleware === [$this, 'routeMiddleware']) {
            $this->routeMiddlewareIsRegistered = true;
        }

        return $this;
    }

    /**
     * Middleware that routes the incoming request and delegates to the matched middleware.
     *
     * Uses the router to route the incoming request, dispatching matched
     * middleware on a request success condition.
     *
     * @param  ServerRequestInterface $request
     * @param  ResponseInterface $response
     * @param  callable $next
     * @return ResponseInterface
     * @throws Exception\InvalidArgumentException if the route result does not contain middleware
     * @throws Exception\InvalidArgumentException if unable to retrieve middleware from the container
     * @throws Exception\InvalidArgumentException if unable to resolve middleware to a callable
     */
    public function routeMiddleware(ServerRequestInterface $request, ResponseInterface $response, callable $next)
    {
        $result = $this->router->match($request);

        if ($result->isFailure()) {
            if ($result->isMethodFailure()) {
                $response = $response->withStatus(405)
                    ->withHeader('Allow', implode(',', $result->getAllowedMethods()));
            }
            return $next($request, $response);
        }

        foreach ($result->getMatchedParams() as $param => $value) {
            $request = $request->withAttribute($param, $value);
        }

        $middleware = $result->getMatchedMiddleware();
        if (! $middleware) {
            throw new Exception\InvalidMiddlewareException(sprintf(
                'The route %s does not have a middleware to dispatch',
                $result->getMatchedRouteName()
            ));
        }

        if (is_callable($middleware)) {
            return $middleware($request, $response, $next);
        }

        if (! is_string($middleware)) {
            throw new Exception\InvalidMiddlewareException(
                'The middleware specified is not callable'
            );
        }

        // try to get the action name from the container (if exists)
        $callable = $this->marshalMiddlewareFromContainer($middleware);
        if (is_callable($callable)) {
            return $callable($request, $response, $next);
        }

        // try to instantiate the middleware directly, if possible
        $callable = $this->marshalInvokableMiddleware($middleware);
        if (! is_callable($callable)) {
            throw new Exception\InvalidMiddlewareException(sprintf(
                'Unable to resolve middleware "%s" to a callable',
                $middleware
            ));
        }

        return $callable($request, $response, $next);
    }

    /**
     * Add a route for the route middleware to match.
     *
     * Accepts either a Router\Route instance, or a combination of a path and
     * middleware, and optionally the HTTP methods allowed.
     *
     * On first invocation, pipes the route middleware to the middleware
     * pipeline.
     *
     * @param string|Router\Route $path
     * @param callable|string $middleware Middleware (or middleware service name) to associate with route.
     * @param null|array $methods HTTP method to accept; null indicates any.
     * @return Router\Route
     * @throws Exception\InvalidArgumentException if $path is not a Router\Route AND middleware is null.
     */
    public function route($path, $middleware = null, array $methods = null)
    {
        if (! $path instanceof Router\Route && null === $middleware) {
            throw new Exception\InvalidArgumentException(sprintf(
                '%s expects either a route argument, or a combination of a path and middleware arguments',
                __METHOD__
            ));
        }

        if ($path instanceof Router\Route) {
            $route   = $path;
            $path    = $route->getPath();
            $methods = $route->getAllowedMethods();
        }

        $this->checkForDuplicateRoute($path, $methods);

        if (! isset($route)) {
            $methods = ($methods === null) ? Router\Route::HTTP_METHOD_ANY : $methods;
            $route   = new Router\Route($path, $middleware, $methods);
        }

        $this->routes[] = $route;
        $this->router->addRoute($route);

        if (! $this->routeMiddlewareIsRegistered) {
            $this->pipe([$this, 'routeMiddleware']);
        }

        return $route;
    }

    /**
     * Run the application
     *
     * If no request or response are provided, the method will use
     * ServerRequestFactory::fromGlobals to create a request instance, and
     * instantiate a default response instance.
     *
     * It then will invoke itself with the request and response, and emit
     * the returned response using the composed emitter.
     *
     * @param null|ServerRequestInterface $request
     * @param null|ResponseInterface $response
     */
    public function run(ServerRequestInterface $request = null, ResponseInterface $response = null)
    {
        $request  = $request ?: ServerRequestFactory::fromGlobals();
        $response = $response ?: new Response();

        $response = $this($request, $response);

        $emitter = $this->getEmitter();
        $emitter->emit($response);
    }

    /**
     * Retrieve the IoC container.
     *
     * If no IoC container is registered, we raise an exception.
     *
     * @return \Interop\Container\ContainerInterface
     * @throws Exception\ContainerNotRegisteredException
     */
    public function getContainer()
    {
        if (null === $this->container) {
            throw new Exception\ContainerNotRegisteredException();
        }
        return $this->container;
    }

    /**
     * Return the final handler to use during `run()` if the stack is exhausted.
     *
     * Creates an instance of Zend\Stratigility\FinalHandler if no handler is
     * already registered.
     *
     * @return callable
     */
    public function getFinalHandler()
    {
        if (! $this->finalHandler) {
            $this->finalHandler = new FinalHandler();
        }
        return $this->finalHandler;
    }

    /**
     * Retrieve an emitter to use during run().
     *
     * If none was registered during instantiation, this will lazy-load a
     * SapiEmitter instance.
     *
     * @return EmitterInterface
     */
    public function getEmitter()
    {
        if (! $this->emitter) {
            $this->emitter = new SapiEmitter;
        }
        return $this->emitter;
    }

    /**
     * Determine if the route is duplicated in the current list.
     *
     * Checks if a route with the same path exists already in the list;
     * if so, and it responds to any of the $methods indicated, raises
     * a DomainException indicating a duplicate route.
     *
     * @param string $path
     * @param null|array $methods
     * @throws DomainException on duplicate route detection.
     */
    private function checkForDuplicateRoute($path, $methods = null)
    {
        if ($methods === null) {
            $methods = Router\Route::HTTP_METHOD_ANY;
        }

        $matches = array_filter($this->routes, function ($route) use ($path, $methods) {
            if ($path !== $route->getPath()) {
                return false;
            }

            if ($methods === Router\Route::HTTP_METHOD_ANY) {
                return true;
            }

            return array_reduce($methods, function ($carry, $method) use ($route) {
                return ($carry || $route->allowsMethod($method));
            }, false);
        });

        if (count($matches) > 0) {
            throw new DomainException(
                'Duplicate route detected; same path, and one or more HTTP methods intersect'
            );
        }
    }

    /**
     * Attempt to retrieve the given middleware from the container.
     *
     * @param string $middleware
     * @return string|callable Returns $middleware intact on failure, and the
     *     middleware instance on success.
     * @throws Exception\InvalidArgumentException if a container exception occurs.
     */
    private function marshalMiddlewareFromContainer($middleware)
    {
        $container = $this->container;
        if (! $container || ! $container->has($middleware)) {
            return $middleware;
        }

        try {
            return $container->get($middleware);
        } catch (ContainerException $e) {
            throw new Exception\InvalidMiddlewareException(sprintf(
                'Unable to retrieve middleware "%s" from the container',
                $middleware
            ), $e->getCode(), $e);
        }
    }

    /**
     * Attempt to instantiate the given middleware.
     *
     * @param string $middleware
     * @return string|callable Returns $middleware intact on failure, and the
     *     middleware instance on success.
     */
    private function marshalInvokableMiddleware($middleware)
    {
        if (! class_exists($middleware)) {
            return $middleware;
        }

        return new $middleware();
    }
}
