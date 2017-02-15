# Migration to Expressive 1.1

Expressive 1.1 should not result in any upgrade problems for users. However,
starting in this version, we offer a few changes affecting the following that
you should be aware of, and potentially update your application to adopt:

- Deprecations
- Original request and response messages
- Recommendation to use programmatic pipelines
- Error handling

## Deprecations

The following classes and/or methods are deprecated with the 1.1.0 release, and
will be removed for the 2.0 release:

- `Zend\Expressive\Application::pipeErrorHandler()`: Stratigility v1 error
  middleware are removed in the Stratigility v2 release, which Expressive 2.0 will
  adopt.

- `Zend\Expressive\Application::routeMiddleware()`: routing middleware moves to
  a dedicated class starting in Expressive 2.0. If you were referencing the
  method in order to pipe it as middleware, use `pipeRoutingMiddleware()` or
  `pipe(ApplicationFactory::ROUTING_MIDDLEWARE)` instead.

- `Zend\Expressive\Application::dispatchMiddleware()`: dispatch middleware moves
  to a dedicated class starting in Expressive 2.0.If you were referencing the
  method in order to pipe it as middleware, use `pipeDispatchMiddleware()` or
  `pipe(ApplicationFactory::DISPATCH_MIDDLEWARE)` instead.

- `Zend\Expressive\Application::getFinalHandler()`: this method gets renamed to
  `getDefaultDelegate()` in Expressive 2.0. We recommend retrieving the value
  from the application dependency injection container if you need it elsewhere.

- `Zend\Expressive\Application::raiseThrowables()`: this method becomes a no-op
  in Stratigility 2.0, on which Expressive 2.0 is based; the behavior it enabled
  becomes the default behavior in that version.

- `Zend\Expressive\Container\Exception\InvalidArgumentException`: this exception
  type is thrown by `ApplicationFactory`; in Expressive 2.0, it throws
  `Zend\Expressive\Exception\InvalidArgumentException` instead.

- `Zend\Expressive\Container\Exception\NotFoundException`: this exception type
  is not currently used anyways.

- `Zend\Expressive\ErrorMiddlewarePipe`: Stratigility v1 error middleware are
  removed in the Stratigility v2 release, which Expressive 2.0 will adopt,
  making this specialized middleware pipe type irrelvant.

- `Zend\Expressive\TemplatedErrorHandler` and `Zend\Expressive\WhoopsErrorHandler`:
  The concept of "final handlers" will be removed in Expressive 2.0, to be
  replaced with "default delegates" (implementations of
  `Interop\Http\ServerMiddleware\DelegateInterface` that will be called if the
  middleware pipeline is exhausted, and which will be guaranteed to return a
  response). Expressive 2.0 will provide tooling to upgrade your dependencies to
  make the transition seamless; end users will only be affected if they were
  extending these classes.

If you were calling any of these directly, or extending or overriding them, you
will need to update your code to work for version 2.0. We recommend not using
these.

## Original messages

Stratigility 1.3 deprecates its internal request and response decorators,
`Zend\Stratigility\Http\Request` and `Zend\Stratigility\Http\Response`,
respectively. The main utility of these instances was to provide access in
inner middleware layers to the original request, original response, and original
URI.

As such access may still be desired, Stratigility 1.3 introduced
`Zend\Stratigility\Middleware\OriginalMessages`. This middleware injects the
following attributes into the request it passes to `$next()`:

- `originalRequest` is the request instance provided to the middleware.
- `originalUri` is the URI instance associated with that request.
- `originalResponse` is the response instance provided to the middleware.

`Zend\Stratigility\FinalHandler` was updated to use these when they're
available starting with version 1.0.3.

We recommend adding the `OriginalMessages` middleware as the outermost (first)
middleware in your pipeline. Using configuration-driven middleware, that would
look like this:

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

