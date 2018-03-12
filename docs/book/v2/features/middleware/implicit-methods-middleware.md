# ImplicitHeadMiddleware and ImplicitOptionsMiddleware

Expressive offers middleware for implicitly supporting `HEAD` and `OPTIONS`
requests. The HTTP/1.1 specifications indicate that all server implementations
_must_ support `HEAD` requests for any given URI, and that they _should_ support
`OPTIONS` requests. To make this possible, we have added features to our routing
layer, and middleware that can detect _implicit_  support for these methods
(i.e., the route was not registered _explicitly_ with the method).

> ## Versions prior to 2.2
>
> If you are using Expressive versions earlier than 2.2, you may define a
> `Zend\Expressive\Middleware\ImplicitHeadMiddleware` or
> `Zend\Expressive\Middleware\ImplicitOptionsMiddleware` service under the
> `invokables` service configuration.
> 
> However, starting in version 2.2, these classes are deprecated in favor of their
> equivalents that are now offered in the zend-expressive-router v2.4+ releases,
> under the namespace `Zend\Expressive\Router\Middleware`.
> 
> The documentation here has been updated to reflect usage under Expressive 2.2+.

## ImplicitHeadMiddleware

`Zend\Expressive\Middleware\ImplicitHeadMiddleware` provides support for
handling `HEAD` requests to routed middleware when the route does not expliclity
allow for the method. It should be registered _between_ the routing and dispatch
middleware.

To use it, it must first be registered with your container. The easiest way to
do that is to register the zend-expressive-router `ConfigProvider` in your
`config/config.php`:

```php
$aggregator = new ConfigAggregator([
    \Zend\Expressive\Router\ConfigProvider::class,
```

Alternately, add the following dependency configuration in one of your
`config/autoload/` configuration files or a `ConfigProvider` class:

```php
use Zend\Expressive\Router\Middleware\ImplicitHeadMiddleware;
use Zend\Expressive\Router\Middleware\ImplicitHeadMiddlewareFactory;

'dependencies' => [
    'factories' => [
        ImplicitHeadMiddleware::class => ImplicitHeadMiddlewareFactory::class,
    ],
],
```

Within your application pipeline, add the middleware between the routing and
dispatch middleware:

```php
$app->pipeRoutingMiddleware();
$app->pipe(ImplicitHeadMiddleware::class);
// ...
$app->pipeDispatchMiddleware();
```

(Note: if you used the `expressive-pipeline-from-config` tool to create your
programmatic pipeline, or if you used the Expressive skeleton, this middleware
is likely already in your pipeline, as is a dependency entry.)

When in place, it will do the following:

- If the request method is `HEAD`, AND
- the request composes a `RouteResult` attribute, AND
- the route result composes a `Route` instance, AND
- the route returns true for the `implicitHead()` method, THEN
- the middleware will return a response.

In all other cases, it returns the result of delegating to the next middleware
layer.

When `implicitHead()` is matched, one of two things may occur. First, if the
route does not support the `GET` method, then the middleware returns the
composed response (either the one injected at instantiation, or an empty
instance). However, if `GET` is supported, it will dispatch the next layer, but
with a `GET` request instead of `HEAD`; additionally, it will inject the
returned response with an empty response body before returning it.

### Detecting forwarded requests

- Since 2.1.0

When the next layer is dispatched, the request will have an additional
attribute, `Zend\Expressive\Middleware\ImplicitHeadMiddleware::FORWARDED_HTTP_METHOD_ATTRIBUTE`,
with a value of `HEAD`. As such, you can check for this value in order to vary
the headers returned if desired.

## ImplicitOptionsMiddleware

`Zend\Expressive\Router\Middleware\ImplicitOptionsMiddleware` provides support for
handling `OPTIONS` requests to routed middleware when the route does not
expliclity allow for the method. Like the `ImplicitHeadMiddleware`, it should be
registered _between_ the routing and dispatch middleware.

To use it, it must first be registered with your container. The easiest way to
do that is to register the zend-expressive-router `ConfigProvider` in your
`config/config.php`:

```php
$aggregator = new ConfigAggregator([
    \Zend\Expressive\Router\ConfigProvider::class,
```

Alternately, add the following dependency configuration in one of your
`config/autoload/` configuration files or a `ConfigProvider` class:

```php
use Zend\Expressive\Router\Middleware\ImplicitOptionsMiddleware;
use Zend\Expressive\Router\Middleware\ImplicitOptionsMiddlewareFactory;

'dependencies' => [
    'factories' => [
        ImplicitOptionsMiddleware::class => ImplicitOptionsMiddlewareFactory::class,
    ],
],
```

Within your application pipeline, add the middleware between the routing and
dispatch middleware:

```php
$app->pipeRoutingMiddleware();
$app->pipe(ImplicitOptionsMiddleware::class);
// ...
$app->pipeDispatchMiddleware();
```

(Note: if you used the `expressive-pipeline-from-config` tool to create your
programmatic pipeline, or if you used the Expressive skeleton, this middleware
is likely already in your pipeline, as is a dependency entry.)

When in place, it will do the following:

- If the request method is `OPTIONS`, AND
- the request composes a `RouteResult` attribute, AND
- the route result composes a `Route` instance, AND
- the route returns true for the `implicitOptions()` method, THEN
- the middleware will return a response with an `Allow` header indicating
  methods the route allows.

In all other cases, it returns the result of delegating to the next middleware
layer.

One thing to note: the allowed methods reported by the route and/or route
result, and returned via the `Allow` header,  may vary based on router
implementation. In most cases, it should be an aggregate of all routes using the
same path specification; however, it *could* be only the methods supported
explicitly by the matched route.
