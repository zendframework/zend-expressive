# Error Handling

Error handling has changed from version 1 to version 2. This document details
both the current (version 2) error handling, as well as version 1. The immediate
sections following detail version 2; [jump to the version 1 section](#version-1-error-handling)
if you need that information.

## Handling exceptions and errors

We recommend that your code raise exceptions for conditions where it cannot
gracefully recover. Additionally, we recommend that you have a reasonable PHP
`error_reporting` setting that includes warnings and fatal errors:

```php
error_reporting(E_ALL & ~E_USER_DEPRECATED & ~E_DEPRECATED & ~E_STRICT & ~E_NOTICE);
```

If you follow these guidelines, you can then write or use middleware that does
the following:

- sets an error handler that converts PHP errors to `ErrorException` instances.
- wraps execution of the delegate (`$delegate->process()`) with a try/catch block.

As an example:

```php
function ($request, DelegateInterface $delegate)
{
    set_error_handler(function ($errno, $errstr, $errfile, $errline) {
        if (! (error_reporting() & $errno)) {
            // Error is not in mask
            return;
        }
        throw new ErrorException($errstr, 0, $errno, $errfile, $errline);
    });

    try {
        $response = $delegate->process($request);
        return $response;
    } catch (Throwable $e) {
    } catch (Exception $e) {
    }

    restore_error_handler();

    $response = new TextResponse(sprintf(
        "[%d] %s\n\n%s",
        $e->getCode(),
        $e->getMessage(),
        $e->getTraceAsString()
    ), 500);
}
```

You would then pipe this as the outermost (or close to outermost) layer of your
application:

```php
$app->pipe($errorMiddleware);
```

So that you do not need to do this, we provide an error handler for you, via
zend-stratigility: `Zend\Stratigility\Middleware\ErrorHandler`.

This implementation allows you to both:

- provide a response generator, invoked when an error is caught; and
- register listeners to trigger when errors are caught.

We provide the factory `Zend\Expressive\Container\ErrorHandlerFactory` for
generating the instance; it should be mapped to the service
`Zend\Stratigility\Middleware\ErrorHandler`.

We provide two error response generators for you:

- `Zend\Expressive\Middleware\ErrorResponseGenerator`, which optionally will
  accept a `Zend\Expressive\Template\TemplateRendererInterface` instance, and a
  template name. When present, these will be used to generate response content;
  otherwise, a plain text response is generated that notes the request method
  and URI.

- `Zend\Expressive\Middleware\WhoopsErrorResponseGenerator`, which uses
  [whoops](http://filp.github.io/whoops/) to present detailed exception
  and request information; this implementation is intended for development
  purposes.

Each also has an accompanying factory for generating the instance:

- `Zend\Expressive\Container\ErrorResponseGeneratorFactory`
- `Zend\Expressive\Container\WhoopsErrorResponseGeneratorFactory`

Map the service `Zend\Expressive\Middleware\ErrorResponseGenerator` to one of
these two factories in your configuration:

```php
use Zend\Expressive\Container;
use Zend\Expressive\Middleware;
use Zend\Stratigility\Middleware\ErrorHandler;

return [
    'dependencies' => [
        'factories' => [
            ErrorHandler::class => Container\ErrorHandlerFactory::class,
            Middleware\ErrorResponseGenerator::class => Container\ErrorResponseGeneratorFactory::class,
        ],
    ],
];
```

> ### Use development mode configuration to enable whoops
>
> You can specify the above in one of your `config/autoload/*.global.php` files,
> to ensure you have a production-capable error response generator.
> 
> If you are using [zf-development-mode](https://github.com/zfcampus/zf-development-mode)
> in your application (which is provided by default in the Expressive 2.0
> skeleton), you can toggle usage of whoops by adding configuration to the file
> `config/autoload/development.local.php.dist`:
>
> ```php
> use Zend\Expressive\Container;
> use Zend\Expressive\Middleware;
> 
> return [
>     'dependencies' => [
>         'factories' => [
>             Middleware\WhoopsErrorResponseGenerator::class => Container\WhoopsErrorResponseGeneratorFactory::class,
>         ],
>     ],
> ];
> ```
>
> When you enable development mode, whoops will then be enabled; when you
> disable development mode, you'll be using your production generator.
>
> If you are not using zf-development-mode, you can define a
> `config/autoload/*.local.php` file with the above configuration whenever you
> want to enable whoops.

## Listening for errors

When errors occur, you may want to _listen_ for them in order to provide
features such as logging. `Zend\Stratigility\Middleware\ErrorHandler` provides
the ability to do so via its `attachListener()` method.

This method accepts a callable with the following signature:

```php
function (
    Throwable|Exception $error,
    ServerRequestInterface $request,
    ResponseInterface $response
) : void
```

The response provided is the response returned by your error response generator,
allowing the listener the ability to introspect the generated response as well.

As an example, you could create a logging listener as follows:

```php
namespace Acme;

use Exception;
use Psr\Log\LoggerInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Throwable;

class LoggingErrorListener
{
    /**
     * Log format for messages:
     *
     * STATUS [METHOD] path: message
     */
    const LOG_FORMAT = '%d [%s] %s: %s';

    private $logger;

    public function __construct(LoggerInterface $logger)
    {
        $this->logger = $logger;
    }

    public function __invoke($error, ServerRequestInterface $request, ResponseInterface $response)
    {
        $this->logger->error(sprintf(
            self::LOG_FORMAT,
            $response->getStatusCode(),
            $request->getMethod(),
            (string) $request->getUri(),
            $error->getMessage()
        ));
    }
}
```

You could then use a [delegator factory](container/delegator-factories.md) to
create your logger listener and attach it to your error handler:

```php
namespace Acme;

use Psr\Container\ContainerInterface;
use Psr\Log\LoggerInterface;
use Zend\Stratigility\Middleware\ErrorHandler;

class LoggingErrorListenerDelegatorFactory
{
    /**
     * @param ContainerInterface $container
     * @param string $name
     * @param callable $callback
     * @return ErrorHandler
     */
    public function __invoke(ContainerInterface $container, $name, callable $callback)
    {
        $listener = new LoggingErrorListener($container->get(LoggerInterface::class));
        $errorHandler = $callback();
        $errorHandler->attachListener($listener);
        return $errorHandler;
    }
}
```

## Handling more specific error types

You could also write more specific error handlers. As an example, you might want
to catch `UnauthorizedException` instances specifically, and display a login
page:

```php
function ($request, DelegateInterface $delegate) use ($renderer)
{
    try {
        $response = $delegate->process($request);
        return $response;
    } catch (UnauthorizedException $e) {
    }

    return new HtmlResponse(
        $renderer->render('error::unauthorized'),
        401
    );
}
```

You could then push this into a middleware pipe only when it's needed:

```php
$app->get('/dashboard', [
    $unauthorizedHandlerMiddleware,
    $middlewareThatChecksForAuthorization,
    $middlewareBehindAuthorizationWall,
], 'dashboard');
```

## Default delegates

`Zend\Expressive\Application` manages an internal middleware pipeline; when you
call `$delegate->process()` (v2) or `$next()` (v1 or legacy double-pass
middleware), `Application` is popping off the next middleware in the queue and
dispatching it.

What happens when that queue is exhausted?

That situation indicates an error condition: no middleware was capable of
returning a response. This could either mean a problem with the request (HTTP
400 "Bad Request" status) or inability to route the request (HTTP 404 "Not
Found" status).

In order to report that information, `Zend\Expressive\Application` composes a
"default delegate": a delegate it will invoke once the queue is exhausted and no
response returned. By default, it uses a custom implementation,
`Zend\Expressive\Delegate\NotFoundDelegate`, which will report a 404 response,
optionally using a composed template renderer to do so.

We provide a factory, `Zend\Expressive\Container\NotFoundDelegateFactory`, for
creating an instance, and this should be mapped to the
`Zend\Expressive\Delegate\NotFoundDelegate` service, and aliased to the
`Zend\Expressive\Delegate\DefaultDelegate` service:

```php
use Zend\Expressive\Container;
use Zend\Expressive\Delegate;

return [
    'dependencies' => [
        'aliases' => [
            'Zend\Expressive\Delegate\DefaultDelegate' => Delegate\NotFoundDelegate::class,
        ],
        'factories' => [
            Delegate\NotFoundDelegate::class => Container\NotFoundDelegateFactory::class,
        ],
    ],
];
```

The factory will consume the following services:

- `Zend\Expressive\Template\TemplateRendererInterface` (optional): if present,
  the renderer will be used to render a template for use as the response
  content.

- `config` (optional): if present, it will use the
  `$config['zend-expressive']['error_handler']['template_404']` value
  as the template to use when rendering; if not provided, defaults to
  `error::404`.

If you wish to provide an alternate response status or use a canned response,
you should provide your own default delegate, and expose it via the
`Zend\Expressive\Delegate\DefaultDelegate` service.

## Page not found

Error handlers work at the outermost layer, and are used to catch exceptions and
errors in your application. At the _innermost_ layer of your application, you
should ensure you have middleware that is _guaranteed_ to return a response;
this will prevent the default delegate from needing to execute by ensuring that
the middleware queue never fully depletes. This in turn allows you to fully
craft what sort of response is returned.

Generally speaking, reaching the innermost middleware layer indicates that no
middleware was capable of handling the request, and thus an HTTP 404 Not Found
condition.

To simplify such responses, we provide `Zend\Expressive\Middleware\NotFoundHandler`,
with an accompanying `Zend\Expressive\Container\NotFoundHandlerFactory`. This
middleware composes and proxies to the `NotFoundDelegate` detailed in the
previous section, and, as such, requires that that service be present.

```php
use Zend\Expressive\Container;
use Zend\Expressive\Delegate;
use Zend\Expressive\Middleware;

return [
    'factories' => [
        Delegate\NotFoundDelegate::class => Container\NotFoundDelegateFactory::class,
        Middleware\NotFoundHandler::class => Container\NotFoundHandlerFactory::class,
    ],
];
```

When registered, you should then pipe it as the innermost layer of your
application:

```php
// A basic application:
$app->pipe(ErrorHandler::class);
$app->pipeRoutingMiddleware();
$app->pipeDispatchMiddleware();
$app->pipe(NotFoundHandler::class);
```

## Version 1 error handling

> ### Deprecated!
>
> As noted in the introduction to this page, this section details error handling
> under version 1 of Expressive. We strongly recommend upgrading to version 2, in
> large part due to its more flexible approach to error handling.
>
> In addition, be aware that both the final handlers and Stratigility 1.X error
> middleware are completely absent from Expressive 2.0; you _will_ need to
> migrate your code at some point!

Expressive 1 provides error handling out of the box, via zend-stratigility 1.X's [FinalHandler
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

### Templated Errors

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

### Whoops

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

### Container Factories and Configuration

The above may feel like a bit much when creating your application. As such, we provide several
factories that work with [PSR-11 Container](https://github.com/php-fig/container)
implementations to simplify setup.

In each case, you should register the selected error handler's factory as the service
`Zend\Expressive\FinalHandler`.

- For the `TemplatedErrorHandler`, use [`Zend\Expressive\Container\TemplatedErrorHandlerFactory`](container/factories.md#templatederrorhandlerfactory).
- For the `WhoopsErrorHandler`, use [`Zend\Expressive\Container\WhoopsErrorHandlerFactory`](container/factories.md#whoopserrorhandlerfactory).
