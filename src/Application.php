<?php
/**
 * @see       https://github.com/zendframework/zend-expressive for the canonical source repository
 * @copyright Copyright (c) 2015-2017 Zend Technologies USA Inc. (http://www.zend.com)
 * @license   https://github.com/zendframework/zend-expressive/blob/master/LICENSE.md New BSD License
 */

namespace Zend\Expressive;

use Interop\Container\ContainerInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Zend\Diactoros\Request;
use Zend\Diactoros\Response;
use Zend\Diactoros\Response\EmitterInterface;
use Zend\Diactoros\Response\SapiEmitter;
use Zend\Diactoros\ServerRequestFactory;
use Zend\Stratigility\FinalHandler;
use Zend\Stratigility\Http\Response as StratigilityResponse;
use Zend\Stratigility\MiddlewarePipe;

/**
 * Middleware application providing routing based on paths and HTTP methods.
 *
 * @todo For 1.1, remove the RouteResultSubjectInterface implementation, and
 *     all deprecated properties and methods.
 * @method Router\Route get($path, $middleware, $name = null)
 * @method Router\Route post($path, $middleware, $name = null)
 * @method Router\Route put($path, $middleware, $name = null)
 * @method Router\Route patch($path, $middleware, $name = null)
 * @method Router\Route delete($path, $middleware, $name = null)
 */
class Application extends MiddlewarePipe implements Router\RouteResultSubjectInterface
{
    use MarshalMiddlewareTrait;

    /**
     * @var null|ContainerInterface
     */
    private $container;

    /**
     * @var bool Flag indicating whether or not the dispatch middleware is
     *     registered in the middleware pipeline.
     */
    private $dispatchMiddlewareIsRegistered = false;

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
     * @deprecated This property will be removed in v1.1.
     * @var bool Flag indicating whether or not the route result observer
     *     middleware is registered in the middleware pipeline.
     */
    private $routeResultObserverMiddlewareIsRegistered = false;

