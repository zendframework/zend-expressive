# Routing Interface

Expressive defines `Zend\Expressive\Router\RouterInterface`, which can be
injected into and consumed by `Zend\Expressive\Application` in order to provide
dynamic routing capabilities to middleware. The interface serves as an
abstraction to allow routers with varying capabilities to be used with an
application.

The interface is defined as follows:

```php
namespace Zend\Expressive\Router;

use Psr\Http\Message\ServerRequestInterface as Request;

interface RouterInterface
{
    /**
     * Add a route.
     *
     * This method adds a route against which the underlying implementation may
     * match. Implementations MUST aggregate route instances, but MUST NOT use
     * the details to inject the underlying router until `match()` and/or
     * `generateUri()` is called.  This is required to allow consumers to
     * modify route instances before matching (e.g., to provide route options,
     * inject a name, etc.).
     *
     * The method MUST raise Exception\RuntimeException if called after either `match()`
     * or `generateUri()` have already been called, to ensure integrity of the
     * router between invocations of either of those methods.
     *
     * @param Route $route
     * @throws Exception\RuntimeException when called after match() or
     *     generateUri() have been called.
     */
    public function addRoute(Route $route);

    /**
     * Match a request against the known routes.
     *
     * Implementations will aggregate required information from the provided
     * request instance, and pass them to the underlying router implementation;
     * when done, they will then marshal a `RouteResult` instance indicating
     * the results of the matching operation and return it to the caller.
     *
     * @param  Request $request
     * @return RouteResult
     */
    public function match(Request $request);

    /**
     * Generate a URI from the named route.
     *
     * Takes the named route and any substitutions, and attempts to generate a
     * URI from it.
     *
     * @see https://github.com/auraphp/Aura.Router#generating-a-route-path
     * @see http://framework.zend.com/manual/current/en/modules/zend.mvc.routing.html
     * @param string $name
     * @param array $substitutions
     * @param array $options Since zend-expressive-router 2.0/zend-expressive 2.0;
     *     not defined in earlier versions.
     * @return string
     * @throws Exception\RuntimeException if unable to generate the given URI.
     */
    public function generateUri($name, array $substitutions = [], array $options = []);
}
```

Developers may create and use their own implementations. We recommend
registering your implementation as the service
`Zend\Expressive\Router\RouterInterface` in your container to ensure other
factories provided by zend-expressive will receive your custom service.

Implementors should also read the following sections detailing the `Route` and
`RouteResult` classes, to ensure that their implementations interoperate
correctly.

> ### generateUri() signature change in 2.0
>
> Prior to zendframework/zend-expressive-router 2.0.0, the signature of
> `generateUri()` was:
>
> ```php
> public function generateUri(
>     string $name,
>     array $substitutions = []
> ) : string
> ```
> 
> If you are targeting that version, you may still provide the `$options`
> argument, but it will not be invoked.

## Routes

Routes are defined via `Zend\Expressive\Router\Route`, and aggregate the
following information:

- Path to match.
- Middleware to use when the route is matched. This may be a callable or a
  service name resolving to middleware.
- HTTP methods allowed for the route; if none are provided, all are assumed.
- Optionally, a name by which to reference the route.

The `Route` class has the following signature:

```php
namespace Zend\Expressive\Router;

use Interop\Http\ServerMiddleware\MiddlewareInterface;

class Route
{
    const HTTP_METHOD_ANY = 0xff;
    const HTTP_METHOD_SEPARATOR = ':';

    /**
     * @param string $path Path to match.
     * @param string|callable|MiddlewareInterface $middleware Middleware to use
     *     when this route is matched. MiddlewareInterface is supported starting
     *     in zend-expressive-router 2.1.0.
     * @param int|array Allowed HTTP methods; defaults to HTTP_METHOD_ANY.
     * @param string|null $name the route name
     * @throws Exception\InvalidArgumentException for invalid path type.
     * @throws Exception\InvalidArgumentException for invalid middleware type.
     * @throws Exception\InvalidArgumentException for any invalid HTTP method names.
     */
    public function __construct($path, $middleware, $methods = self::HTTP_METHOD_ANY, $name = null);

    /**
     * @return string
     */
    public function getPath();

    /**
     * Set the route name.
     *
     * @param string $name
     */
    public function setName($name);

    /**
     * @return string
     */
    public function getName();

    /**
     * @return string|callable|MiddlewareInterface MiddlewareInterface is supported
     *     starting in zend-expressive-router 2.1.0.
     */
    public function getMiddleware();

    /**
     * @return int|string[] Returns HTTP_METHOD_ANY or array of allowed methods.
     */
    public function getAllowedMethods();

    /**
     * Indicate whether the specified method is allowed by the route.
     *
     * @param string $method HTTP method to test.
     * @return bool
     */
    public function allowsMethod($method);

    /**
     * @param array $options
     */
    public function setOptions(array $options);

    /**
     * @return array
     */
    public function getOptions();
}
```

