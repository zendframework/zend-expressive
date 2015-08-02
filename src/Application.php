<?php
namespace Zend\Expressive;

use BadMethodCallException;
use DomainException;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Zend\Diactoros\Response\EmitterInterface;
use Zend\Diactoros\Response\SapiEmitter;
use Zend\Diactoros\ServerRequestFactory;
use Zend\Stratigility\FinalHandler;
use Zend\Stratigility\MiddlewarePipe;

class Application extends MiddlewarePipe
{
    /**
     * @var Dispatcher
     */
    private $dispatcher;

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
     * @var Route[]
     */
    private $routes = [];

    /**
     * @param Dispatcher $dispatcher
     * @param null|callable $finalHandler Final handler to use when $out is not
     *     provided on invocation.
     * @param null|EmitterInterface $emitter Emitter to use when `run()` is
     *     invoked.
     */
    public function __construct(Dispatcher $dispatcher, callable $finalHandler = null, EmitterInterface $emitter = null)
    {
        parent::__construct();
        $this->dispatcher   = $dispatcher;
        $this->finalHandler = $finalHandler;
        $this->emitter      = $emitter;
        $this->pipe($dispatcher);
    }

    /**
     * Overload middleware invocation.
     *
     * If the dispatcher is in the pipeline, this method will inject the routes
     * it has aggregated prior to self invocation.
     *
     * If $out is not provided, uses the result of `getFinalHandler()`.
     *
     * @param Request $request
     * @param Response $response
     * @param callable|null $out
     * @return Response
     */
    public function __invoke(Request $request, Response $response, callable $out = null)
    {
        $this->injectRoutes();
        if (null === $out) {
            $out = $this->getFinalHandler();
        }
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
        return call_user_func_array([$this, 'route'], $args);
    }

    /**
     * @param string|Router\Route $path
     * @param callable|string $middleware Middleware (or middleware service name) to associate with route.
     * @param null|array $methods HTTP method to accept; null indicates any.
     * @return Router\Route
     * @throws InvalidArgumentException if $path is not a Router\Route AND middleware is null.
     */
    public function route($path, $middleware = null, array $methods = null)
    {
        if (! $path instanceof Router\Route && null === $middleware) {
            throw new InvalidArgumentException(sprintf(
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
     * @param null|Request $request
     * @param null|Response $response
     */
    public function run(Request $request = null, Response $response = null)
    {
        $request  = $request ?: ServerRequestFactory::fromGlobals();
        $response = $response ?: new Response();

        $response = $this($request, $response);

        $emitter = $this->getEmitter();
        $emitter->emit($response);
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
     * Inject routes into the router associated with the dispatcher.
     */
    private function injectRoutes()
    {
        $router = $this->dispatcher->getRouter();
        array_walk($this->routes, function ($route) use ($router) {
            if ($route->isInjected()) {
                return;
            }
            $router->addRoute($route);
            $route->inject();
        });
    }
}