    /**
     * Observers to trigger once we have a route result.
     *
     * @var Router\RouteResultObserverInterface[]
     */
    private $routeResultObservers = [];

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
     * @todo Remove logic for creating final handler for version 2.0.0.
     * @todo Remove swallowDeprecationNotices() invocation for version 2.0.0.
     * @param ServerRequestInterface $request
     * @param ResponseInterface $response
     * @param callable|null $out
     * @return ResponseInterface
     */
    public function __invoke(ServerRequestInterface $request, ResponseInterface $response, callable $out = null)
    {
        $this->swallowDeprecationNotices();

        if (! $out && (null === ($out = $this->getFinalHandler($response)))) {
            $response = $response instanceof StratigilityResponse
                ? $response
                : new StratigilityResponse($response);
            $out = new FinalHandler([], $response);
        }

        $result = parent::__invoke($request, $response, $out);

        restore_error_handler();

        return $result;
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
     * @param string|Router\Route $path
     * @param callable|string     $middleware Middleware (or middleware service name) to associate with route.
     * @param null|string         $name the name of the route
     * @return Router\Route
     */
    public function any($path, $middleware, $name = null)
    {
        return $this->route($path, $middleware, null, $name);
    }

    /**
     * Attach a route result observer.
     *
     * @deprecated This method will be removed in v1.1.
     * @param Router\RouteResultObserverInterface $observer
     */
    public function attachRouteResultObserver(Router\RouteResultObserverInterface $observer)
    {
        $this->routeResultObservers[] = $observer;
    }

    /**
     * Detach a route result observer.
     *
     * @deprecated This method will be removed in v1.1.
     * @param Router\RouteResultObserverInterface $observer
     */
    public function detachRouteResultObserver(Router\RouteResultObserverInterface $observer)
    {
        if (false === ($index = array_search($observer, $this->routeResultObservers, true))) {
            return;
        }
        unset($this->routeResultObservers[$index]);
    }

    /**
     * Notify all route result observers with the given route result.
     *
     * @deprecated This method will be removed in v1.1.
     * @param Router\RouteResult
     */
    public function notifyRouteResultObservers(Router\RouteResult $result)
    {
        foreach ($this->routeResultObservers as $observer) {
            $observer->update($result);
        }
    }

    /**
     * Overload pipe() operation.
     *
     * Middleware piped may be either callables or service names. Middleware
     * specified as services will be wrapped in a closure similar to the
     * following:
     *
     * <code>
     * function ($request, $response, $next = null) use ($container, $middleware) {
     *     $invokable = $container->get($middleware);
     *     if (! is_callable($invokable)) {
     *         throw new Exception\InvalidMiddlewareException(sprintf(
     *             'Lazy-loaded middleware "%s" is not invokable',
     *             $middleware
     *         ));
     *     }
     *     return $invokable($request, $response, $next);
     * };
     * </code>
     *
     * This is done to delay fetching the middleware until it is actually used;
     * the upshot is that you will not be notified if the service is invalid to
     * use as middleware until runtime.
     *
     * Middleware may also be passed as an array; each item in the array must
     * resolve to middleware eventually (i.e., callable or service name).
     *
     * Finally, ensures that the route middleware is only ever registered
     * once.
     *
     * @param string|array|callable $path Either a URI path prefix, or middleware.
     * @param null|string|array|callable $middleware Middleware
     * @return self
     */
    public function pipe($path, $middleware = null)
    {
        if (null === $middleware) {
            $middleware = $this->prepareMiddleware($path, $this->container);
            $path = '/';
        }

        if (! is_callable($middleware)
            && (is_string($middleware) || is_array($middleware))
        ) {
            $middleware = $this->prepareMiddleware($middleware, $this->container);
        }

        if ($middleware === [$this, 'routeMiddleware'] && $this->routeMiddlewareIsRegistered) {
            return $this;
        }

        if ($middleware === [$this, 'dispatchMiddleware'] && $this->dispatchMiddlewareIsRegistered) {
            return $this;
        }

        parent::pipe($path, $middleware);

        if ($middleware === [$this, 'routeMiddleware']) {
            $this->routeMiddlewareIsRegistered = true;
        }

        if ($middleware === [$this, 'dispatchMiddleware']) {
            $this->dispatchMiddlewareIsRegistered = true;
        }

        return $this;
    }

    /**
     * Pipe an error handler.
     *
     * Middleware piped may be either callables or service names. Middleware
     * specified as services will be wrapped in a closure similar to the
     * following:
     *
     * <code>
     * function ($error, $request, $response, $next) use ($container, $middleware) {
     *     $invokable = $container->get($middleware);
     *     if (! is_callable($invokable)) {
     *         throw new Exception\InvalidMiddlewareException(sprintf(
     *             'Lazy-loaded middleware "%s" is not invokable',
     *             $middleware
     *         ));
     *     }
     *     return $invokable($error, $request, $response, $next);
     * };
     * </code>
     *
     * This is done to delay fetching the middleware until it is actually used;
     * the upshot is that you will not be notified if the service is invalid to
     * use as middleware until runtime.
     *
     * Once middleware detection and wrapping (if necessary) is complete,
     * proxies to pipe().
     *
     * @param string|callable $path Either a URI path prefix, or middleware.
     * @param null|string|callable $middleware Middleware
     * @return self
     */
    public function pipeErrorHandler($path, $middleware = null)
    {
        if (null === $middleware) {
            $middleware = $this->prepareMiddleware($path, $this->container, $forError = true);
            $path = '/';
        }

        if (! is_callable($middleware)
            && (is_string($middleware) || is_array($middleware))
        ) {
            $middleware = $this->prepareMiddleware($middleware, $this->container, $forError = true);
        }

        parent::pipe($path, $middleware);

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
     * Register the dispatch middleware in the middleware pipeline.
     */
    public function pipeDispatchMiddleware()
    {
        if ($this->dispatchMiddlewareIsRegistered) {
            return;
        }
        $this->pipe([$this, 'dispatchMiddleware']);
    }

    /**
     * Register the route result observer middleware in the middleware pipeline.
     *
     * @deprecated This method will be removed in v1.1.
     */
    public function pipeRouteResultObserverMiddleware()
    {
        if ($this->routeResultObserverMiddlewareIsRegistered) {
            return;
        }
        $this->pipe([$this, 'routeResultObserverMiddleware']);
        $this->routeResultObserverMiddlewareIsRegistered = true;
    }

    /**
     * Middleware that routes the incoming request and delegates to the matched middleware.
     *
     * Uses the router to route the incoming request, injecting the request
     * with:
     *
     * - the route result object (under a key named for the RouteResult class)
     * - attributes for each matched routing parameter
     *
     * On completion, it calls on the next middleware (typically the
     * `dispatchMiddleware()`).
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
     */
    public function routeMiddleware(ServerRequestInterface $request, ResponseInterface $response, callable $next)
    {
        $result = $this->router->match($request);

        if ($result->isFailure()) {
            if ($result->isMethodFailure()) {
                $response = $response->withStatus(405)
                    ->withHeader('Allow', implode(',', $result->getAllowedMethods()));

                // Need to swallow deprecation notices, as this is how 405 errors
                // are reported in the 1.0 series.
                $this->swallowDeprecationNotices();
                return $next($request, $response, 405);
            }
            return $next($request, $response);
        }

        // Inject the actual route result, as well as individual matched parameters.
        $request = $request->withAttribute(Router\RouteResult::class, $result);
        foreach ($result->getMatchedParams() as $param => $value) {
            $request = $request->withAttribute($param, $value);
        }

        return $next($request, $response);
    }

    /**
     * Dispatch the middleware matched by routing.
     *
     * If the request does not have the route result, calls on the next
     * middleware.
     *
     * Next, it checks if the route result has matched middleware; if not, it
     * raises an exception.
     *
     * Finally, it attempts to marshal the middleware, and dispatches it when
     * complete, return the response.
     *
     * @param ServerRequestInterface $request
     * @param ResponseInterface $response
     * @param callable $next
     * @returns ResponseInterface
     * @throws Exception\InvalidMiddlewareException if no middleware is present
     *     to dispatch in the route result.
     */
    public function dispatchMiddleware(ServerRequestInterface $request, ResponseInterface $response, callable $next)
    {
        $routeResult = $request->getAttribute(Router\RouteResult::class, false);
        if (! $routeResult) {
            return $next($request, $response);
        }

        $middleware = $routeResult->getMatchedMiddleware();
        if (! $middleware) {
            throw new Exception\InvalidMiddlewareException(sprintf(
                'The route %s does not have a middleware to dispatch',
                $routeResult->getMatchedRouteName()
            ));
        }

        $middleware = $this->prepareMiddleware($middleware, $this->container);
        return $middleware($request, $response, $next);
    }

    /**
     * Middleware for notifying route result observers.
     *
     * If the request has a route result, calls notifyRouteResultObservers().
     *
     * This middleware should be injected between the routing and dispatch
     * middleware when creating your middleware pipeline.
     *
     * If you are using this, rewrite your observers as middleware that
     * pulls the route result from the request instead.
     *
     * @deprecated This method will be removed in v1.1.
     * @param ServerRequestInterface $request
     * @param ResponseInterface $response
     * @param callable $next
     * @returns ResponseInterface
     */
    public function routeResultObserverMiddleware(
        ServerRequestInterface $request,
        ResponseInterface $response,
        callable $next
    ) {
        $result = $request->getAttribute(Router\RouteResult::class, false);
        if ($result) {
            $this->notifyRouteResultObservers($result);
        }

        return $next($request, $response);
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
     * @param callable|string|array $middleware Middleware (or middleware service name) to associate with route.
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
        $response = $response ?: new Response();
        $request  = $request ?: ServerRequestFactory::fromGlobals();
        $request  = $request->withAttribute('originalResponse', $response);

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
     * @param null|ResponseInterface $response Response instance with which to seed the
     *     FinalHandler; used to determine if the response passed to the handler
     *     represents the original or final response state.
     * @return callable|null
     */
    public function getFinalHandler(ResponseInterface $response = null)
    {
        if (! $this->finalHandler) {
            return null;
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
     * If none was registered during instantiation, this will lazy-load an
     * EmitterStack composing an SapiEmitter instance.
     *
     * @return EmitterInterface
     */
    public function getEmitter()
    {
        if (! $this->emitter) {
            $this->emitter = new Emitter\EmitterStack();
            $this->emitter->push(new SapiEmitter());
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
     * Register an error handler to swallow deprecation notices due to error middleware usage.
     *
     * @todo Remove method for version 2.0.0.
     * @return void
     */
    private function swallowDeprecationNotices()
    {
        $previous = null;
        $handler = function ($errno, $errstr, $errfile, $errline, $errcontext) use (&$previous) {
            $swallow = $errno === E_USER_DEPRECATED && false !== strstr($errstr, 'error middleware is deprecated');

            if ($swallow || $previous === null) {
                return $swallow;
            }

            $previous($errno, $errstr, $errfile, $errline, $errcontext);
        };

        $previous = set_error_handler($handler);
    }
}
