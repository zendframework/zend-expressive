# Error Handling

Expressive provides error handling out of the box, via zend-stratigility's [FinalHandler
implementation](https://github.com/zendframework/zend-stratigility/blob/master/doc/book/api.md#finalhandler).
This pseudo-middleware is executed in the following conditions:

- If the middleware stack is exhausted, and no middleware has returned a response.
- If an error has been passed via `$next()`, but not handled by any error middleware.

The `FinalHandler` essentially tries to recover gracefully. In the case that no error was passed, it
does the following:

- If the response passed to it differs from the response provided at initialization, it will return
  the response directly; the assumption is that some middleware along the way called `$next()`
  with a new response.
- If the response instances are identical, it checks to see if the body size has changed; if it has,
  the assumption is that a middleware at some point has written to the response body.
- At this point, it assumes no middleware was able to handle the request, and creates a 404
  response, indicating "Not Found."

In the event that an error *was* passed, it does the following:

- If `$error` is not an exception, it will use the response status if it already indicates an error
  (ie., &gt;= 400 status), or will use a 500 status, and return the response directly with the
  reason phrase.
- If `$error` *is* an exception, it will use the exception status if it already indicates an error
  (ie., &gt;= 400 status), or will use a 500 status, and return the response directly with the
  reason phrase. If the `FinalHandler` was initialized with an option indicating that it is in
  development mode, it writes the exception stack trace to the response body.

This workflow stays the same throughout zend-expressive. But sometimes, it's just not enough.

## Templated Errors

You'll typically want to provide error messages in your site template. To do so, we provide
`Zend\Expressive\TemplatedErrorHandler`. This class is similar to the `FinalHandler`, but accepts,
optionally, a `Zend\Expressive\Template\TemplateRendererInterface` instance, and template names to use for
404 and general error conditions. This makes it a good choice for use in production.

First, of course, you'll need to select a templating system and ensure you have
the appropriate dependencies installed; see the [templating documentation](template/intro.md)
for information on what we support and how to install supported systems.

Once you have selected your templating system, you can setup the templated error
handler.

```php
use Zend\Expressive\Application;
use Zend\Expressive\Plates\PlatesRenderer;
use Zend\Expressive\TemplatedErrorHandler;

$plates = new PlatesRenderer();
$plates->addPath(__DIR__ . '/templates/error', 'error');
$finalHandler = new TemplatedErrorHandler($plates, 'error::404', 'error::500');

$app = new Application($router, $container, $finalHandler);
```

The above will use the templates `error::404` and `error::500` for 404 and general errors,
respectively, rendering them using our Plates template adapter.

You can also use the `TemplatedErrorHandler` as a substitute for the `FinalHandler`, without using
templated capabilities, by omitting the `TemplateRendererInterface` instance when instantiating it. In this
case, the response message bodies will be empty, though the response status will reflect the error.

See the section titled "Container Factories and Configuration", below, for techniques on configuring
the `TemplatedErrorHandler` as your final handler within a container-based application.

## Whoops

[whoops](http://filp.github.io/whoops/) is a library for providing a more usable UI around
exceptions and PHP errors. We provide integration with this library through
`Zend\Express\WhoopsErrorHandler`. This error handler derives from the `TemplatedErrorHandler`, and
uses its features for 404 status and non-exception errors. For exceptions, however, it will return
the whoops output. As such, it is a good choice for use in development.

To use it, you must first install whoops:

```bash
$ composer require filp/whoops
```

Then you will need to provide the error handler a whoops runtime instance, as well as a
`Whoops\Handler\PrettyPageHandler` instance. You can also optionally provide a `TemplateRendererInterface`
instance and template names, just as you would for a `TemplatedErrorHandler`.

```php
use Whoops\Handler\PrettyPageHandler;
use Whoops\Run as Whoops;
use Zend\Expressive\Application;
use Zend\Expressive\Plates\PlatesRenderer;
use Zend\Expressive\WhoopsErrorHandler;

$handler = new PrettyPageHandler();

$whoops = new Whoops;
$whoops->writeToOutput(false);
$whoops->allowQuit(false);
$whoops->pushHandler($handler);

$plates = new PlatesRenderer();
$plates->addPath(__DIR__ . '/templates/error', 'error');
$finalHandler = new WhoopsErrorHandler(
    $whoops,
    $handler,
    $plates,
    'error::404',
    'error::500'
);

$app = new Application($router, $container, $finalHandler);

// Register Whoops just before running the application, as otherwise it can
// swallow bootstrap errors. 
$whoops->register();
$app->run();
```

The calls to `writeToOutput(false)`, `allowQuit(false)`, and `register()` must be made to guarantee
whoops will interoperate well with zend-expressive.

You can add more handlers if desired.

Internally, when an exception is discovered, zend-expressive adds some data to the whoops output,
primarily around the request information (URI, HTTP request method, route match attributes, etc.).

See the next section for techniques on configuring the `WhoopsErrorHandler` as your final handler
within a container-based application.

## Container Factories and Configuration

The above may feel like a bit much when creating your application. As such, we provide several
factories that work with [container-interop](https://github.com/container-interop/container-interop)-compatible
container implementations to simplify setup.

In each case, you should register the selected error handler's factory as the service
`Zend\Expressive\FinalHandler`.

- For the `TemplatedErrorHandler`, use [`Zend\Expressive\Container\TemplatedErrorHandlerFactory`](container/factories.md#templatederrorhandlerfactory).
- For the `WhoopsErrorHandler`, use [`Zend\Expressive\Container\WhoopsErrorHandlerFactory`](container/factories.md#whoopserrorhandlerfactory).
