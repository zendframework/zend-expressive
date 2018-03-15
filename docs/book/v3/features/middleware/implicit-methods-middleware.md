# ImplicitHeadMiddleware and ImplicitOptionsMiddleware

Expressive offers middleware for implicitly supporting `HEAD` and `OPTIONS`
requests. The HTTP/1.1 specifications indicate that all server implementations
_must_ support `HEAD` requests for any given URI, and that they _should_ support
`OPTIONS` requests. To make this possible, we have added features to our routing
layer, and middleware that can detect _implicit_ support for these methods
(i.e., the route was not registered _explicitly_ with the method).

Both middleware detailed here are provided in the zend-expressive-router
package.

## ImplicitHeadMiddleware

`Zend\Expressive\Router\Middleware\ImplicitHeadMiddleware` provides support for
handling `HEAD` requests to routed middleware when the route does not explicitly
allow for the method. It should be registered _between_ the routing and dispatch
middleware.

The zend-expressive-router package provides a factory for creating an instance,
and registers it by default via its configuration provider.

> If you want to provide a response instance with additional headers or a custom
> status code, you will need to provide your own factory.

Within your application pipeline, add the middleware between the routing and
dispatch middleware, generally immediately following the routing middleware:

```php
$app->pipe(RouteMiddleware::class);
$app->pipe(ImplicitHeadMiddleware::class);
// ...
$app->pipe(DispatchMiddleware::class);
```

(Note: if you used the Expressive skeleton, this middleware is likely already in
your pipeline.)

When in place, it will do the following:

- If the request method is `HEAD`, AND
- the request composes a `RouteResult` attribute, AND
- the route result indicates a routing failure due to HTTP method used, THEN
- the middleware will return a response.

In all other cases, it returns the result of delegating to the next middleware
layer.

When the middleware decides it can answer the request, one of two things may
occur. First, if the route does not support the `GET` method, then the
middleware returns an empty response.  However, if `GET` is supported, it will
dispatch the next layer, but with a `GET` request instead of `HEAD`;
additionally, it will inject the returned response with an empty response body
before returning it.

### Detecting forwarded requests

When the next layer is dispatched, the request will have an additional
attribute, `Zend\Expressive\Router\Middleware\ImplicitHeadMiddleware::FORWARDED_HTTP_METHOD_ATTRIBUTE`,
with a value of `HEAD`. As such, you can check for this value in order to vary
the headers returned if desired.

## ImplicitOptionsMiddleware

`Zend\Expressive\Router\Middleware\ImplicitOptionsMiddleware` provides support for
handling `OPTIONS` requests to routed middleware when the route does not
explicitly allow for the method. Like the `ImplicitHeadMiddleware`, it should be
registered _between_ the routing and dispatch middleware.

The zend-expressive-router package provides a factory for creating an instance,
and registers it by default via its configuration provider.

> If you want to provide a response instance with additional headers or a custom
> status code, you will need to provide your own factory.

Within your application pipeline, add the middleware between the routing and
dispatch middleware, generally immediately following the routing middleware or
`ImplicitHeadMiddleware`:

```php
$app->pipe(RouteMiddleware::class);
$app->pipe(ImplicitOptionsMiddleware::class);
// ...
$app->pipe(DispatchMiddleware::class);
```

(Note: if you used the Expressive skeleton, this middleware is likely already in
your pipeline.)

When in place, it will do the following:

- If the request method is `OPTIONS`, AND
- the request composes a `RouteResult` attribute, AND
- the route result indicates a routing failure due to HTTP method used, THEN
- the middleware will return a 200 response with an `Allow` header indicating
  methods the route allows.

In all other cases, it returns the result of delegating to the next middleware
layer.

One thing to note: the allowed methods reported by the route and/or route
result, and returned via the `Allow` header,  may vary based on router
implementation. In most cases, it should be an aggregate of all routes using the
same path specification; however, it *could* be only the methods supported
explicitly by the matched route.
