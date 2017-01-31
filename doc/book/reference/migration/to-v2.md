# Migration to Expressive 2.0

Expressive 2.0 should not result in many upgrade problems for users. However,
starting in this version, we offer a few changes affecting the following that
you should be aware of, and potentially update your application to adopt:

- Original request and response messages
- Error handling
- Programmatic middleware pipelines
- Usage of [http-interop middleware](https://github.com/http-interop/http-middleware)
- Implicit handling of `HEAD` and `OPTIONS` requests

## Original messages

In the [migration to version 1.1 guide](to-v1-1.md), we detail the fact that
Stratigility 1.3 deprecated its internal request and response decorators.
Stratigility 2.0, on which Expressive 2.0 is based, removes them entirely.

If your code relied on the various `getOriginal*()` methods those decorators
exposed, you will need to update your code in two ways:

- You will need to add `Zend\Stratigility\Middleware\OriginalMessages` to your
  middleware pipeline, as the outermost (or close to outermost) layer.
- You will need to update your code to call on the request instance's
  `getAttribute()` method with one of `originalRequest`, `originalUri`, or
  `originalResponse` to retrieve the values.

To address the first point, see the [Expressive 1.1 migration
documentation](to-v1-1.md#original-messages), which details how to update your
configuration or programmatic pipeline.

For the second point, we provide a tool via the
[zendframework/zend-expressive-tooling](https://github.com/zendframework/zend-expressive-tooling)
package which will help you in this latter part of the migration. Install it as
a development requirement via composer:

```bash
$ composer require --dev zendframework/zend-expressive-tooling
```

And then execute it via:

```bash
$ ./vendor/bin/expressive-migrate-original-messages
```

This tool will update calls to `getOriginalRequest()` and `getOriginalUri()` to
instead use the new request attributes that the `OriginalMessages` middleware
injects:

- `getOriginalRequest()` becomes `getAttribute('originalRequest', $request)`
- `getOriginalUri()` becomes `getAttribute('originalUri', $request->getUri())`

In both cases, `$request` will be replaced with whatever variable name you used
for the request instance.

For `getOriginalResponse()` calls, which happen on the response instance, the
tool will instead tell you what files had such calls, and detail how you can
update those calls to use the `originalResponse` request attribute.

## Error handling

As noted in the [Expressive 1.1 migration docs](to-v1-1.md#error-handling),
Stratigility 1.3 introduced the ability to tell it to no longer catch exceptions
internally, paving the way for middleware-based error handling. Additionally, it
deprecated its own `ErrorMiddlewareInterface` and duck-typed implementations of
the interface in favor of middleware-based error handling. Finally, it
deprecated the `$e`/`$error` argument to "final handlers", as that argument
would be used only when attempting to invoke `ErrorMiddlewareInterface`
instances.

Stratigility 2.0, on which Expressive 2.0 is based, no longer catches exceptions
internally, removes the `ErrorMiddlewareInterface` entirely, and thus the
`$e`/`$error` argument to final handlers.

As such, you **MUST** provide your own error handling with Expressive 2.0.

Error handling middleware will typically introduce a try/catch block:

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

Additionally, you will need middleware registered as your innermost layer that
is guaranteed to return a response. Generally, if you hit that layer, no other
middleware is capable of handling the request, indicating a 400 (Bad Request) or
404 (Not Found) HTTP status. With the combination of an error handler at the
outermost layer, and a "not found" handler at the innermost layer, you can
handle any error in your application.

Stratigility 1.3 and 2.0 provide an error handler implementation via
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

Expressive 2.0 provides the following functionality to assist with your error
handling needs:

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

- `Zend\Expressive\Container\ErrorResponseGeneratorFactory` can create an
  instance of the `ErrorResponseGenerator` using the following:

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
  the `ServerRequestInterface` instance.<br/><br/>
  `Zend\Expressive\Container\WhoopsErrorResponseGeneratorFactory` can create an
  instance, and will use the `Zend\Expressive\Whoops` service to seed the
  `Whoops\Run` argument.

- `Zend\Expressive\Middleware\NotFoundHandler` can be used as the innermost
  layer of your pipeline in order to return a 404 response. (Typically, if you
  get to the innermost layer, no middleware was able to handle the request,
  indicating a 404.) By default, it will produce a canned plaintext response.
  However, you can also provide an optional `TemplateRendererInterface` instance
  and `$template` in order to provided templated content.<br/><br/>
  The constructor arguments are:

    - `ResponseInterface $responsePrototype`: this is an empty response on which
      to set the 404 status and inject the 404 content.

    - `TemplateRendererInterface $renderer`: optionally, you may provide a
      renderer to use in order to provide templated response content.

    -  $template = 'error::404'`: optionally, you may provide a
      template to render; if none is provided, a sane default is used.

- `Zend\Expressive\Container\NotFoundHandlerFactory` can create an instance of
  the `NotFoundHandler` for you, and will use the following to do so:

    - The `Zend\Expressive\Template\TemplateRendererInterface` service, if
      available.

    - The `zend-expressive.error_handler.template_404` configuration value, if
      available, will be used for the `$template`.

- `Zend\Expressive\Container\ErrorHandlerFactory` will create an instance of
  `Zend\Stratigility\Middleware\ErrorHandler`, and use the
  `Zend\Stratigility\Middleware\ErrorResponseGenerator` service to seed
  it.<br/><br/>
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
            Middleware\ErrorResponseGenerator::class => Container\ErrorResponseGeneratorFactory::class,
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

This can be problematic when deprecation errors are triggered.  If they are cast
to exceptions, code that would normally run will now result in error pages.

We recommend adding the following line to your `public/index.php` towards the
top of the file:

```php
error_reporting(error_reporting() & ~E_USER_DEPRECATED);
```

This will prevent the error handler from casting deprecation notices to
exceptions, while keeping the rest of your error reporting mask intact.

### Removing legacy error middleware

Stratigility version 1-style error middleware (middleware implementing
`Zend\Stratigility\ErrorMiddlewareInterface`, or duck-typing its signature,
which included an `$error` argument as the first argument to the middleware) is
no longer supported with Stratigility version 2 and Expressive 2.0. You will
need to find any instances of them in your application, or cases where your
middleware invokes error middleware via the third argument to `$next()`.

We provide a tool to assist you with that via the package 
[zendframework/zend-expressive-tooling](https://github.com/zendframework/zend-expressive-tooling):
`vendor/bin/expressive-scan-for-error-middleware`. Run the command from your
project root, optionally passing the `help`, `--help`, or `-h` commands for
usage. The tool will detect each of these for you, flagging them for you to
update or remove.

## Programmatic middleware pipelines

Starting with Expressive 1.1, we recommended *programmatic creation of
pipelines and routing*; the [Expressive 1.1 migration
guide](to-v1-1.md#programmatic-middleware-pipelines) provides more detail.

With Expressive 2.0, this is now the _default_ option shipped in the skeleton.

If you are upgrading from version 1 and are not currently using programmatic
pipelines, we provide a migration tool that will convert your application to do
so. The tool is available via the package
[zendframework/zend-expressive-tooling](https://github.com/zendframework/zend-expressive-tooling).
You may install this package in one of the following ways:

- Via the vendor binary `./vendor/bin/expressive-tooling`:

  ```bash
  $ ./vendor/bin/expressive-tooling        # install
  $ ./vendor/bin/expressive-tooling remove # uninstall
  ```

- Using Composer:

  ```bash
  $ composer require --dev zendframework/zend-expressive-tooling # install
  $ composer remove --dev zendframework/zend-expressive-tooling  # uninstall
  ```

Once installed, you will use the `vendor/bin/expressive-pipeline-from-config`
command.

This command does the following:

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
  which enables the `programmatic_pipelines` configuration flag. Additionally,
  it adds dependency configuration for the new error handlers.

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
  that middleware re-throw for exceptions it cannot handle. (Use the
  `vendor/bin/expressive-scan-for-error-middleware` command from
  zendframework/zend-expressive-tooling to assist in this.)

- Consider providing your own `Zend\Stratigility\NoopFinalHandler`
  implementation; this will now only be invoked if the queue is exhausted, and
  could return a generic 404 page, raise an exception, etc.

## http-interop

Stratigility 2.0 provides the ability to work with [http-interop middleware
0.4.1](https://github.com/http-interop/http-middleware/tree/0.4.1).

This specification, which is being developed as the basis of
[PSR-15](https://github.com/php-fig/fig-standards/tree/master/proposed/http-middleware),
defines what is known as _lambda_ or _single-pass_ middleware, vs the
_double-pass_ middleware traditionally used by Stratigility and Expressive.

Double-pass refers to the fact that two arguments are passed to the delegation
function `$next`: the request and response. Lambda or single-pass middleware
only pass a single argument, the request.

Stratigility 2.0 provides support for dispatching either style of middleware.

Specifically, your middleware can now implement:

- `Interop\Http\ServerMiddleware\MiddlewareInterface`, which defines a single
  method, `process(Psr\Http\Message\ServerRequestInterface $request,
  Interop\Http\ServerMiddleware\Interface $delegate)`.
- Callable middleware that follows the above signature (the typehint for the
  request argument is optional).
  
Both styles of middleware may be piped directly to the middleware pipeline or as
routed middleware within Expressive. In each case, you can invoke the
next middleware layer using `$delegate->process($request)`.

In Expressive 2.0, `Application` will continue to accept the legacy double-pass
signature, but will require that you either:

- Provide a `$responsePrototype` (a `ResponseInterface` instance) to the
  `Application` instance prior to piping or routing such middleware.
- Decorate the middleware in a `Zend\Stratigility\Middleware\CallableMiddlewareWrapper`
  instance (which also requires a `$responsePrototype`).

If you use `Zend\Expressive\Container\ApplicationFactory` to create your
`Application` instance, a response prototype will be injected for you from the
outset.

We recommend that you begin writing middleware to follow the http-interop
standard at this time. As an example:

```php
namespace App\Middleware;

use Interop\Http\ServerMiddleware\DelegateInterface;
use Interop\Http\ServerMiddleware\MiddlewareInterface;
use Psr\Http\Message\ServerRequestInterface;

class XClacksOverheadMiddleware implements MiddlewareInterface
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

use Interop\Http\ServerMiddleware\DelegateInterface;
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

## Handling HEAD and OPTIONS requests

Prior to 2.0, it was possible to route middleware that could not handle `HEAD`
and/or `OPTIONS` requests. Per [RFC 7231, section 4.1](https://tools.ietf.org/html/rfc7231#section-4.1),
"all general-purpose servers MUST support the methods GET and HEAD. All other
methods are OPTIONAL." Additionally, most servers and implementors agree that
`OPTIONS` _should_ be supported for any given resource, so that consumers can
determine what methods are allowed for the given resource.

To make this happen, the Expressive project implemented several features.

First, zend-expressive-router 1.3.0 introduced several features in both
`Zend\Expressive\Router\Route` and `Zend\Expressive\Router\RouteResult` to help
consumers implement support for `HEAD` and `OPTIONS` in an automated way. The
`Route` class now has two new methods, `implicitHead()` and `implicitOptions()`;
these each return a boolean `true` value if support for those methods is
_implicit_ &mdash; i.e., not defined explicitly for the route. The `RouteResult`
class now introduces a new factory method, `fromRoute()`, that will create an
instance from a `Route` instance; this then allows consumers of a `RouteResult`
to query the `Route` to see if a matched `HEAD` or `OPTIONS` request needs
automated handling. Each of the supported router implementations were updated to
use this method, as well as to return a successful routing result if `HEAD`
and/or `OPTIONS` requests are submitted, but the route does not explicitly
support the method.

Within Expressive itself, we now offer two new middleware to provide this
automation:

- `Zend\Expressive\Middleware\ImplicitHeadMiddleware`
- `Zend\Expressive\Middleware\ImplicitOptionsMiddleware`

If you want to support these methods automatically, each of these should be
enabled between the routing and dispatch middleware. If you use the
`expressive-pipeline-from-config` tool as documented in the
[programmatic pipeline migration section](#migrate-to-programmatic-pipelines),
entries for each will be injected into your generated pipeline.

Please see the [chapter on the implicit methods middleware](../../features/middleware/implicit-methods-middleware.md)
for more information on each.
