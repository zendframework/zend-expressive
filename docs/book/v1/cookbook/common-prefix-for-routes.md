# How can I prepend a common path to all my routes?

You may have multiple middlewares providing their own functionality:

```php
$middleware1 = new UserMiddleware();
$middleware2 = new ProjectMiddleware();

$app = AppFactory::create();
$app->pipe($middleware1);
$app->pipe($middleware2);

$app->run();
```

Let's assume the above represents an API.

As your application progresses, you may have a mixture of different content, and now want to have
the above segregated under the path `/api`.

This is essentially the same problem as addressed in the
["Segregating your application to a subpath"](../reference/usage-examples.md#segregating-your-application-to-a-subpath) example.

To accomplish it:

- Create a new application.
- Pipe the previous application to the new one, under the path `/api`.

```php
$middleware1 = new UserMiddleware();
$middleware2 = new ProjectMiddleware();

$api = AppFactory::create();
$api->pipe($middleware1);
$api->pipe($middleware2);

$app = AppFactory::create();
$app->pipe('/api', $api);

$app->run();
```

The above works, because every `Application` instance is itself middleware, and, more specifically,
an instance of [Stratigility's `MiddlewarePipe`](https://github.com/zendframework/zend-stratigility/blob/master/doc/book/middleware.md),
which provides the ability to compose middleware.