If you are [programmatically creating your pipeline](https://mwop.net/blog/2016-05-16-programmatic-expressive.html),
use the following:

```php
$app->pipe(OriginalMessages::class);
/* all other middleware */
```

### Identifying and fixing getOriginal calls

To help you identify and update calls in your own code to the `getOriginal*()`
methods, we provide a tool via the [zendframework/zend-expressive-tooling](https://github.com/zendframework/zend-expressive-tooling)
package, `vendor/bin/expressive-migrate-original-messages`.

First, install the tooling package; since the tooling it provides is only
useful during development, install it as a development requirement:

```bash
$ composer require --dev zendframework/zend-expressive-tooling
```

Once installed,  you can execute the tool using:

```bash
$ ./vendor/bin/expressive-migrate-original-messages
```

Passing the arguments `help`, `--help`, or `-h` will provide usage information;
in most cases, it will assume sane defaults in order to run its scans.

The tool updates calls to `getOriginalRequest()` and `getOriginalUri()` to
instead use the new request attributes that the `OriginalMessages` middleware
injects:

- `getOriginalRequest()` becomes `getAttribute('originalRequest', $request)`
- `getOriginalUri()` becomes `getAttribute('originalUri', $request->getUri())`

In both cases, `$request` will be replaced with whatever variable name you used
for the request instance.

For `getOriginalResponse()` calls, which happen on the response instance, the
tool will instead tell you what files had such calls, and detail how you can
update those calls to use the `originalResponse` request attribute.

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
  instead adapting your application to use [standard middleware for error
  handling](#error-handling). Otherwise, it acts just like `pipe()`.
  Starting in Expressive 1.1, this method will emit a deprecation notice.

As an example pipeline:

```php
$app->pipe(OriginalMessages::class);
$app->pipe(Helper\ServerUrlMiddleware::class);
$app->pipe(ErrorHandler::class);
$app->pipeRoutingMiddleware();
$app->pipe(Helper\UrlHelperMiddleware::class);
$app->pipeDispatchMiddleware();
$app->pipe(NotFoundHandler::class);
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
$app->get('/api/ping', \App\Action\PingAction::class, 'api.ping');
```

We recommend rewriting your middleware pipeline and routing configuration into
programmatic/declarative statements. Specifically:

- We recommend putting the pipeline declarations into `config/pipeline.php`.
- We recommend putting the routing declarations into `config/routes.php`.

Once you've written these, you will then need to make the following changes to
your application:

- First, enable the `zend-expressive.programmatic_pipeline` configuration flag.
  This can be done in any `config/autoload/*.global.php` file:

  ```php
  return [
      'zend-expressive' => [
          'programmatic_pipeline' => true,
      ],
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

For programmatic pipelines to work properly, you will also need to provide error
handling middleware, which is discussed in the next section.

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

- The "Final Handler". This is a handler invoked when the middleware pipeline is
  exhausted without returning a response, and has the signature `function
  (ServerRequestInterface $request, ResponseInterface $response, $err = null)`;
  it is provided to the middleware pipeline when invoking the outermost
  middleware; in the case of Expressive, it is composed in the `Application`
  instance, and passed to the application middleware when it executes `run()`.
  When invoked, it needs to decide if invocation is due to no middleware
  executing (HTTP 404 status), middleware calling `$next()` with an altered
  response (response is then returned), or due to invocation of error middleware
  (calling `$next()` with the third, error, argument) with no error middleware
  returning a response.

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

When enabled, the internal dispatcher will no longer catch exceptions.

This both allows you to, and _requires_ you to, write your own error handling
middleware. This will require two things:

- Middleware with a try/catch block that operates as the outermost (or close to
  outermost) layer of your application, and which can provide error pages or
  details to your end users.
- Middleware at the innermost layer that is guaranteed to return a response;
  generally, reaching this means no middleware was able to route the request, and
  thus a 404 condition.

The below sections detail approaches to each.

### Error handling middleware

Error handling middleware generally will look something like this:

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

Stratigility's `ErrorHandler` allows injection of an "error response generator",
which allows you to alter how the error response is generated based on the
current environment. Error response generators are callables with the signature:

```php
function (
    Throwable|Exception $e,
    ServerRequestInterface $request,
    ResponseInterface $response
) : ResponseInterface
```

We recommend using the Stratigility `ErrorHandler` and writing and attaching a
custom error response generator. As a simple example, the following details a
generator that will use a template to display an error page:

```php
namespace Acme;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Zend\Expressive\Template\TemplateRendererInterface;

class TemplatedErrorResponseGenerator
{
    const TEMPLATE_DEFAULT = 'error::error';

    private $renderer;

    private $template;

    public function __construct(
        TemplateRendererInterface $renderer,
        $template = TEMPLATE_DEFAULT
    ) {
        $this->renderer = $renderer;
        $this->template = $template;
    }

    public function __invoke(
        $e,
        ServerRequestInterface $request,
        ResponseInterface $response
    ) {
        $response->write($this->renderer->render($this->template, [
            'exception' => $e,
            'request'   => $request,
        ]));
        return $response;
    }
}
```

You might then create a factory for generating the `ErrorHandler` and attaching
this response generator as follows:

```php
namespace Acme\Container;

use Acme\TemplatedErrorResponseGenerator;
use Psr\Container\ContainerInterface;
use Zend\Diactoros\Response;
use Zend\Expressive\Template\TemplateRendererInterface;
use Zend\Stratigility\Middleware\ErrorHandler;

class ErrorHandlerFactory
{
    public function __invoke(ContainerInterface $container)
    {
        $generator = new TemplatedErrorResponseGenerator(
            $container->get(TemplateRendererInterface::class)
        );

        return new ErrorHandler(new Response(), $generator);
    }
}
```

Once that is created you can tell your middleware configuration about it:

```php
// in config/autoload/middleware-pipeline.global.php
use Acme\Container\ErrorHandlerFactory;
use Zend\Stratigility\Middleware\ErrorHandler;

return [
    'dependencies' => [
        /* ... */
        'factories' => [
            ErrorHandler::class => ErrorHandlerFactory::class,
            /* ... */
        ],
        /* ... */
    ],
    'middleware_pipeline' => [
        'always' => [
            'middleware' => [
                ErrorHandler::class,
                /* ... */
            ],
            'priority' => 10000,
        ],
        /* ... */
    ],
];
```

Alternately, if using a programmatic pipeline, as detailed in the previous
section, you can use the following:

```php
use Zend\Stratigility\Middleware\ErrorHandler;

$app->pipe(ErrorHandler::class);
// add all other middleware after it
```

### Not Found middleware

At the innermost layer of your application, you need middleware guaranteed to
return a response; typically, this indicates a failure to route the request,
and, as such, an HTTP 404 response.  `Zend\Stratigility\Middleware\NotFoundHandler`
provides an implementation, but is written such that the response body remains
empty. As such, you might write a custom, templated handler:

```php
namespace Acme;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Zend\Diactoros\Response;
use Zend\Expressive\Template\TemplateRendererInterface;

class TemplatedNotFoundHandler
{
    const TEMPLATE_DEFAULT = 'error::404';

    private $renderer;

    private $template;

    public function __construct(
        TemplateRendererInterface $renderer,
        $template = self::TEMPLATE_DEFAULT
    ) {
        $this->renderer = $renderer;
        $this->template = $template;
    }

    public function __invoke(
        ServerRequestInterface $request,
        ResponseInterface $response,
        callable $next
    ) {
        $response = new Response();
        $response->write($this->renderer->render($this->template));
        return $response->withStatus(404);
    }
}
```

Similar to the discussion of the `ErrorHandler` above, we'll create a factory
for this:

```php
namespace Acme\Container;

use Acme\TemplatedNotFoundHandler;
use Psr\Container\ContainerInterface;
use Zend\Expressive\Template\TemplateRendererInterface;

class TemplatedNotFoundHandlerFactory
{
    public function __invoke(ContainerInterface $container)
    {
        return new TemplatedNotFoundHandler(
            $container->get(TemplateRendererInterface::class)
        );
    }
}
```

We can then register it in our pipeline:

```php
// in config/autoload/middleware-pipeline.global.php
use Acme\Container\NotFoundHandlerFactory;
use Acme\TemplatedNotFoundHandler;

return [
    'dependencies' => [
        /* ... */
        'factories' => [
            TemplatedNotFoundHandler::class => TemplatedNotFoundHandlerFactory::class,
            /* ... */
        ],
        /* ... */
    ],
    'middleware_pipeline' => [
        /* ... */

        // After 'routing', but before 'error';
        // alternately as last item in 'routing' middleware list.
        'not-found' => [
            'middleware' => TemplatedNotFoundHandler::class,
            'priority' => 0,
        ],

        /* ... */
    ],
];
```

If you are using programmatic pipelines, as described in the previous section:

```php
use Acme\TemplatedNotFoundHandler;

// all other pipeline directives, and then:
$app->pipe(TemplatedNotFoundHandler::class);
```

### Detecting error middleware usage

If you use the new error handling paradigm, we recommend that you also audit
your application for legacy Stratigility error middleware, as well as invocation
of error middleware. To do this, we provide a tool via the
[zendframework/zend-expressive-tooling](https://github.com/zendframework/zend-expressive-tooling)
package, `vendor/bin/expressive-scan-for-error-middleware`.

First, install the tooling as a development requirement:

```bash
$ composer require --dev zendframework/zend-expressive-tooling
```

The tool will scan the `src/` directory by default, but allows you to scan other
directories via the `--dir` flag. It will detect and report files with any of
the following:

- Classes implementing `Zend\Stratigility\ErrorMiddlewareInterface`.
- Invokable classes implementing the error middleware signature.
- Methods accepting `$next` that invoke it with an error argument.

As an example running it:

```bash
$ ./vendor/bin/expressive-scan-for-error-middleware scan
# or, with a directory argument:
$ ./vendor/bin/expressive-scan-for-error-middleware scan --dir ./lib
```

You may also call the tool using its `help` command, or either of the `--help`
or `-h` flags to get full usage information.

Use this tool to identify potential problem areas in your application, and
update your code to use the new error handling facilities as outlined above.

## Full example

Putting all of the above together &mdash; [original message
memoizing](#original-messages), [programmatic
pipelines](#programmatic-middleware-pipelines), and [middleware-based error
handling](#error-handling) &mdash; might look like the following examples.

First, we'll tell Expressive to use programmatic pipelines, and to enable the
new error handling (by telling it to "raise throwables", instead of catching
them):

```php
// In config/autoload/zend-expressive.global.php:
return [
    /* ... */
    'zend-expressive' => [
        'programmatic_pipeline' => true,
        'raise_throwables' => true,
        /* ... */
    ],
];
```

Next, we'll update `config/autoload/middleware-pipeline.global.php` to list only
dependencies:

```php
use Acme\Container;
use Acme\TemplatedNotFoundHandler;
use Zend\Expressive\Container\ApplicationFactory;
use Zend\Expressive\Helper;
use Zend\Stratigility\Middleware\ErrorHandler;
use Zend\Stratigility\Middleware\OriginalMessages;

return [
    'dependencies' => [
        'invokables' => [
            OriginalMessages::class => OriginalMesssages::class,
        ],
        'factories' => [
            ErrorHandler::class => Container\ErrorHandlerFactory::class,
            Helper\ServerUrlMiddleware::class => Helper\ServerUrlMiddlewareFactory::class,
            Helper\UrlHelperMiddleware::class => Helper\UrlHelperMiddlewareFactory::class,
            TemplatedNotFoundHandler::class => Container\TemplatedNotFoundHandlerFactory::class,
        ],
    ],
];
```

We'll also update `config/autoload/routes.global.php` to only list dependencies;
in the following example, we list only the middleware shipped by default with
the skeleton application:

```php
use App\Action;
use Zend\Expressive\Router\FastRouteRouter;
use Zend\Expressive\Router\RouterInterface;

return [
    'dependencies' => [
        'invokables' => [
            RouterInterface::class => FastRouteRouter::class,
            Action\PingAction::class => Action\PingAction::class,
        ],
        'factories' => [
            Action\HomePageAction::class => Action\HomePageFactory::class,
        ],
    ],
];
```

To create our pipeline, we will create the file `config/pipeline.php`:

```php
use Acme\TemplatedNotFoundHandler;
use Zend\Expressive\Container\ApplicationFactory;
use Zend\Expressive\Helper;
use Zend\Stratigility\Middleware\ErrorHandler;
use Zend\Stratigility\Middleware\OriginalMessages;

$app->pipe(OriginalMessages::class);
$app->pipe(ErrorHandler::class);
$app->pipe(Helper\ServerUrlMiddleware::class);
$app->pipe([
    ApplicationFactory::ROUTING_MIDDLEWARE,
    Helper\UrlHelperMiddleware::class,
    ApplicationFactory::DISPATCH_MIDDLEWARE,
]);
$app->pipe(TemplatedNotFoundHandler::class);
```

Note that you can use _arrays_ of middleware just like you did in the
configuration; this allows you to separate middleware into logical groups if
desired!

To provide our routed middleware, we will create the file
`config/pipeline.php`:

```php
use App\Action;

$app->get('/', Action\HomePageAction::class, 'home');
$app->get('/api/ping', Action\PingAction::class, 'api.ping');
```

The above exercises the various routing methods of the `Application` class.

Finally, we will need to update our `public/index.php`, to tell it to require
our new pipeline and routing files; we'll do that between retrieving the
application from the container, and running the application:

```php
$app = $container->get(\Zend\Expressive\Application::class);
require 'config/pipeline.php';
require 'config/routes.php';
$app->run();
```

With these changes in place, your application should continue to run as it did
previously!

## Looking forward

Expressive 2.0 will ship error handling middleware and "not found" middleware,
as well as tools to convert your application to a programmatic pipeline in such
a way as to utilize these shipped implementations. In the meantime, however, you
can adopt programmatic pipelines and the new error handling paradigm within the
version 1 series using the configuration flags and guidelines listed above in
order to make your application forwards-compatible.

