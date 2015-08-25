<?php
/**
 * Zend Framework (http://framework.zend.com/)
 *
 * @see       https://github.com/zendframework/zend-expressive for the canonical source repository
 * @copyright Copyright (c) 2015 Zend Technologies USA Inc. (http://www.zend.com)
 * @license   https://github.com/zendframework/zend-expressive/blob/master/LICENSE.md New BSD License
 */

namespace Zend\Expressive;

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
 * @method Router\Route get($path, $middleware, $name = null)
 * @method Router\Route post($path, $middleware, $name = null)
 * @method Router\Route put($path, $middleware, $name = null)
 * @method Router\Route patch($path, $middleware, $name = null)
 * @method Router\Route delete($path, $middleware, $name = null)
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
     * @var Router\Route[]
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
        $this->pipeRoutingMiddleware();
        $out = $out ?: $this->getFinalHandler($response);
        return parent::__invoke($request, $response, $out);
    }

    /**
     * @param string $method
     * @param array $args
     * @return Router\Route
     * @throws Exception\BadMethodCallException if the $method is not in $httpRouteMethods.
     * @throws Exception\BadMethodCallException if receiving more or less than 2 arguments.
     */
    public function __call($method, $args)
    {
        if (! in_array(strtoupper($method), $this->httpRouteMethods, true)) {
            throw new Exception\BadMethodCallException('Unsupported method');
        }

        switch (count($args)) {
            case 2:
                // We have path and middleware; append the HTTP method.
                $args[] = [$method];
                break;
            case 3:
                // Need to reflow arguments to (0 => path, 1 => middleware, 2 => methods, 3 => name)
                // from (0 => path, 1 => middleware, 2 => name)
                $args[3] = $args[2];  // place name in $args[3]
                $args[2] = [$method]; // method becomes $args[2]
                break;
            default:
                throw new Exception\BadMethodCallException(sprintf(
                    '%s::%s requires at least 2 arguments, and no more than 3; received %d',
                    __CLASS__,
                    $method,
                    count($args)
                ));
        }

        // @TODO: we can use variadic parameters when dependency is raised to PHP 5.6
        return call_user_func_array([$this, 'route'], $args);
    }

    /**
     * Overload pipe() operation.
     *
     * Allows specifying service names for middleware, instead of requiring a
     * callable.
     *
     * Ensures that the route middleware is only ever registered once.
     *
     * @param string|callable $path Either a URI path prefix, or middleware.
     * @param null|string|callable $middleware Middleware
     * @return self
     */
    public function pipe($path, $middleware = null)
    {
        // Lazy-load middleware from the container when possible
        $container = $this->container;
        if (null === $middleware && is_string($path) && $container && $container->has($path)) {
            $middleware = $this->marshalLazyMiddlewareService($path, $container);
            $path       = '/';
        } elseif (is_string($middleware) && ! is_callable($middleware) && $container && $container->has($middleware)) {
            $middleware = $this->marshalLazyMiddlewareService($middleware, $container);
        } elseif (null === $middleware && is_callable($path)) {
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
     * Pipe an error handler.
     *
     * Proxies to pipe(), after first determining if the middleware represents
     * a service to lazy-load via the container.
     *
     * @param string|callable $path Either a URI path prefix, or middleware.
     * @param null|string|callable $middleware Middleware
     * @return self
     */
    public function pipeErrorHandler($path, $middleware = null)
    {
        // Lazy-load middleware from the container
        $container = $this->container;
        if (null === $middleware && is_string($path) && $container && $container->has($path)) {
            $middleware = $this->marshalLazyErrorMiddlewareService($path, $container);
            $path       = '/';
        } elseif (is_string($middleware) && ! is_callable($middleware) && $container && $container->has($middleware)) {
            $middleware = $this->marshalLazyErrorMiddlewareService($middleware, $container);
        } elseif (null === $middleware && is_callable($path)) {
            $middleware = $path;
            $path       = '/';
        }

        $this->pipe($path, $middleware);

        return $this;
    }

    /**
     * Register the routing middleware in the middleware pipeline.
     */
    public function pipeRoutingMiddleware()
    {
        if ($this->routeMiddlewareIsRegistered) {
            return;
        }
        $this->pipe([$this, 'routeMiddleware']);
    }

    /**
     * Middleware that routes the incoming request and delegates to the matched middleware.
     *
     * Uses the router to route the incoming request, dispatching matched
     * middleware on a request success condition.
     *
     * If routing fails, `$next()` is called; if routing fails due to HTTP
     * method negotiation, the response is set to a 405, injected with an
     * Allow header, and `$next()` is called with its `$error` argument set
     * to the value `405` (invoking the next error middleware).
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
                return $next($request, $response, 405);
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
     * @param null|string $name the name of the route
     * @return Router\Route
     * @throws Exception\InvalidArgumentException if $path is not a Router\Route AND middleware is null.
     */
    public function route($path, $middleware = null, array $methods = null, $name = null)
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
            $name    = $route->getName();
        }

        $this->checkForDuplicateRoute($path, $methods);

        if (! isset($route)) {
            $methods = (null === $methods) ? Router\Route::HTTP_METHOD_ANY : $methods;
            $route   = new Router\Route($path, $middleware, $methods, $name);
        }

        $this->routes[] = $route;
        $this->router->addRoute($route);
        $this->pipeRoutingMiddleware();

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
     * @param null|ResponseInterface $response Response instance with which to seed the
     *     FinalHandler; used to determine if the response passed to the handler
     *     represents the original or final response state.
     * @return callable
     */
    public function getFinalHandler(ResponseInterface $response = null)
    {
        if (! $this->finalHandler) {
            $this->finalHandler = new FinalHandler([], $response);
        }

        // Inject the handler with the response, if possible (e.g., the
        // TemplatedErrorHandler and WhoopsErrorHandler implementations).
        if (method_exists($this->finalHandler, 'setOriginalResponse')) {
            $this->finalHandler->setOriginalResponse($response);
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
     * Checks if a route with the same name or path exists already in the list;
     * if so, and it responds to any of the $methods indicated, raises
     * a DuplicateRouteException indicating a duplicate route.
     *
     * @param string $path
     * @param null|array $methods
     * @throws Exception\DuplicateRouteException on duplicate route detection.
     */
    private function checkForDuplicateRoute($path, $methods = null)
    {
        if (null === $methods) {
            $methods = Router\Route::HTTP_METHOD_ANY;
        }

        $matches = array_filter($this->routes, function (Router\Route $route) use ($path, $methods) {
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
            throw new Exception\DuplicateRouteException(
                'Duplicate route detected; same name or path, and one or more HTTP methods intersect'
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

    /**
     * @param string $middleware
     * @param ContainerInterface $container
     * @return callable
     */
    private function marshalLazyMiddlewareService($middleware, ContainerInterface $container)
    {
        return function ($request, $response, $next = null) use ($container, $middleware) {
            $invokable = $container->get($middleware);
            if (! is_callable($invokable)) {
                throw new Exception\InvalidMiddlewareException(sprintf(
                    'Lazy-loaded middleware "%s" is not invokable',
                    $middleware
                ));
            }
            return $invokable($request, $response, $next);
        };
    }

    /**
     * @param string $middleware
     * @param ContainerInterface $container
     * @return callable
     */
    private function marshalLazyErrorMiddlewareService($middleware, ContainerInterface $container)
    {
        return function ($error, $request, $response, $next) use ($container, $middleware) {
            $invokable = $container->get($middleware);
            if (! is_callable($invokable)) {
                throw new Exception\InvalidMiddlewareException(sprintf(
                    'Lazy-loaded middleware "%s" is not invokable',
                    $middleware
                ));
            }
            return $invokable($error, $request, $response, $next);
        };
    }
}
