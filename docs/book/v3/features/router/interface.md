# Routing Interface

Expressive defines `Zend\Expressive\Router\RouterInterface`, which is used by
the `Zend\Expressive\Router\RouteMiddleware` &mdash; as well as the
`Zend\Expressive\Router\RouteCollector` consumed by
`Zend\Expressive\Application` &mdash; in order to provide dynamic routing
capabilities to middleware. The interface serves as an abstraction to allow
routers with varying capabilities to be used with an application.

The interface is defined as follows:

```php
namespace Zend\Expressive\Router;

use Psr\Http\Message\ServerRequestInterface as Request;

/**
 * Interface defining required router capabilities.
 */
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
     * @throws Exception\RuntimeException when called after match() or
     *     generateUri() have been called.
     */
    public function addRoute(Route $route) : void;

    /**
     * Match a request against the known routes.
     *
     * Implementations will aggregate required information from the provided
     * request instance, and pass them to the underlying router implementation;
     * when done, they will then marshal a `RouteResult` instance indicating
     * the results of the matching operation and return it to the caller.
     */
    public function match(Request $request) : RouteResult;

    /**
     * Generate a URI from the named route.
     *
     * Takes the named route and any substitutions, and attempts to generate a
     * URI from it. Additional router-dependent options may be passed.
     *
     * The URI generated MUST NOT be escaped. If you wish to escape any part of
     * the URI, this should be performed afterwards; consider passing the URI
     * to league/uri to encode it.
     *
     * @see https://github.com/auraphp/Aura.Router/blob/3.x/docs/generating-paths.md
     * @see https://docs.zendframework.com/zend-router/routing/
     * @throws Exception\RuntimeException if unable to generate the given URI.
     */
    public function generateUri(string $name, array $substitutions = [], array $options = []) : string;
}
```

Developers may create and use their own implementations. We recommend
registering your implementation as the service
`Zend\Expressive\Router\RouterInterface` in your container to ensure other
factories provided by zend-expressive will receive your custom service.

Implementors should also read the following sections detailing the `Route` and
`RouteResult` classes, to ensure that their implementations interoperate
correctly.

## Routes

Routes are defined via `Zend\Expressive\Router\Route`, and aggregate the
following information:

- Path to match.
- Middleware to use when the route is matched. The value **must** implement
  `Psr\Http\Server\MiddlewareInterface`.
- HTTP methods allowed for the route; if none are provided, all are assumed.
- Optionally, a name by which to reference the route.

The `Route` class has the following signature:

```php
namespace Zend\Expressive\Router;

use Fig\Http\Message\RequestMethodInterface as RequestMethod;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

class Route implements MiddlewareInterface
{
    public const HTTP_METHOD_ANY = null;
    public const HTTP_METHOD_SEPARATOR = ':';

    /**
     * @param string $path Path to match.
     * @param MiddlewareInterface $middleware Middleware to use when this route is matched.
     * @param null|string[] $methods Allowed HTTP methods; defaults to HTTP_METHOD_ANY.
     * @param null|string $name the route name
     */
    public function __construct(
        string $path,
        MiddlewareInterface $middleware,
        array $methods = self::HTTP_METHOD_ANY,
        string $name = null
    );

    /**
     * Proxies to the middleware composed during instantiation.
     */
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler) : ResponseInterface;

    public function getPath() : string;

    /**
     * Set the route name.
     */
    public function setName(string $name) : void;

    public function getName() : string;

    public function getMiddleware() : MiddlewareInterface;

    /**
     * @return null|string[] Returns HTTP_METHOD_ANY or array of allowed methods.
     */
    public function getAllowedMethods() : ?array;

    /**
     * Indicate whether the specified method is allowed by the route.
     *
     * @param string $method HTTP method to test.
     */
    public function allowsMethod(string $method) : bool;

    public function setOptions(array $options) : void;

    public function getOptions() : array;
}
```

