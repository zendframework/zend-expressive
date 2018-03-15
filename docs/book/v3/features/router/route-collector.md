# The Route Collector

`Zend\Expressive\Router\RouteCollector` is a class that exists to help you
_create_ path-based routes, while simultaneously injecting them into a router
instance.

It composes a `Zend\Expressive\Router\RouterInterface` instance via its
constructor, and provides the following methods:

- `route()`
- `any()`
- `delete()`
- `get()`
- `patch()`
- `post()`
- `put()`

These methods allow you to add routes to the underlying router. The last five
all reference the HTTP method the generated route will answer to, and each have
the same signature:

```php
public function {method}(
    string $path,
    Psr\Http\Server\MiddlewareInterface $middleware,
    string $name = null
) : Zend\Expressive\Router\Route
```

The `any()` method has the same signature, but indicates that it will answer to
_any_ HTTP method.

Finally, `route()` has the following signature:

```php
public function route(
    string $path,
    Psr\Http\Server\MiddlewareInterface $middleware,
    array $methods = null,
    string $name = null
) : Zend\Expressive\Router\Route
```

A `null` value for the `$methods` indicates any HTTP method is allowed.

`Zend\Expressive\Application` composes an instance of this class
and proxies to it when any of the above methods are called.
`Zend\Expressive\Router\Middleware\RouteMiddleware`, by default, composes the
same router instance, allowing it to honor the definitions created.
