# How does one segregate middleware by host?

If your application is being re-used to respond to multiple host domains, how
can you segregate middleware to work only in reponse to a specific host request?

As an example, perhaps you have an "admin" area of your application you only
want to expose via the host name "admin.example.org"; how can you do this?

## The host function

[Stratigility](https://docs.zendframework.com/zend-stratigility/) provides a
function, `Zend\Stratigility\host()` that can be used to decorate middleware in
a `Zend\Stratigility\Middleware\HostMiddlewareDecorator` instance. These expect
the string name of a host, and the middleware that should only trigger when that
host is matched in the request.

As a simple example:

```php
// in config/pipeline.php:
use function Zend\Stratigility\host;

$app->pipe(host('admin.example.org', $adminMiddleware));
```

However, you'll note that the above uses an already instantiated middleware
instance; how can you lazy-load a named service instead?

## Lazy-loading host-segregated middleware

The `config/pipeline.php` file defines and returns a callable that accepts three
arguments:

- a `Zend\Expressive\Application $app` instance
- a `Zend\Expressive\MiddlewareFactory $factory` instance
- a `Psr\Container\ContainerInterface $container` instance

We can use the second of these to help us. We will use the `lazy()` method to
specify a middleware service name to lazy-load:

```php
$app->pipe(host('admin.example.org', $factory->lazy(AdminMiddleware::class)));
```

What about specifying a pipeline of middleware? For that, we can use the
`pipeline()` method of the factory:

```php
$app->pipe(host('admin.example.org', $factory->pipeline(
    SessionMiddleware::class,
    AuthenticationMiddleware::class,
    AuthorizationMiddleware::class,
    AdminHandler::class
)));
```

Alternately, either of the above examples could use the `prepare()` method:

```php
// lazy example:
$app->pipe(host('admin.example.org', $factory->prepare(AdminMiddleware::class)));

// pipeline example:
$app->pipe(host('admin.example.org', $factory->prepare([
    SessionMiddleware::class,
    AuthenticationMiddleware::class,
    AuthorizationMiddleware::class,
    AdminHandler::class,
])));
```

> For more information on the `MiddlewareFactory`, [read its documentation](../features/container/middleware-factory.md).
