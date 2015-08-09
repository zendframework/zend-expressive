# Cookbook

Below are several common problems that could occur.

## How can I prepend a common path to all my routes?

You may have multiple middlewares providing their own functionality:

```php
$middleware1 = new UserMiddleware();
$middleware2 = new ProjectMiddleware();

$app = AppFactory::create();
$app->pipe($middleware1);
$app->pipe($middleware2);

$app->run();
```

However, as you are creating an API, you may want to prepend all the paths provided by those middlewares by "/api". To do that,
you can simply create a new application, and pipe the previous application (this works because an application is also a
middleware):

```php
$middleware1 = new UserMiddleware();
$middleware2 = new ProjectMiddleware();

$app = AppFactory::create();
$app->pipe($middleware1);
$app->pipe($middleware2);

$finalApp = AppFactory::create();
$finalApp->pipe('/api', $app);

$finalApp->run();
```