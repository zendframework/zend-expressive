# How can I prepend a common path to all my routes?

You may have multiple middleware in your project, each providing their own
functionality:

```php
$app->pipe(UserMiddleware::class);
$app->pipe(ProjectMiddleware::class);
```

Let's assume the above represents an API.

As your application progresses, you may have a mixture of different content, and now want to have
the above segregated under the path `/api`.

To accomplish it, we will pipe an _array_ of middleware _under a path_, `/api`.

When we pipe an array of middleware, internally, `Zend\Expressive\Application`
creates a new `Zend\Stratigility\MiddlewarePipe` instance, and pipes each
middleware item to it.

When we specify a path, the middleware is decorated with a
`Zend\Stratigility\Middleware\PathMiddlewareDecorator`. This middleware will
compare the request path against the path with which it was created; if they
match, it passes processing on to its middleware.

The following example assumes you are using the structure of
`config/pipeline.php` as shipped with the skeleton application.

```php
use Psr\Container\ContainerInterface;
use Zend\Expressive\Application;
use Zend\Expressive\MiddlewareFactory;

/**
 * Setup middleware pipeline:
 */
return function (Application $app, MiddlewareFactory $factory, ContainerInterface $container) : void {
    // . . .
    $app->pipe('/api', [
        UserMiddleware::class,
        ProjectMiddleware::class,
    ]);
    // . . .
}
```

Alternately, you can perform the path decoration manually:

```php
use Psr\Container\ContainerInterface;
use Zend\Expressive\Application;
use Zend\Expressive\MiddlewareFactory;

use function Zend\Stratigility\path;

/**
 * Setup middleware pipeline:
 */
return function (Application $app, MiddlewareFactory $factory, ContainerInterface $container) : void {
    // . . .
    $app->pipe(path('/api', $factory->pipeline(
        UserMiddleware::class,
        ProjectMiddleware::class
    )));
    // . . .
}
```

(Calling `$factory->pipeline()` is necessary here to ensure that we create the
`MiddlewarePipe` instance, and so that each item in the specified pipeline will
be decorated as `Zend\Expressive\Middleware\LazyLoadingMiddleware`.)
