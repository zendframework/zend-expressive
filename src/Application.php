<?php
/**
 * @see       https://github.com/zendframework/zend-expressive for the canonical source repository
 * @copyright Copyright (c) 2015-2017 Zend Technologies USA Inc. (https://www.zend.com)
 * @license   https://github.com/zendframework/zend-expressive/blob/master/LICENSE.md New BSD License
 */

declare(strict_types=1);

namespace Zend\Expressive;

use Fig\Http\Message\StatusCodeInterface as StatusCode;
use InvalidArgumentException;
use Psr\Container\ContainerInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Throwable;
use UnexpectedValueException;
use Zend\Diactoros\Response;
use Zend\Diactoros\Response\EmitterInterface;
use Zend\Diactoros\Response\SapiEmitter;
use Zend\Diactoros\ServerRequest;
use Zend\Diactoros\ServerRequestFactory;
use Zend\Stratigility\MiddlewarePipe;

/**
 * Middleware application providing routing based on paths and HTTP methods.
 */
class Application implements MiddlewareInterface, RequestHandlerInterface
{
    use ApplicationConfigInjectionTrait;

    /**
     * @var null|ContainerInterface
     */
    private $container;

    /**
     * @var null|RequestHandlerInterface
     */
    private $defaultHandler;

    /**
     * @var null|EmitterInterface
     */
    private $emitter;

    /**
     * @var MiddlewareFactory
     */
    private $middlewareFactory;

    /**
     * @var null|callable
     */
    private $pathDecorator;

    /**
     * @var MiddlewarePipe
     */
    private $pipeline;

    /**
     * @var null|callable
     */
    private $pipelineDecorator;

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
     * @var ResponseInterface
     */
    private $responsePrototype;

    /**
     * Calls on the parent constructor, and then uses the provided arguments
     * to set internal properties.
     *
     * @param null|ContainerInterface $container IoC container from which to pull services, if any.
     * @param null|RequestHandlerInterface $defaultHandler Default handler
     *     to use when $out is not provided on invocation / run() is invoked.
     * @param null|EmitterInterface $emitter Emitter to use when `run()` is
     *     invoked.
     */
    public function __construct(
        Router\RouterInterface $router,
        ContainerInterface $container = null,
        RequestHandlerInterface $defaultHandler = null,
        EmitterInterface $emitter = null,
        ResponseInterface $responsePrototype = null
    ) {
        $this->pipeline          = new MiddlewarePipe();
        $this->router            = $router;
        $this->container         = $container;
        $this->middlewareFactory = $container ? new MiddlewareFactory(new MiddlewareContainer($container)) : null;
        $this->defaultHandler    = $defaultHandler;
        $this->emitter           = $emitter;
        $this->responsePrototype = $responsePrototype ?: new Response();
    }

