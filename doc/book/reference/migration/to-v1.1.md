# Migration to Expressive 1.1

Expressive 1.1 should not result in any upgrade problems for users. However,
starting in this version, we offer a few changes affecting the following that
you should be aware of, and potentially update your application to adopt:

- Original request and response messages
- Error handling
- Programmatic middleware pipelines
- Usage of [http-interop middleware](https://github.com/http-interop/http-middleware)

## Original messages

Stratigility 1.3 deprecates its internal request and response decorators,
`Zend\Stratigility\Http\Request` and `Zend\Stratigility\Http\Response`,
respsectively. The main utility of these instances was to provide access in
inner middleware layers to the original request, original response, and original
URI.

As such access may still be desired, Stratigility 1.3 introduced
`Zend\Stratigility\Middleware\OriginalMessages`. This middleware injects the
following attributes into the request it passes to `$next()`:

- `originalRequest` is the request instance provided to the middleware.
- `originalUri` is the URI instance associated with that request.
- `originalResponse` is the response instance provided to the middleware.

`Zend\Stratigility\FinalHandler` was updated to use these when they're
available.

We recommend adding this middleware as the outermost (first) middleware in your
pipeline. Using configuration-driven middleware, that would look like this:

```php
// config/autoload/middleware-pipeline.global.php
/* ... */
use Zend\Expressive\Helper;
use Zend\Stratigility\Middleware\OriginalMessages;

return [
    'dependencies' => [
        'invokables' => [
            OriginalMessages::class => OriginalMessages::class,
        ],
        /* ... */
    ],
    'middleware_pipeline' => [
        'always' => [
            'middleware' => [
                OriginalMessages::class, // <----- Add this entry
                Helper\ServerUrlMiddleware::class,
                /* ... */
            ],
            'priority' => 10000,
        ],

        /* ... */
    ],
];
```

If using programmatic pipelines (see below):

```php
$app->pipe(OriginalMessages::class);
/* all other middleware */
```

## Error handling

Prior to version 1.1, error handling was accomplished via two mechanisms:

- Stratigility "error middleware" (middleware with the signature `function
  ($error, ServerRequestInterface $request, ResponseInterface $response,
  callable $next)`). This middleware would be invoked when calling `$next()`
  with a third argument indicating an error, and would be expected to handle it
  or delegate to the next error middleware.

  Internally, Stratigility would execute each middleware within a try/catch
  block; if an exception were caught, it would then delegate to the next _error
  middleware_ using the caught exception as the `$err` argument.

- The "Final Handler". This is a special middleware type with the signature
  `function (ServerRequestInterface $request, ResponseInterface $response, $err = null)`,
  and is typically passed when invoking the outermost middleware; in the case of
  Expressive, it is composed in the `Application` instance, and passed to the
  application middleware when it executes `run()`. It is called when the
  internal middleware pipeline is exhausted, but no response has been returned.
  When invoked, it then needed to decide if this was a case of no middleware
  matching (HTTP 404 status), middleware calling `$next()` with an altered
  response (response is then returned), or an error (middleware called
  `$next()` with an `$err` argument, but none was able to handle it).

Expressive 1.1 updates the minimum supported Stratigility version to 1.3, which
deprecates the concept of error middleware, and recommends a "final handler"
that does no error handling, but instead returns a canned response (typically a
404). Additionally, it deprecates the practice of wrapping middleware execution
in a try/catch block, and provides a flag for disabling that behavior entirely,
`raise_throwables`.

Starting in Expressive 1.1, you can set the `raise_throwables` flag in your
configuration:

```php
return [
    'zend-expressive' => [
        'raise_throwables' => true,
    ],
];
```

When enabled, the internal dispatcher will no longer catch exceptions, allowing
you to write your own error handling middleware. Such middleware generally will
look something like this:

```php
function (
    ServerRequestInterface $request,
    ResponseInterface $response,
    callable $next
) {
    try {
        $response = $next($request, $response);
        return $response;
    } catch (\Throwable $exception) {
        // caught PHP 7 throwable
    } catch (\Exception $exception) {
        // caught PHP 5 exception
    }

    // ... 
    // do something with $exception and generate a response
    // ...

    return $response;
}
```

Stratigility 1.3 provides such an implementation via its
`Zend\Stratigility\Middleware\ErrorHandler`. In addition to the try/catch block,
it also sets up a PHP error handler that will catch any PHP error types in the
current `error_reporting` mask; the error handler will raise exceptions of the
type `ErrorException` with the PHP error details.

`ErrorHandler` allows injection of an "error response generator", which allows
you to alter how the error response is generated based on the current
environment. Error response generators are callables with the signature:

```php
function (
    Throwable|Exception $e,
    ServerRequestInterface $request,
    ResponseInterface $response
) : ResponseInterface
```

Expressive 1.1 provides the following functionality to assist with your error
handling needs should you decide to opt in to this functionality:

- `Zend\Expressive\Middleware\ErrorResponseGenerator` will output a canned
  plain/text message, or use a supplied template renderer to generate content
  for the response. It accepts the following arguments to its constructor:

    - `$isDevelopmentMode = false`: whether or not the application is in
      development mode. If so, it will output stack traces when no template
      renderer is used (see below), or supply the exception to the template via
      the `error` variable if a renderer is present.

    - `Zend\Expressive\Template\TemplateRendererInterface $renderer`: if
      supplied, the results of rendering a template will be injected into
      the response. Templates are passed the following variables:

        - `response`: the response at the time of rendering
        - `request`: the request at the time of rendering
        - `uri`: the URI at the time of rendering
        - `status`: the response status code
        - `reason`: the response reason phrase
        - `error`: the exception; this is only provided when in development mode.

    - `$template = 'error::error'`: the template to render, with a default value
      if none is provided.

  `Zend\Expressive\Container\ErrorResponseGeneratorFactory` can create an
  instance, using the following:

    - The `debug` top-level configuration value is used to set the
      `$isDevelopmentMode` flag.

    - If a `Zend\Expressive\Template\TemplateRendererInterface` service is
      registered, it will be provided to the constructor.

    - The value of `zend-expressive.error_handler.template_error`, if present,
      will be used to seed the `$template` argument.

- `Zend\Expressive\Middleware\WhoopsErrorResponseGenerator` uses Whoops to 
  generate the error response. Its constructor takes a single argument, a
  `Whoops\Run` instance. If a `Whoops\Handler\PrettyPageHandler` is registered
  with the instance, it will add a data table with request details derived from
  the `ServerRequestInterface` instance.

  `Zend\Expressive\Container\WhoopsErrorResponseGeneratorFactory` can create an
  instance, and will use the `Zend\Expressive\Whoops` service to seed the
  `Whoops\Run` argument.

- `Zend\Expressive\Middleware\NotFoundHandler` can be used as the innermost
  layer of your pipeline in order to return a 404 response. (Typically, if you
  get to the innermost layer, no middleware was able to handle the request,
  indicating a 404.) By default, it will produce a canned plaintext response.
  However, you can also provide an optional `TemplateRendererInterface` instance
  and `$template` in order to provided templated content.

  The constructor arguments are:

    - `ResponseInterface $responsePrototype`: this is an empty response on which
      to set the 404 status and inject the 404 content.

    - `TemplateRendererInterface $renderer`: optionally, you may provide a
      renderer to use in order to provide templated response content.

    -  $template = 'error::404'`: optionally, you may provide a
      template to render; if none is provided, a sane default is used.

  `Zend\Expressive\Container\NotFoundHandlerFactory` can create an instance for
  you, and will use the following to do so:

    - The `Zend\Expressive\Template\TemplateRendererInterface` service, if
      available.

    - The `zend-expressive.error_handler.template_404` configuration value, if
      available, will be used for the `$template`.

- `Zend\Expressive\Container\ErrorHandlerFactory` will create an instance of
  `Zend\Stratigility\Middleware\ErrorHandler`, and use the
  `Zend\Stratigility\Middleware\ErrorResponseGenerator` service to seed it.

  As such, register one of the following as a factory for the
  `Zend\Stratigility\Middleware\ErrorResponseGenerator` service:

    - `Zend\Expressive\Container\ErrorResponseGeneratorFactory`
    - `Zend\Expressive\Container\WhoopsErrorResponseGeneratorFactory`

### Error handler configuration example

If you are using configuration-driven middleware, your middleware pipeline
configuration may look like this in order to make use of the new error handling
facilities:

```php
// config/autoload/middleware-pipeline.global.php

use Zend\Expressive\Application;
use Zend\Expressive\Container;
use Zend\Expressive\Helper;
use Zend\Expressive\Middleware;
use Zend\Stratigility\Middleware\ErrorHandler;
use Zend\Stratigility\Middleware\ErrorResponseGenerator;
use Zend\Stratigility\Middleware\OriginalMessages;

return [
    // Add the following section to enable the new error handling:
    'zend-expressive' => [
        'raise_throwables' => true,
    ],

    'dependencies' => [
        'invokables' => [
            // See above section on "Original messages":
            OriginalMessages::class => OriginalMessages::class,
        ],
        'factories' => [
            Helper\ServerUrlMiddleware::class => Helper\ServerUrlMiddlewareFactory::class,
            Helper\UrlHelperMiddleware::class => Helper\UrlHelperMiddlewareFactory::class,

            // Add the following three entries:
            ErrorHandler::class => Container\ErrorHandlerFactory::class,
            ErrorResponseGenerator::class => Container\ErrorResponseGeneratorFactory::class,
            Middleware\NotFoundHandler::class => Container\NotFoundHandlerFactory::class,
        ],
    ],

    'middleware_pipeline' => [
        'always' => [
            'middleware' => [
                OriginalMessages::class,
                Helper\ServerUrlMiddleware::class,
                ErrorHandler::class,
                /* ... */
            ],
            'priority' => 10000,
        ],

        'routing' => [
            'middleware' => [
                Application::ROUTING_MIDDLEWARE,
                Helper\UrlHelperMiddleware::class,
                Application::DISPATCH_MIDDLEWARE,
            ],
            'priority' => 1,
        ],

        'not-found' => [
            'middleware' => Middleware\NotFoundHandler::class,
            'priority' => 0,
        ],

        // Remove the section "error""
    ],
];
```

If you are defining a programmatic pipeline (see more below on this), the
pipeline might look like:

```php
$app->pipe(OriginalMessages::class);
$app->pipe(Helper\ServerUrlMiddleware::class);
$app->pipe(ErrorHandler::class);
$app->pipeRoutingMiddleware();
$app->pipe(Helper\UrlHelperMiddleware::class);
$app->pipeDispatchMiddleware();
$app->pipe(Middleware\NotFoundHandler::class);
```

### Error handling and PHP errors

As noted above, `Zend\Stratigility\Middleware\ErrorHandler` also creates a PHP
error handler that casts PHP errors to `ErrorException` instances. More
specifically, it uses the current `error_reporting` value to determine _which_
errors it should cast this way.

This can be problematic when deprecation errors are triggered &mdash; and
both Stratigility and Expressive will trigger a number of these based on
functionality you may have in place. If they are cast to exceptions, code that
would normally run will now result in error pages.

We recommend adding the following line to your `public/index.php` towards the
top of the file:

```php
error_reporting(error_reporting() & ~E_USER_DEPRECATED);
```

This will prevent the error handler from casting deprecation notices to
exceptions, while keeping the rest of your error reporting mask intact.

## Programmatic middleware pipelines

With Expressive 1.0, we recommended creating middleware pipelines and routing
via configuration. Starting with 1.1, we recommend *programmatic creation of
pipelines and routing*.

Programmatic pipelines exercise the existing Expressive API. Methods include:

- `pipe()` allows you to pipe middleware for the pipeline; this can optionally
  take a `$path` argument. (If one argument is present, it is assumed to be
  middleware; with two arguments, the first argument is the `$path`.) Paths are
  literal URI path segments. If the incoming request matches that segment, the
  middleware will execute; otherwise, it will not. These can be used to provide
  sub-applications with their own routing.

- `pipeRoutingMiddleware()` is used to pipe the internal routing middleware into
  the pipeline.

- `pipeDispatchMiddleware()` is used to pipe the internal dispatch middleware into
  the pipeline.

- `pipeErrorMiddleware()` is used to pipe the legacy Stratigility error
  middleware into the pipeline. We recommend **NOT** using this method, and
  instead adapting your application to use the new [error handling
  facilities](#error-handling). Otherwise, it acts just like `pipe()`.
  Starting in Expressive 1.1, this method will emit a deprecation notice.

As an example pipeline:

```php
$app->pipe(OriginalMessages::class);
$app->pipe(Helper\ServerUrlMiddleware::class);
$app->pipe(ErrorHandler::class);
$app->pipeRoutingMiddleware();
$app->pipe(Helper\UrlHelperMiddleware::class);
$app->pipeDispatchMiddleware();
$app->pipe(Middleware\NotFoundHandler::class);
```

Expressive also provides methods for specifying routed middleware. These
include:

- `get($path, $middleware, $name = null)`
- `post($path, $middleware, $name = null)`
- `put($path, $middleware, $name = null)`
- `patch($path, $middleware, $name = null)`
- `delete($path, $middleware, $name = null)`
- `route($path, $middleware, array $methods = null, $name = null)`

Each returns a `Zend\Expressive\Router\Route` instance; this is useful if you
wish to provide additional options to your route:

```php
$app->get('/api/ping', Ping::class)
    ->setOptions([
        'timestamp' => date(),
    ]);
```

As an example, the default routes defined in the skeleton application can be
written as follows:

```php
$app->get('/', \App\Action\HomePageAction::class, 'home');
$app->get('/api.ping', \App\Action\PingAction::class, 'api.ping');
```

We recommend rewriting your middleware pipeline and routing configuration into
programmatic/declarative statements. Specifically:

- We recommend putting the pipeline declarations into `config/pipeline.php`.
- We recommend putting the pipeline declarations into `config/routes.php`.

Once you've written these, you will then need to make the following changes to
your application:

- First, enable the `zend-expressive.programmatic_pipeline` configuration flag.
  This can be done in any `config/autoload/*.global.php` file:

  ```php
  return [
      'zend-expressive' => [
          'programmatic_pipeline' => true,
      ]
  ];
  ```

  Once enabled, any `middleware_pipeline` or `routes` configuration will be
  ignored when creating the `Application` instance.

- Second, update your `public/index.php` to add the following lines immediately
  prior to calling `$app->run();`:

  ```php
  require 'config/pipeline.php';
  require 'config/routes.php';
  ```

Once this has been done, the application will use your new programmatic
pipelines instead of configuration. You can remove the `middleware_pipeline` and
`routes` configuration after verifying your application continues to work.

We also recommend setting up the new [error handling](#error-handling) when you
do.

To simplify this process, we provide a tool, detailed in the next section.

## Migration tool

In order to make migrating to programmatic pipelines and the new error handling
less difficult, we have created a migration tool, installed as a vendor binary,
to assist you: `vendor/bin/expressive-pipeline-from-config`.

This tool does the following:

- Reads your `middleware_pipeline` configuration, and generates a programmatic
  pipeline for you, which is then stored in `config/pipeline.php`. The generated
  pipeline contains the following additions:

    - The first middleware in the pipeline is `Zend\Stratigility\Middleware\OriginalMessages`,
      which injects the incoming request, URI, and response as the request
      attributes `originalRequest`, `originalUri`, and `originalResponse`,
      respectively. (This can aid URI generation in nested middleware later.)

    - The second middleware in the pipeline is `Zend\Stratigility\Middleware\ErrorHandler`.

    - The last middleware in the pipeline is `Zend\Expressive\Middleware\NotFoundHandler`.

- Reads your `routes` configuration, and generates a programmatic
  routing table for you, which is then stored in `config/routes.php`.

- Adds a new configuration file, `config/autoload/programmatic-pipeline.global.php`, 
  which enables the `programmatic_pipelines` and `raise_throwables`
  configuration flags outlined above. Additionally, it adds dependency
  configuration for the new error handlers.

- Inserts two lines before the `$app->run()` statement of your
  `public/index.php`, one each to require `config/pipeline.php` and
  `config/routes.php`.

Your `middleware_pipeline` and `routes` configuration are not removed at this
time, to allow you to test and verify your application first; however, due to
the configuration in `config/autoload/programmatic-pipeline.global.php`, these
are now ignored.

If you wish to use Whoops in your development environment, you may add the
following to a local configuration file (e.g., `config/autoload/local.php`):

```php
use Zend\Expressive\Container\WhoopsErrorResponseGeneratorFactory;
use Zend\Expressive\Middleware\ErrorResponseGenerator;

return [
    'dependencies' => [
        'factories' => [
            ErrorResponseGenerator::class => WhoopsErrorResponseGeneratorFactory::class,
        ],
    ],
];
```

Other things you may want to do:

- The `ErrorHandler` entry could potentially be moved inwards a few layers. As
  an example, the `ServerUrlMiddleware` has no possibility of raising an
  exception or error, and could be moved outwards; you could do similarly for
  any middleware that only injects additional response headers.

- Remove any Stratigility-style error middleware (middleware expecting an error
  as the first argument). If any specialized error handling should occur, add
  additional middleware into the pipeline that can catch exceptions, and have
  that middleware re-throw for exceptions it cannot handle.

- Consider providing your own `Zend\Stratigility\NoopFinalHandler`
  implementation; this will now only be invoked if the queue is exhausted, and
  could return a generic 404 page, raise an exception, etc.

## http-interop

Stratigility 1.3 provides the ability to work with [http-interop middleware
0.2.0](https://github.com/http-interop/http-middleware/tree/ff545c87e97bf4d88f0cb7eb3e89f99aaa53d7a9).

This specification, which is being developed as the basis of
[PSR-15](https://github.com/php-fig/fig-standards/tree/master/proposed/http-middleware),
defines what is known as _lambda_ or _single-pass_ middleware, vs the
_double-pass_ middleware traditionally used by Stratigility and Expressive.

Double-pass refers to the fact that two arguments are passed to the delegation
function `$next`: the request and response. Lambda or single-pass middleware
only pass a single argument, the request.

Stratigility 1.3 provides support for dispatching either style of middleware.

Specifically, your middleware can now implement:

- `Interop\Http\Middleware\ServerMiddlewareInterface`, which defines a single
  method, `process(ServerRequestInterface $request,
  Interop\Http\Middleware\DelegateInterface $delegate)`.
- Callable middleware that follows the above signature (the typehint for the
  request argument is optional).
  
Both styles of middleware may be piped directly to the middleware pipeline or as
routed middleware within Expressive. In each case, you can invoke the
next middleware layer using `$delegate->process($request)`.

Starting in Stratigility 2.0 and Expressive 2.0, `Application` will continue to
accept the legacy double-pass signature, but will require that you either:

- Provide a `$responsePrototype` (a `ResponseInterface` instance) to the
  `Application` instance prior to piping or routing such middleware.
- Decorate the middleware in a `Zend\Stratigility\Middleware\CallableMiddlewareWrapper`
  instance (which also requires a `$responsePrototype`).

We recommend that you begin writing middleware to follow the http-interop
standard at this time. As an example:

```php
namespace App\Middleware;

use Interop\Http\Middleware\DelegateInterface;
use Interop\Http\Middleware\ServerMiddlewareInterface;
use Psr\Http\Message\ServerRequestInterface;

class XClacksOverheadMiddleware implements ServerMiddlewareInterface
{
    /**
     * {@inheritDoc}
     */
    public function process(ServerRequestInterface $request, DelegateInterface $delegate)
    {
        $response = $delegate->process($request);
        return $response->withHeader('X-Clacks-Overhead', 'GNU Terry Pratchett');
    }
}
```

Alternately, you can write this as a callable:

```php
namespace App\Middleware;

use Interop\Http\Middleware\DelegateInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

class XClacksOverheadMiddleware
{
    /**
     * @param ServerRequestInterface $request
     * @param DelegateInterface $delegate
     * @return ResponseInterface
     */
    public function __invoke(ServerRequestInterface $request, DelegateInterface $delegate)
    {
        $response = $delegate->process($request);
        return $response->withHeader('X-Clacks-Overhead', 'GNU Terry Pratchett');
    }
}
```
