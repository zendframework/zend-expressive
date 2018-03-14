# The Middleware Factory

With version 3, we made a conscious choice to use strong type-hinting wherever
possible. However, we also recognize that doing so can sometimes be an
inconvenience to the user and lead to an explosion in code verbosity.

One area in particular that concerned us was the `Application` instance itself,
and the various methods it exposes for piping and routing middleware. If we made
each of these strictly typed, users would be forced to write code that looks
like the following:

```php
use Psr\Container\ContainerInterface;
use Zend\Expressive\Application;
use Zend\Expressive\Middleware\LazyLoadingMiddleware;
use Zend\Stratigility\MiddlewarePipe;

use function Zend\Stratigility\middleware;
use function Zend\Stratigility\path;

return function (Application $app, ContainerInterface $container) : void {
    $app->pipe(path(
        '/foo',
        new LazyLoadingMiddleware(App\FooMiddleware::class, $container)
    ));

    $app->pipe(middleware(function ($request, $handler) {
        // ...
    }));

    $booksPipeline = new MiddlewarePipe();
    $booksPipeline->pipe(new LazyLoadingMiddleware(
        Zend\ProblemDetails\ProblemDetailsMiddleware::class,
        $container
    ));
    $booksPipeline->pipe(new LazyLoadingMiddleware(
        App\SessionMiddleware::class,
        $container
    ));
    $booksPipeline->pipe(new LazyLoadingMiddleware(
        App\AuthenticationMiddleware::class,
        $container
    ));
    $booksPipeline->pipe(new LazyLoadingMiddleware(
        App\AuthorizationMiddleware::class,
        $container
    ));
    $booksPipeline->pipe(new LazyLoadingMiddleware(
        Zend\Expressive\Helper\BodyParams\BodyParamsMiddleware::class
        $container
    ));
    $booksPipeline->pipe(new LazyLoadingMiddleware(
        App\ValidationMiddleware::class
        $container
    ));
    $booksPipeline->pipe(new LazyLoadingMiddleware(
        App\Handler\CreateBookHandler::class
        $container
    ));
    $app->post('/books/{id:\d+}', $booksPipeline);
};
```

Additionally, this would pose an enormous burden when migrating to version 3.

For these reasons, we developed the class `Zend\Expressive\MiddlewareFactory`.
It composes a [MiddlewareContainer](middleware-container.md) in order to back
the following operations.

## callable

```php
$middleware = $factory->callable(function ($request, $handler) {
});
```

This method takes a callable middleware, and decorates it as a
`Zend\Stratigility\Middleware\CallableMiddlewareDecorator` instance.

## handler

```php
$middleware = $factory->handler($requestHandler);
```

This method takes a PSR-15 request handler instance and decorates it as a
`Zend\Stratigility\Middleware\RequestHandlerMiddleware` instance.

## lazy

```php
$middleware = $factory->lazy(App\Middleware\FooMiddleware::class);
```

This method decorates the service name using
`Zend\Expressive\Middlware\LazyLoadingMiddleware`, passing the composed
`MiddlewareContainer` to the instance during instantiation.

## pipeline

```php
$pipeline = $factory->pipeline(
    $middlewareInstance,
    'MiddlewareServiceName',
    function ($request, $handler) {
    },
    $requestHandlerInstance
);
```

Creates and returns a `Zend\Stratigility\MiddlewarePipe`, after passing each
argument to `prepare()` first.

(You may pass an array of values instead of individual arguments as well.)

## prepare

```php
$middleware = $factory->prepare($middleware);
```

Inspects the provided middleware argument, with the following behavior:

- `MiddlewareInterface` instances are returned verbatim.
- `RequestHandlerInterface` instances are decorated using `handler()`.
- `callable` arguments are decorated using `callable()`.
- `string` arguments are decorated using `lazy()`.
- `array` arguments are decorated using `pipeline()`.

## Usage in bootstrapping

The skeleton defines two files `config/pipeline.php` and `config/routes.php`.
These are expected to return a callable with the following signature:

```php
use Psr\Container\ContainerInterface;
use Zend\Expressive\Application;
use Zend\Expressive\MiddlewareFactory;

return function (Application $app, MiddlewareFactory $factory, ContainerInterface $container) : void {
};
```

Note that the `MiddlewareFactory` is passed to these callables; this gives you
the ability to use it for more complex piping and routing needs, including
creating nested pipelines.

As an example, we'll rewrite our initial example to use the `MiddlewareFactory`:

```php
use Psr\Container\ContainerInterface;
use Zend\Expressive\Application;
use Zend\Expressive\MiddlewareFactory;

use function Zend\Stratigility\path;

return function (Application $app, MiddlewareFactory $factory, ContainerInterface $container) : void {
    $app->pipe(path('/foo', $factory->prepare(App\FooMiddleware::class)));

    $app->pipe($factory->prepare(function ($request, $handler) {
        // ...
    }));

    $app->post('/books/{id:\d+}', $factory->pipeline(
        ProblemDetailsMiddleware::class,
        App\SessionMiddleware::class,
        App\AuthenticationMiddleware::class,
        App\AuthorizationMiddleware::class,
        Zend\Expressive\Helper\BodyParams\BodyParamsMiddleware::class,
        App\ValidationMiddleware::class,
        App\Handler\CreateBookHandler::class
    ));
};
```

> #### Further simplifications
>
> Internally, `Application`'s `pipe()` and various routing methods make use of
> the `MiddlewareFactory` already; `pipe()` also already makes use of `path()`
> as well. As such, usage of the `MiddlewareFactory` is not strictly necessary
> in the above example; it is used for illustrative purposes only.
