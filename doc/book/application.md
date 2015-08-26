# Applications

In zend-expressive, you define a `Zend\Expressive\Application` instance and
execute it. The `Application` instance is itself [middleware](https://github.com/zendframework/zend-stratigility/blob/master/doc/book/middleware.md)
that composes:

- a [router](router/intro.md), for dynamically routing requests to middleware.
- a [dependency injection container](container/intro.md), for retrieving
  middleware to dispatch.
- a [final handler](error-handling.md), for handling error conditions raised by
  the application.
- an [emitter](https://github.com/zendframework/zend-diactoros/blob/master/doc/book/emitting-responses.md),
  for emitting the response when application execution is complete.

You can define the `Application` instance in several ways:

- Direct instantiation, which requires providing several dependencies.
- The `AppFactory`, which will use sane defaults, but allows injecting alternate
  container and/or router implementations.
- Via a dependency injection container; we provide a factory for setting up all
  aspects of the instance via configuration and other defined services.

Regardless of how you setup the instance, there are several methods you will
likely interact with at some point or another.

## Instantiation

As noted at the start of this document, we provide several ways to create an
`Application` instance.

### Constructor

If you wish to manually instantiate the `Application` instance, it has the
following constructor:

```php
/**
 * @param Zend\Expressive\Router\RouterInterface $router
 * @param null|Interop\Container\ContainerInterface $container IoC container from which to pull services, if any.
 * @param null|callable $finalHandler Final handler to use when $out is not
 *     provided on invocation.
 * @param null|Zend\Diactoros\Response\EmitterInterface $emitter Emitter to use when `run()` is
 *     invoked.
 */
public function __construct(
    Zend\Expressive\Router\RouterInterface $router,
    Interop\Container\ContainerInterface $container = null,
    callable $finalHandler = null,
    Zend\Diactoros\Response\EmitterInterface $emitter = null
);
```

If no container is provided at instantiation, then all routed and piped
middleware **must** be provided as callables.

### AppFactory

`Zend\Expressive\AppFactory` provides a convenience layer for creating an
`Application` instance; it makes the assumption that you will use defaults in
most situations, and likely only change which container and/or router you wish
to use. It has the following signature:

```php
AppFactory::create(
    Interop\Container\ContainerInterface $container = null,
    Zend\Expressive\Router\RouterInterface $router = null
);
```

When no container or router are provided, it defaults to:

- zend-servicemanager for the container.
- Aura.Router for the router.

### Container factory

We also provide a factory that can be consumed by a
[container-interop](https://github.com/container-interop/container-interop)
dependency injection container; see the [container factories documentation](container/factories.md)
for details.

## Adding routable middleware

We [discuss routing vs piping elsewhere](router/piping.md); routing is the act
of dynamically matching an incoming request against criteria, and it is one of
the primary features of zend-expressive.

Regardless of which [router implementation](router/interface.md) you use, you
can use the following methods to provide routable middleware:

### route()

`route()` has the following signature:

```php
public function route(
    $pathOrRoute,
    $middleware = null,
    array $methods = null,
    $name = null
) : Zend\Expressive\Router\Route
```

where:

- `$pathOrRoute` may be either a string path to match, or a
  `Zend\Expressive\Router\Route` instance.
- `$middleware` **must** be present if `$pathOrRoute` is a string path, and
  **must** be a callable or a service name that resolves to valid middleware.
- `$methods` must be an array of HTTP methods valid for the given path and
  middleware. If null, it assumes any method is valid.
- `$name` is the optional name for the route, and is used when generating a URI
  from known routes. See the section on [route naming](router/uri-generation.md#generating-uris)
  for details.

This method is typically only used if you want a single middleware to handle
multiple HTTP request methods.

### get(), post(), put(), patch(), delete()

Each of the methods `get()`, `post()`, `put()`, `patch()`, and `delete()`
proxies to `route()` and has the signature:

```php
function (
    $pathOrRoute,
    $middleware = null,
    $name = null
) : Zend\Expressive\Router\Route
```

Essentially, each calls `route()` and specifies an array consisting solely of
the corresponding HTTP method for the `$methods` argument.

### Piping

Because zend-expressive builds on [zend-stratigility](https://github.com/zendframework/zend-stratigility),
and, more specifically, its `MiddlewarePipe` definition, you can also pipe
(queue) middleware to the application. This is useful for adding middleware that
should execute on each request, defining error handlers, and/or segregating
applications by subpath.

The signature of `pipe()` is:

```php
public function pipe($pathOrMiddleware, $middleware = null)
```

where:

- `$pathOrMiddleware` is either a string URI path (for path segregation), a
  callable middleware, or the service name for a middleware to fetch from the
  composed container.
- `$middleware` is required if `$pathOrMiddleware` is a string URI path. It can
  be either a callable, or the service name for a middleware to fetch from the
  composed container.

Unlike `Zend\Stratigility\MiddlewarePipe`, `Application::pipe()` *allows
fetching middleware by service name*. This facility allows lazy-loading of
middleware only when it is invoked. Internally, it wraps the call to fetch and
dispatch the middleware inside a closure.

Additionally, we define a new method, `pipeErrorHandler()`, with the following
signature:

```php
public function pipeErrorHandler($pathOrMiddleware, $middleware = null)
```

It acts just like `pipe()` except when the middleware specified is a service
name; in that particular case, when it wraps the middleware in a closure, it
uses the error handler signature:

```php
function ($error, ServerRequestInterface $request, ResponseInterface $response, callable $next);
```

Read the section on [piping vs routing](router/piping.md) for more information.

### Registering routing middleware

Routing is accomplished via dedicated a dedicated middleware method, 
`Application::routeMiddleware()`. It is an instance method, and can be
piped/registered with other middleware platforms if desired.

Internally, the first time `route()` is called (including via one of the proxy
methods), or, if never called, when `__invoke()` (the exposed application
middleware) is called, the instance will pipe `Application::routeMiddleware` to
the middleware pipeline. You can also pipe it manually if desired:

```php
$app->pipeRoutingMiddleware();
```

## Retrieving dependencies

As noted in the intro, the `Application` class has several dependencies. Some of
these may allow further configuration, or may be useful on their own, and have
methods for retrieving them. They include:

- `getContainer()`: returns the composed [container-interop](https://github.com/container-interop/container-interop)
  instance (used to retrieve routed middleware).
- `getEmitter()`: returns the composed
  [emitter](https://github.com/zendframework/zend-diactoros/blob/master/doc/book/emitting-responses.md),
  typically a `Zend\Expressive\Emitter\EmitterStack` instance.
- `getFinalHandler(ResponseInterface $response = null)`: retrieves the final
  handler instance. This is middleware with the signature `function ($request,
  $response, $error = null)`, and it is invoked when the middleware pipeline
  queue is depleted and no response has been returned.

## Executing the application: run()

When the application is completely setup, you can execute it with the `run()`
method. The method may be called with no arguments, but has the following
signature:

```php
public function run(
    ServerRequestInterface $request = null,
    ResponseInterface $response = null
);
```
