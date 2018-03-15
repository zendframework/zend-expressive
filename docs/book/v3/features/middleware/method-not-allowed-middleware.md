# Returning Method Not Allowed

When the path matches, but the HTTP method does not, your application should
return a `405 Method Not Allowed` status in response.

To enable that functionality, we provide
`Zend\Expressive\Router\Middleware\MethodNotAllowedMiddleware` via the
zend-expressive-router package.

This middleware triggers when the following conditions occur:

- The request composes a `RouteResult` attribute (i.e., routing middleware has
  completed), AND
- the route result indicates a routing failure due to HTTP method used (i.e.,
  `RouteResult::isMethodFailure()` returns `true`).

When these conditions occur, the middleware will generate a response:

- with a `405 Method Not Allowed` status, AND
- an `Allow` header indicating the HTTP methods allowed.

Pipe the middleware after the routing middleware; if using one or more of the
[implicit methods middleware](implicit-methods-middleware.md), this middleware
**must** be piped after them, as it will respond for _any_ HTTP method!

```php
$app->pipe(RouteMiddleware::class);
$app->pipe(ImplicitHeadMiddleware::class);
$app->pipe(ImplicitOptionsMiddleware::class);
$app->pipe(MethodNotAllowedMiddleware::class);
// ...
$app->pipe(DispatchMiddleware::class);
```

(Note: if you used the Expressive skeleton, this middleware is likely already in
your pipeline.)
