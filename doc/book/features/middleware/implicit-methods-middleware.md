# ImplicitHeadMiddleware and ImplicitOptionsMiddleware

Starting with version 2.0, Expressive offers middleware for implicitly
supporting `HEAD` and `OPTIONS` requests. The HTTP/1.1 specifications indicate
that all server implementations _must_ support `HEAD` requests for any given
URI, and that they _should_ support `OPTIONS` requests. To make this possible,
we have added features to our routing layer, and middleware that can detect
_implicit_  support for these methods (i.e., the route was not registered
_explicitly_ with the method).

## ImplicitHeadMiddleware

`Zend\Expressive\Middleware\ImplicitHeadMiddleware` provides support for
handling `HEAD` requests to routed middleware when the route does not expliclity
allow for the method. It should be registered _between_ the routing and dispatch
middleware.

By default, it can be instantiated with no extra arguments. However, you _may_
provide a response instance to use by default to the constructor if you need to
craft special headers, status code, etc.

Register the dependency via `dependencies` configuration:

```php
use Zend\Expressive\Middleware\ImplicitHeadMiddleware;

return [
    'dependencies' => [
        'invokables' => [
            ImplicitHeadMiddleware::class => ImplicitHeadMiddleware::class,
        ],

        // or, if you have defined a factory to inject a response:
        'factories' => [
            ImplicitHeadMiddleware::class => \Your\ImplicitHeadMiddlewareFactory::class,
        ],
    ],
];
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
programmatic pipeline, or if you used the Expressive 2.0 skeleton or later, this
middleware is likely already in your pipeline, as is a dependency entry.)

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

## ImplicitOptionsMiddleware

`Zend\Expressive\Middleware\ImplicitOptionsMiddleware` provides support for
handling `OPTIONS` requests to routed middleware when the route does not
expliclity allow for the method. Like the `ImplicitHeadMiddleware`, it should be
registered _between_ the routing and dispatch middleware.

By default, it can be instantiated with no extra arguments. However, you _may_
provide a response prototype instance to use by default to the constructor if
you need to craft special headers, status code, etc.

Register the dependency via `dependencies` configuration:

```php
use Zend\Expressive\Middleware\ImplicitOptionsMiddleware;

return [
    'dependencies' => [
        'invokables' => [
            ImplicitOptionsMiddleware::class => ImplicitOptionsMiddleware::class,
        ],

        // or, if you have defined a factory to inject a response:
        'factories' => [
            ImplicitOptionsMiddleware::class => \Your\ImplicitOptionsMiddlewareFactory::class,
        ],
    ],
];
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
programmatic pipeline, or if you used the Expressive 2.0 skeleton or later, this
middleware is likely already in your pipeline, as is a dependency entry.)

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