Typically, developers will use `Zend\Expressive\Application::route()` (or one of
the HTTP-specific routing methods) to create routes, and will not need to
interact with `Route` instances. However, that method can *also* accept `Route`
instances, allowing more flexibility in defining and configuring them;
additionally, when working with `RouteResult` instances, you may pull the
`Route` instance from that in order to obtain data about the matched route.

## Matching and RouteResults

Internally, `Zend\Expressive\Application` calls on `RouterInterface::match()`,
passing it the current request instance. This allows implementations to pull
what they may need from the request in order to perform their routing logic; for
example, they may need the request method, the URI path, the value of the
`HTTPS` server variable, etc.

Implementations are expected to return a `Zend\Expressive\Router\RouteResult`
instance, which the routing middleware then uses to determine if routing
succeeded. In the event of success, it will pull any matched parameters from the
result and inject them as request attributes, and then pull the matched
middleware and execute it. In the case of failure, it will determine if the
failure was due to inability to match, or usage of a disallowed HTTP method; in
the former case, it proceeds to the next middleware in the stack, and in the
latter, returns a 405 response.

The `RouteResult` signature is as follows:

```php
namespace Zend\Expressive\Router;

class RouteResult
{
    /**
     * Create an instance representing a route success.
     *
     * This method is removed starting in zend-expressive-router 2.0; use
     * fromRoute() instead.
     *
     * @param string $name Name of matched route.
     * @param callable|string $middleware Middleware associated with the
     *     matched route.
     * @param array $params Parameters associated with the matched route.
     * @return static
     */
    public static function fromRouteMatch($name, $middleware, array $params);

    /**
     * Create an instance representing a route success from a Route instance.
     *
     * This method was introduced in zend-expressive-router 1.3, and should
     * be used for generating an instance indicating a route success from
     * that version forward.
     *
     * @param Route $route
     * @param array $params Parameters associated with the matched route.
     * @return static
     */
    public static function fromRoute(Route $route, array $params = []);

    /**
     * Create an instance representing a route failure.
     *
     * @param null|int|array $methods HTTP methods allowed for the current URI, if any
     * @return static
     */
    public static function fromRouteFailure($methods = null);

    /**
     * Does the result represent successful routing?
     *
     * @return bool
     */
    public function isSuccess();

    /**
     * Retrieve the matched route, if possible.
     *
     * If this result represents a failure, return false; otherwise, return the
     * matched route instance.
     *
     * Available starting in zend-expressive-router 1.3.0.
     *
     * @return Route
     */
    public function getMatchedRoute();

    /**
     * Retrieve the matched route name, if possible.
     *
     * If this result represents a failure, return false; otherwise, return the
     * matched route name.
     *
     * @return string
     */
    public function getMatchedRouteName();

    /**
     * Retrieve the matched middleware, if possible.
     *
     * @return false|callable|string Returns false if the result represents a
     *     failure; otherwise, a callable or a string service name.
     */
    public function getMatchedMiddleware();

    /**
     * Returns the matched params.
     *
     * Guaranteed to return an array, even if it is simply empty.
     *
     * @return array
     */
    public function getMatchedParams();

    /**
     * Is this a routing failure result?
     *
     * @return bool
     */
    public function isFailure();

    /**
     * Does the result represent failure to route due to HTTP method?
     *
     * @return bool
     */
    public function isMethodFailure();

    /**
     * Retrieve the allowed methods for the route failure.
     *
     * @return string[] HTTP methods allowed
     */
    public function getAllowedMethods();
}
```

Typically, only those implementing routers will interact with this class.