Typically, developers will use the `route()` method of either
`Zend\Expressive\Router\PathBasedRoutingMiddleware` or
`Zend\Expressive\Application` (or one of the HTTP-specific routing methods of
either class) to create routes, and will not need to interact with `Route`
instances.  Additionally, when working with `RouteResult` instances, you may
pull the `Route` instance from that in order to obtain data about the matched
route.

## Matching and RouteResults

Internally, routing middleware calls on `RouterInterface::match()`,
passing it the current request instance. This allows implementations to pull
what they may need from the request in order to perform their routing logic; for
example, they may need the request method, the URI path, the value of the
`HTTPS` server variable, etc.

Implementations are expected to return a `Zend\Expressive\Router\RouteResult`
instance, which is then injected as a request attribute under the name
`Zend\Expressive\Router\RouteResult` when passing processing of the request to
the provided handler. Additionally, in the event of success, it will pull any
matched parameters from the result and inject them as request attributes as
well.

Dispatch middleware can then retrieve the route result from the request and
process it, passing the route result its own request and handler.

The zend-expressive-router package also provides a number of middleware geared
towards handling failed results which can be placed between routing and dispatch
middleware:

- `Zend\Expressive\Router\Middleware\MethodNotAllowedMiddleware` checks to see
  if the route failures was due to the HTTP method, and, if so, return a 405
  response with an appropriate `Allow` header.
  ([read more](../middleware/method-not-allowed-middleware.md))

- `Zend\Expressive\Router\Middleware\ImplicitHeadMiddleware` checks to see if a
  routing failure was due to a route match using a `HEAD` request, and will then
  dispatch the appropriate route via `GET` request, and inject an empty body
  into the returned response.
  ([read more](../middleware/implicit-methods-middleware.md#implicitheadmiddleware))

- `Zend\Expressive\Router\Middleware\ImplicitOptionsMiddleware` checks to see if a
  routing failure was due to a route match using a `OPTIONS` request; if so, it
  will return a 200 response with an appropriate `Allow `header.
  ([read more](../middleware/implicit-methods-middleware.md#implicitoptionsmiddleware))

The `RouteResult` signature is as follows:

```php
namespace Zend\Expressive\Router;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

class RouteResult implements MiddlewareInterface
{
    /**
     * Create an instance representing a route succes from the matching route.
     *
     * @param array $params Parameters associated with the matched route, if any.
     */
    public static function fromRoute(Route $route, array $params = []) : self;

    /**
     * Create an instance representing a route failure.
     *
     * @param null|array $methods HTTP methods allowed for the current URI, if any.
     *     null is equivalent to allowing any HTTP method; empty array means none.
     */
    public static function fromRouteFailure(?array $methods) : self;

    /**
     * Process the result as middleware.
     *
     * If the result represents a failure, it passes handling to the handler.
     *
     * Otherwise, it processes the composed middleware using the provide request
     * and handler.
     */
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler) : ResponseInterface;

    /**
     * Does the result represent successful routing?
     */
    public function isSuccess() : bool;

    /**
     * Retrieve the route that resulted in the route match.
     *
     * @return false|null|Route false if representing a routing failure;
     *     null if not created via fromRoute(); Route instance otherwise.
     */
    public function getMatchedRoute();

    /**
     * Retrieve the matched route name, if possible.
     *
     * If this result represents a failure, return false; otherwise, return the
     * matched route name.
     *
     * @return false|string
     */
    public function getMatchedRouteName();

    /**
     * Returns the matched params.
     */
    public function getMatchedParams() : array;

    /**
     * Is this a routing failure result?
     */
    public function isFailure() : bool;

    /**
     * Does the result represent failure to route due to HTTP method?
     */
    public function isMethodFailure() : bool;

    /**
     * Retrieve the allowed methods for the route failure.
     *
     * @return string[] HTTP methods allowed
     */
    public function getAllowedMethods() : array;
}
```

Typically, only those implementing routers will interact with this class.
