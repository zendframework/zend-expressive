<?php
namespace Zend\Expressive;

use BadMethodCallException;
use DomainException;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Zend\Stratigility\Dispatch\Dispatcher;
use Zend\Stratigility\MiddlewarePipe;

class Application extends MiddlewarePipe
{
    /**
     * @var Dispatcher
     */
    private $dispatcher;

    /**
     * @var boolean Whether or not the dispatcher has been added to the pipeline.
     */
    private $dispatcherInPipeline = false;

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
     */
    public function __construct(Dispatcher $dispatcher)
    {
        parent::__construct();
        $this->dispatcher = $dispatcher;
    }

    /**
     * Overload middleware invocation.
     *
     * If the dispatcher is in the pipeline, this method will inject the routes
     * it has aggregated prior to self invocation.
     *
     * @param Request $request
     * @param Response $response
     * @param callable|null $out
     * @return Response
     */
    public function __invoke(Request $request, Response $response, callable $out = null)
    {
        if ($this->dispatcherInPipeline) {
            $router = $this->dispatcher->getRouter();
            $router->injectRoutes($this->routes);
        }
        return parent::__invoke($request, $response, $out);
    }

    /**
     * @param string $method
     * @param array $args
     * @return Route
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
     * @param string|Route $path
     * @param callable|string $middleware Middleware (or middleware service name) to associate with route.
     * @param null|array $methods HTTP method to accept; null indicates any.
     * @return Route
     * @throws InvalidArgumentException if $path is not a Route AND middleware is null.
     */
    public function route($path, $middleware = null, array $methods = null)
    {
        if (! $path instanceof Route && null === $middleware) {
            throw new InvalidArgumentException(sprintf(
                '%s expects either a route argument, or a combination of a path and middleware arguments',
                __METHOD__
            ));
        }

        if ($path instanceof Route) {
            $route   = $path;
            $path    = $route->getPath();
            $methods = $route->getAllowedMethods();
        }

        $this->checkForDuplicateRoute($path, $methods);

        if (! isset($route)) {
            $route = new Route($path, $middleware);
            if (is_array($methods)) {
                $route->setAllowedMethods($methods);
            }
        }

        $this->routes[] = $route;
        $this->injectDispatcher();
        return $route;
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
            $methods = Route::HTTP_METHOD_ANY;
        }

        $matches = array_filter($this->routes, function ($route) use ($path, $methods) {
            if ($path !== $route->getPath()) {
                return false;
            }

            if ($methods === Route::HTTP_METHOD_ANY) {
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
     * Inject the dispatcher into the pipeline.
     *
     * But only if it hasn't been already.
     */
    private function injectDispatcher()
    {
        if ($this->dispatcherInPipeline) {
            return;
        }
        $this->pipe($this->dispatcher);
        $this->dispatcherInPipeline = true;
    }
}
