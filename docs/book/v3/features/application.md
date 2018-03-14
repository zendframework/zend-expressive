# Applications

In zend-expressive, you define a `Zend\Expressive\Application` instance and
execute it. The `Application` instance is itself [middleware](https://docs.zendframework.com/zend-stratigility/middleware/)
that composes:

- a `Zend\Expressive\MiddlewareFactory` instance, used to prepare middleware
  arguments to pipe into:
- a `Zend\Stratigility\MiddlewarePipe` instance, representing the application
  middleware pipeline.
- a `Zend\Expressive\Router\RouteCollector` instance, used to create
  `Zend\Expressive\Router\Route` instances based on a combination of paths and
  HTTP methods, and which also injects created instances into the application's
  router.
- a `Zend\HttpHandlerRunner\RequestHandlerRunner` instance which will ultimately
  be responsible for marshaling the incoming request, passing it to the
  `MiddlewarePipe`, and emitting the response.

You can define the `Application` instance in two ways:

- Direct instantiation, which requires providing several dependencies.
- Via a dependency injection container; we provide a factory for setting up all
  aspects of the instance via configuration and other defined services.

Regardless of how you setup the instance, there are several methods you will
likely interact with at some point or another.

## Instantiation

### Constructor

If you wish to manually instantiate the `Application` instance, it has the
following constructor:

```php
public function __construct(
    Zend\Expressive\MiddlewareFactory $factory,
    Zend\Stratigility\MiddlewarePipeInterface $pipeline,
    Zend\Expressive\Router\RouteCollector $routes,
    Zend\HttpHandlerRunner\RequestHandlerRunner $runner
) {
```

### Container factory

We also provide a factory that can be consumed by a [PSR-11](https://www.php-fig.org/psr/psr-11/)
dependency injection container; see the [container factories documentation](container/factories.md)
for details.

## Adding routable middleware

We [discuss routing vs piping elsewhere](router/piping.md); routing is the act
of dynamically matching an incoming request against criteria, and it is one of
the primary features of zend-expressive.

Regardless of which [router implementation](router/interface.md) you use, you
can use the following `Application` methods to provide routable middleware:

### route()

`route()` has the following signature:

```php
public function route(
    string $path,
    $middleware,
    array $methods = null,
    string $name = null
) : Zend\Expressive\Router\Route
```

where:

- `$path` must be a string path to match.
- `$middleware` **must** be:
    - a service name that resolves to valid middleware in the container;
    - a fully qualified class name of a constructor-less class that represents a
      PSR-15 `MiddlewareInterface` or `RequestHandlerInterface` instance;
    - an array of any of the above; these will be composed in order into a
      `Zend\Stratigility\MiddlewarePipe` instance.
- `$methods` must be an array of HTTP methods valid for the given path and
  middleware. If null, it assumes any method is valid.
- `$name` is the optional name for the route, and is used when generating a URI
  from known routes. See the section on [route naming](router/uri-generation.md#generating-uris)
  for details.

This method is typically only used if you want a single middleware to handle
multiple HTTP request methods.

### get(), post(), put(), patch(), delete(), any()

Each of the methods `get()`, `post()`, `put()`, `patch()`, `delete()`, and `any()`
proxies to `route()` and has the signature:

```php
function (
    string $path,
    $middleware,
    string $name = null
) : Zend\Expressive\Router\Route
```

Essentially, each calls `route()` and specifies an array consisting solely of
the corresponding HTTP method for the `$methods` argument.

### Piping

Because zend-expressive builds on [zend-stratigility](https://docs.zendframework.com/zend-stratigility/),
and, more specifically, its `MiddlewarePipe` definition, you can also pipe
(queue) middleware to the application. This is useful for adding middleware that
should execute on each request, defining error handlers, and/or segregating
applications by subpath.

The signature of `pipe()` is:

```php
public function pipe($middlewareOrPath, $middleware = null)
```

where:

- `$middlewareOrPath` is either a string URI path (for path segregation), PSR-15
  `MiddlewareInterface` or `RequestHandlerInterface`, or the service name for a
  middleware or request handler to fetch from the composed container.
- `$middleware` is required if `$middlewareOrPath` is a string URI path. It can
  be one of:
    - a service name that resolves to valid middleware in the container;
    - a fully qualified class name of a constructor-less class that represents a
      PSR-15 `MiddlewareInterface` or `RequestHandlerInterface` instance;
    - an array of any of the above; these will be composed in order into a
      `Zend\Stratigility\MiddlewarePipe` instance.

Unlike `Zend\Stratigility\MiddlewarePipe`, `Application::pipe()` *allows
fetching middleware and request handlers by service name*. This facility allows
lazy-loading of middleware only when it is invoked. Internally, it wraps the
call to fetch and dispatch the middleware inside a
`Zend\Expressive\Middleware\LazyLoadingMiddleware` instance.

Read the section on [piping vs routing](router/piping.md) for more information.

### Registering routing and dispatch middleware

Routing and dispatch middleware must be piped to the application like any other
middleware. You can do so using the following:

```php
$app->pipe(Zend\Expressive\Router\Middleware\RouteMiddleware::class);
$app->pipe(Zend\Expressive\Router\Middleware\DispatchMiddleware::class);
```

We recommend piping the following middleware between the two as well:

```php
$app->pipe(Zend\Expressive\Router\Middleware\ImplicitHeadMiddleware::class);
$app->pipe(Zend\Expressive\Router\Middleware\ImplicitOptionsMiddleware::class);
$app->pipe(Zend\Expressive\Router\Middleware\MethodNotAllowedMiddleware::class);
```

These allow your application to return:

- `HEAD` requests for handlers that do not specifically allow `HEAD`; these will
  return with a 200 status, and any headers normally returned with a `GET`
  request.
- `OPTIONS` requests for handlers that do not specifically allow `OPTIONS`;
  these will return with a 200 status, and an `Allow` header indicating all
  allowed HTTP methods for the given route match.
- 405 statuses when the route matches, but not the HTTP method; these will also
  include an `Allow` header indicating all allowed HTTP methods.

See the section on [piping](router/piping.md) to see how you can register
non-routed middleware and create layered middleware applications.

## Executing the application: run()

When the application is completely setup, you can execute it with the `run()`
method. The method proxies to the underlying `RequestHandlerRunner`, which will
create a PSR-7 server request instance, pass it to the composed middleware
pipeline, and then emit the response returned.
