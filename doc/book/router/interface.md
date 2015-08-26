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
use Zend\Expressive\Exception;

interface RouterInterface
{
    /**
     * @param Route $route
     */
    public function addRoute(Route $route);

    /**
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
     * @return string
     * @throws Exception\RuntimeException if unable to generate the given URI.
     */
    public function generateUri($name, array $substitutions = []);
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
- Middleware to use when the route is matched. This may be a callable or a
  service name resolving to middleware.
- HTTP methods allowed for the route; if none are provided, all are assumed.
- Optionally, a name by which to reference the route.

The `Route` class has the following signature:

```php
namespace Zend\Expressive\Router;

use Zend\Expressive\Exception;

class Route
{
    const HTTP_METHOD_ANY = 0xff;
    const HTTP_METHOD_SEPARATOR = ':';

    /**
     * @param string $path Path to match.
     * @param string|callable $middleware Middleware to use when this route is matched.
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
     * @return string
     */
    public function getName();

    /**
     * @return string|callable
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
instances, allowing more flexibility in defining and configuring them.

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
     * Create an instance repesenting a route success.
     *
     * @param string $name Name of matched route.
     * @param callable|string $middleware Middleware associated with the
     *     matched route.
     * @param array $params Parameters associated with the matched route.
     * @return static
     */
    public static function fromRouteMatch($name, $middleware, array $params);

    /**
     * Create an instance repesenting a route failure.
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
     * Retreive the matched route name, if possible.
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
     * Guaranted to return an array, even if it is simply empty.
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
