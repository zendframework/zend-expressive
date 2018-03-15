# Routing and Dispatch Middleware

Within Expressive, we differentiate _routing_ from _dispatching_. _Routing_ is the
act of matching a request to middleware; this typically involves inspecting the
path and HTTP method used, but may also consider aspects such as headers,
protocol, and more. _Dispatching_ occurs _after_ routing; it examines the
results of routing, processing the middleware matched.

Expressive goes so far as to separate the two actions into _separate
middleware_. This is done to allow additional middleware to execute between
them. For example, as you'll learn in the next two chapters, we can look for
routing failures and answer `HEAD` and `OPTIONS` requests, or return a `405
Method Not Allowed` status without ever hitting the dispatch middleware. When
you [read about the UrlHelper](../helpers/url-helper.md), you'll discover it has
associated middleware that can receive the results of routing in order to
facilitate URI generation. Keeping the two actions separated as distinct
middleware provides a ton of power and flexibility in building your
applications.

We provide two middleware around these actions, each in the
`Zend\Expressive\Router\Middleware` namespace and provided by the
zendframework/zend-expressive-router package:

- `RouteMiddleware`, which consumes a [router](../router/interface.md) in order
  to route a request.

- `DispatchMiddleware`, which dispatches the route result.

## RouteMiddleware

`Zend\Expressive\Router\Middleware\RouteMiddleware` receives a
`Zend\Expressive\Router\RouterInterface` instance to its constructor. When it is
processed, it passes the request to the router in order to receive a
`Zend\Expressive\Router\RouteResult` instance.

When the result indicates a match, the middleware creates an updated request
instance that includes each of the route match parameters as attributes.

Regardless of the result, it will create an updated request instance that
includes the result as the attribute `Zend\Expressive\Router\RouteResult`.

It then invokes the handler; all later middleware can then access the route
result using:

```php
$result = $request->getAttribute(\Zend\Expressive\Router\RouteResult::class);
```

## DispatchMiddleware

`Zend\Expressive\Router\Middleware\DispatchMiddleware` defines only the
`process()` method required by the PSR-15 `MiddlewareInterface`. Internally, it:

- checks for a `RouteResult` in the request, AND
- processes it, passing the request and handler.

If there is no `RouteResult`, it delegates to the handler without doing anything
else.