    public function handle(ServerRequestInterface $request) : ResponseInterface
    {
        return $this->process($request, $this->getDefaultHandler());
    }

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler) : ResponseInterface
    {
        return $this->pipeline->process($request, $handler);
    }

    /**
     * Run the application
     *
     * If no request or response are provided, the method will use
     * ServerRequestFactory::fromGlobals to create a request instance, and
     * instantiate a default response instance.
     *
     * It retrieves the default delegate using getDefaultHandler(), and
     * uses that to process itself.
     *
     * Once it has processed itself, it emits the returned response using the
     * composed emitter.
     */
    public function run(ServerRequestInterface $request = null, ResponseInterface $response = null) : void
    {
        try {
            $request = $request ?: ServerRequestFactory::fromGlobals();
        } catch (InvalidArgumentException | UnexpectedValueException $e) {
            // Unable to parse uploaded files | Invalid request method
            $this->emitMarshalServerRequestException($e);
            return;
        }

        $response = $this->handle(
            $request->withAttribute('originalResponse', $response ?: new Response())
        );

        $emitter = $this->getEmitter();
        $emitter->emit($response);
    }

    /**
     * @param string|array|callable|MiddlewareInterface $middleware Middleware
     *     (or middleware service name) to pipe to the application.
     * @throws Exception\InvalidMiddlewareException if the middleware provided is
     *     none of the specified types.
     */
    public function pipe($middleware) : void
    {
        $this->pipeline->pipe($this->middlewareFactory->prepare($middleware));
    }

    /**
     * Add a route for the route middleware to match.
     *
     * Accepts either a Router\Route instance, or a combination of a path and
     * middleware, and optionally the HTTP methods allowed.
     *
     * @param string|array|callable|MiddlewareInterface $middleware Middleware
     *     (or middleware service name) to associate with route.
     * @param null|array $methods HTTP method to accept; null indicates any.
     * @param null|string $name The name of the route.
     * @throws Exception\InvalidArgumentException if $path is not a Router\Route AND middleware is null.
     * @throws Exception\InvalidMiddlewareException if $middleware is neither a
     *     string nor a MiddlewareInterface instance.
     */
    public function route(string $path, $middleware, array $methods = null, string $name = null) : Router\Route
    {
        $this->checkForDuplicateRoute($path, $methods);

        $methods    = null === $methods ? Router\Route::HTTP_METHOD_ANY : $methods;
        $middleware = $this->middlewareFactory->prepare($middleware);
        $route      = new Router\Route($path, $middleware, $methods, $name);

        $this->routes[] = $route;
        $this->router->addRoute($route);

        return $route;
    }

    /**
     * @param string|array|callable|MiddlewareInterface $middleware Middleware
     *     (or middleware service name) to associate with route.
     * @param null|string $name The name of the route.
     */
    public function get(string $path, $middleware, string $name = null) : Router\Route
    {
        return $this->route($path, $middleware, ['GET'], $name);
    }

    /**
     * @param string|array|callable|MiddlewareInterface $middleware Middleware
     *     (or middleware service name) to associate with route.
     * @param null|string $name The name of the route.
     */
    public function post(string $path, $middleware, $name = null) : Router\Route
    {
        return $this->route($path, $middleware, ['POST'], $name);
    }

    /**
     * @param string|array|callable|MiddlewareInterface $middleware Middleware
     *     (or middleware service name) to associate with route.
     * @param null|string $name The name of the route.
     */
    public function put(string $path, $middleware, string $name = null) : Router\Route
    {
        return $this->route($path, $middleware, ['PUT'], $name);
    }

    /**
     * @param string|array|callable|MiddlewareInterface $middleware Middleware
     *     (or middleware service name) to associate with route.
     * @param null|string $name The name of the route.
     */
    public function patch(string $path, $middleware, string $name = null) : Router\Route
    {
        return $this->route($path, $middleware, ['PATCH'], $name);
    }

    /**
     * @param string|array|callable|MiddlewareInterface $middleware Middleware
     *     (or middleware service name) to associate with route.
     * @param null|string $name The name of the route.
     */
    public function delete(string $path, $middleware, string $name = null) : Router\Route
    {
        return $this->route($path, $middleware, ['DELETE'], $name);
    }

    /**
     * @param string|array|callable|MiddlewareInterface $middleware Middleware
     *     (or middleware service name) to associate with route.
     * @param null|string $name The name of the route.
     */
    public function any(string $path, $middleware, string $name = null) : Router\Route
    {
        return $this->route($path, $middleware, null, $name);
    }

    /**
     * Retrieve all directly registered routes with the application.
     *
     * @return Router\Route[]
     */
    public function getRoutes() : array
    {
        return $this->routes;
    }

    public function getMiddlewareFactory() : MiddlewareFactory
    {
        return $this->middlewareFactory;
    }

    /**
     * Retrieve the IoC container.
     *
     * If no IoC container is registered, we raise an exception.
     *
     * @throws Exception\ContainerNotRegisteredException
     */
    public function getContainer() : ContainerInterface
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
     * - If a container is composed, and it has the 'Zend\Expressive\Handler\DefaultHandler'
     *   service, pulls that service, assigns it, and returns it.
     * - If no container is composed, creates an instance of Handler\NotFoundHandler
     *   using the current response prototype only (i.e., no templating).
     */
    public function getDefaultHandler() : RequestHandlerInterface
    {
        if ($this->defaultHandler) {
            return $this->defaultHandler;
        }

        if ($this->container && $this->container->has('Zend\Expressive\Handler\DefaultHandler')) {
            $this->defaultHandler = $this->container->get('Zend\Expressive\Handler\DefaultHandler');
            return $this->defaultHandler;
        }

        if ($this->container) {
            $factory = new Container\NotFoundHandlerFactory();
            $this->defaultHandler = $factory($this->container);
            return $this->defaultHandler;
        }

        $this->defaultHandler = new Handler\NotFoundHandler($this->responsePrototype);
        return $this->defaultHandler;
    }

    /**
     * Retrieve an emitter to use during run().
     *
     * If none was registered during instantiation, this will lazy-load an
     * EmitterStack composing an SapiEmitter instance.
     */
    public function getEmitter() : EmitterInterface
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
     * @throws Exception\DuplicateRouteException on duplicate route detection.
     */
    private function checkForDuplicateRoute(string $path, array $methods = null) : void
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

        if (! empty($matches)) {
            throw new Exception\DuplicateRouteException(
                'Duplicate route detected; same name or path, and one or more HTTP methods intersect'
            );
        }
    }

    private function emitMarshalServerRequestException(Throwable $exception) : void
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
