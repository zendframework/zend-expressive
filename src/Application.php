<?php
/**
 * @see       https://github.com/zendframework/zend-expressive for the canonical source repository
 * @copyright Copyright (c) 2015-2017 Zend Technologies USA Inc. (http://www.zend.com)
 * @license   https://github.com/zendframework/zend-expressive/blob/master/LICENSE.md New BSD License
 */

namespace Zend\Expressive;

use Fig\Http\Message\StatusCodeInterface as StatusCode;
use Interop\Http\ServerMiddleware\DelegateInterface;
use InvalidArgumentException;
use Psr\Container\ContainerInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use UnexpectedValueException;
use Zend\Diactoros\Response;
use Zend\Diactoros\Response\EmitterInterface;
use Zend\Diactoros\Response\SapiEmitter;
use Zend\Diactoros\ServerRequest;
use Zend\Diactoros\ServerRequestFactory;
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
    use ApplicationConfigInjectionTrait;
    use MarshalMiddlewareTrait;

    const DISPATCH_MIDDLEWARE = 'EXPRESSIVE_DISPATCH_MIDDLEWARE';
    const ROUTING_MIDDLEWARE = 'EXPRESSIVE_ROUTING_MIDDLEWARE';

    /**
     * @var null|ContainerInterface
     */
    private $container;

    /**
     * @var callable
     */
    private $defaultDelegate;

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
     * @param null|DelegateInterface $defaultDelegate Default delegate
     *     to use when $out is not provided on invocation / run() is invoked.
     * @param null|EmitterInterface $emitter Emitter to use when `run()` is
     *     invoked.
     */
    public function __construct(
        Router\RouterInterface $router,
        ContainerInterface $container = null,
        DelegateInterface $defaultDelegate = null,
        EmitterInterface $emitter = null
    ) {
        parent::__construct();
        $this->router          = $router;
        $this->container       = $container;
        $this->defaultDelegate = $defaultDelegate;
        $this->emitter         = $emitter;

        $this->setResponsePrototype(new Response());
    }

    /**
     * @param string $method
     * @param array $args
     * @return Router\Route
     * @throws Exception\BadMethodCallException if the $method is not in $httpRouteMethods.
     * @throws Exception\BadMethodCallException if receiving more or less than 2 arguments.
     */
    public function __call($method, array $args)
    {
        if (! in_array(strtoupper($method), $this->httpRouteMethods, true)) {
            throw new Exception\BadMethodCallException('Unsupported method');
        }

        switch (count($args)) {
            case 2:
                // We have path and middleware; append the HTTP method.
                $routeArgs = [
                    $args[0],
                    $args[1],
                    [$method],
                ];
                break;
            case 3:
                // Need to reflow arguments to (0 => path, 1 => middleware, 2 => methods, 3 => name)
                // from (0 => path, 1 => middleware, 2 => name)
                $routeArgs = [
                    $args[0],
                    $args[1],
                    [$method],
                    $args[2],
                ];
                break;
            default:
                throw new Exception\BadMethodCallException(sprintf(
                    '%s::%s requires at least 2 arguments, and no more than 3; received %d',
                    __CLASS__,
                    $method,
                    count($args)
                ));
        }

        return $this->route(...$routeArgs);
    }

    /**
     * @param string|Router\Route $path
     * @param callable|string $middleware Middleware (or middleware service name) to associate with route.
     * @param null|string $name The name of the route.
     * @return Router\Route
     */
    public function any($path, $middleware, $name = null)
    {
        return $this->route($path, $middleware, null, $name);
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
            $middleware = $this->prepareMiddleware(
                $path,
                $this->router,
                $this->responsePrototype,
                $this->container
            );
            $path = '/';
        }

        if (! is_callable($middleware)
            && (is_string($middleware) || is_array($middleware))
        ) {
            $middleware = $this->prepareMiddleware(
                $middleware,
                $this->router,
                $this->responsePrototype,
                $this->container
            );
        }

        if ($middleware instanceof Middleware\RouteMiddleware && $this->routeMiddlewareIsRegistered) {
            return $this;
        }

        if ($middleware instanceof Middleware\DispatchMiddleware && $this->dispatchMiddlewareIsRegistered) {
            return $this;
        }

        parent::pipe($path, $middleware);

        if ($middleware instanceof Middleware\RouteMiddleware) {
            $this->routeMiddlewareIsRegistered = true;
        }

        if ($middleware instanceof Middleware\DispatchMiddleware) {
            $this->dispatchMiddlewareIsRegistered = true;
        }

        return $this;
    }

    /**
     * Register the routing middleware in the middleware pipeline.
     *
     * @return void
     */
    public function pipeRoutingMiddleware()
    {
        if ($this->routeMiddlewareIsRegistered) {
            return;
        }
        $this->pipe(self::ROUTING_MIDDLEWARE);
    }

    /**
     * Register the dispatch middleware in the middleware pipeline.
     *
     * @return void
     */
    public function pipeDispatchMiddleware()
    {
        if ($this->dispatchMiddlewareIsRegistered) {
            return;
        }
        $this->pipe(self::DISPATCH_MIDDLEWARE);
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
     * @param null|string $name The name of the route.
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
            $methods    = null === $methods ? Router\Route::HTTP_METHOD_ANY : $methods;
            $middleware = $this->prepareMiddleware(
                $middleware,
                $this->router,
                $this->responsePrototype,
                $this->container
            );
            $route      = new Router\Route($path, $middleware, $methods, $name);
        }

        $this->routes[] = $route;
        $this->router->addRoute($route);

        return $route;
    }

    /**
     * Retrieve all directly registered routes with the application.
     *
     * @return Router\Route[]
     */
    public function getRoutes()
    {
        return $this->routes;
    }

    /**
     * Run the application
     *
     * If no request or response are provided, the method will use
     * ServerRequestFactory::fromGlobals to create a request instance, and
     * instantiate a default response instance.
     *
     * It retrieves the default delegate using getDefaultDelegate(), and
     * uses that to process itself.
     *
     * Once it has processed itself, it emits the returned response using the
     * composed emitter.
     *
     * @param null|ServerRequestInterface $request
     * @param null|ResponseInterface $response
     * @return void
     */
    public function run(ServerRequestInterface $request = null, ResponseInterface $response = null)
    {
        try {
            $request  = $request ?: ServerRequestFactory::fromGlobals();
        } catch (InvalidArgumentException $e) {
            // Unable to parse uploaded files
            $this->emitMarshalServerRequestException($e);
            return;
        } catch (UnexpectedValueException $e) {
            // Invalid request method
            $this->emitMarshalServerRequestException($e);
            return;
        }

        $response = $response ?: new Response();
        $request  = $request->withAttribute('originalResponse', $response);
        $delegate = $this->getDefaultDelegate();

        $response = $this->process($request, $delegate);

        $emitter = $this->getEmitter();
        $emitter->emit($response);
    }

    /**
     * Retrieve the IoC container.
     *
     * If no IoC container is registered, we raise an exception.
     *
     * @return ContainerInterface
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
     * Return the default delegate to use during `run()` if the stack is exhausted.
     *
     * If no default delegate is present, attempts the following:
     *
     * - If a container is composed, and it has the 'Zend\Expressive\Delegate\DefaultDelegate'
     *   service, pulls that service, assigns it, and returns it.
     * - If no container is composed, creates an instance of Delegate\NotFoundDelegate
     *   using the current response prototype only (i.e., no templating).
     *
     * @return DelegateInterface
     */
    public function getDefaultDelegate()
    {
        if ($this->defaultDelegate) {
            return $this->defaultDelegate;
        }

        if ($this->container && $this->container->has('Zend\Expressive\Delegate\DefaultDelegate')) {
            $this->defaultDelegate = $this->container->get('Zend\Expressive\Delegate\DefaultDelegate');
            return $this->defaultDelegate;
        }

        if ($this->container) {
            $factory = new Container\NotFoundDelegateFactory();
            $this->defaultDelegate = $factory($this->container);
            return $this->defaultDelegate;
        }

        $this->defaultDelegate = new Delegate\NotFoundDelegate($this->responsePrototype);
        return $this->defaultDelegate;
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
     * @param \Exception|\Throwable $exception
     * @return void
     */
    private function emitMarshalServerRequestException($exception)
    {
        if ($this->container && $this->container->has(Middleware\ErrorResponseGenerator::class)) {
            $generator = $this->container->get(Middleware\ErrorResponseGenerator::class);
            $response = $generator($exception, new ServerRequest(), $this->responsePrototype);
        } else {
            $response = $this->responsePrototype
                ->withStatus(StatusCode::STATUS_BAD_REQUEST);
        }

        $emitter = $this->getEmitter();
        $emitter->emit($response);
    }
}
