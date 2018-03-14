# Middleware Types

Expressive allows you to compose applications out of _pipeline_ and _routed_
middleware.

**Pipeline** middleware is middleware that defines the workflow of your
application. These generally run on every execution of the application, and
include such aspects as:

- Error handling
- Locale detection
- Session setup
- Authentication and authorization

**Routed** middleware is middleware that responds only to specific URI paths and
HTTP methods. As an example, you might want middleware that only responds to
HTTP POST requests to the path `/users`.

Expressive allows you to define middleware using any of the following:

- [PSR-15 middleware](https://www.php-fig.org/psr/psr-15/) instances.
- [PSR-15 request handler](https://www.php-fig.org/psr/psr-15/) instances.
- Service names resolving to one of the above middleware types.
- Callable middleware that implements the PSR-15 `MiddlewareInterface` signature.
- Middleware pipelines expressed as arrays of the above middleware types.

## PSR-15 middleware

The PSR-15 specification covers HTTP server middleware and request handlers that
consume [PSR-7](http://www.php-fig.org/psr/psr-7) HTTP messages. Expressive
accepts both middleware that implements the `MiddlewareInterface` and request
handlers that implement `RequestHandlerInterface`. As an example:

```php
use Interop\Http\Server\MiddlewareInterface;
use Interop\Http\Server\RequestHandlerInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

class SomeMiddleware implements MiddlewareInterface
{
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler) : ResponseInterface
    {
        // do something and return a response, or
        // delegate to another request handler capable
        // of returning a response via:
        //
        // return $handler->handle($request);
    }
}
```

You could also implement such middleware via an anonymous class.

## Callable middleware

Sometimes you may not want to create a class for one-off middleware. As such,
Expressive allows you to provide a PHP callable that uses the same signature as
`Psr\Http\Server\MiddlewareInterface`:

```php
use Psr\Http\Server\RequestHandlerInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

function (ServerRequestInterface $request, RequestHandlerInterface $handler) : ResponseInterface
{
    // do something and return a response, or
    // delegate to another request handler capable
    // of returning a response via:
    //
    // return $handler->handle($request);
}
```

One note: neither argument _require_ a typehint, and examples throughout the
manual will omit the typehints when demonstrating callable middleware.

## Service-based middleware

We encourage the use of a dependency injection container for providing your
middleware. As such, Expressive also allows you to use _service names_ for both
pipeline and routed middleware. Generally, service names will be the specific
middleware class names, but can be any valid string that resolves to a service.

When Expressive is provided a service name for middleware, it internally
decorates the middleware in a `Zend\Expressive\Middleware\LazyLoadingMiddleware`
instance, allowing it to be loaded only when dispatched.

## Middleware pipelines

Expressive allows any pipeline or routed middleware to be self-contained
[middleware pipelines](https://docs.zendframework.com/zend-stratigility/api/#middleware).
To prevent the need for instantiating a `Zend\Stratigility\MiddlewarePipe`
instance when defining the pipeline, Expressive allows you to provide an array
of middleware:

```php
// Pipeline middleware:
$app->pipe([
    FirstMiddleware::class,
    SecondMiddleware::class,
]);

// Routed middleware:
$app->get('/foo', [
    FirstMiddleware::class,
    SecondMiddleware::class,
]);
```

The values in these arrays may be any valid middleware type as defined in this
chapter.
