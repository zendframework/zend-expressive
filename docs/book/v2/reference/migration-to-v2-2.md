# Migration to Expressive 2.2

Version 2.2 exists to message deprecated functionality, and to provide backports
of functionality from version 3.0 as it makes sense. In most cases, your code
should continue to work as it did before, but may now emit deprecation notices.
This document details some specific deprecations, and how you can change your
code to remove the messages, and, simultaneously, help prepare your code for
version 3.

## Config providers

The zend-expressive and zend-expressive-router packages now expose _config
providers_. These are dedicated classes that return package-specific
configuration, including dependency information. We suggest you add these to
your application's configuration. Add the following two lines in your
`config/config.php` file, inside the array passed to the `ConfigAggregator`
constructor:

```php
\Zend\Expressive\ConfigProvider::class,
\Zend\Expressive\Router\ConfigProvider::class,
```

> The command `./vendor/bin/expressive migrate:expressive-v2.2` will do this for
> you.

## Routing and dispatch middleware

In previous releases of Expressive, you would route your routing and dispatch
middleware using the following dedicated methods:

```php
$app->pipeRoutingMiddleware();
$app->pipeDispatchMiddleware();
```

These methods are now _deprecated_, and will be removed in version 3.0.

Instead, you should use `pipe()` with the following services:

```php
$app->pipe(\Zend\Expressive\Router\Middleware\RouteMiddleware::class);
$app->pipe(\Zend\Expressive\Router\Middleware\DispatchMiddleware::class);
```

> The command `./vendor/bin/expressive migrate:expressive-v2.2` will do this for
> you.

This also means you can easily replace these middleware with your own at this
time!

## Routing and dispatch constants

If you are using configuration-driven routes, you are likely using the constants
`Zend\Expressive\Application::ROUTING_MIDDLEWARE` and `DISPATCH_MIDDLEWARE` to
indicate the routing and dispatch middleware, as follows:

```php
'middleware_pipeline' => [
    Application::ROUTING_MIDDLEWARE,
    Application::DISPATCH_MIDDLEWARE,
],
```

In the above section, we detailed deprecation of the methods
`pipeRoutingMiddleware()` and `pipeDispatchMiddleware()`; the constants above
are the configuration equivalent of calling these methods, and are similarly
deprecated.

Change these entries to use the same syntax as other pipeline middleware, and
have the `middleware` key indicate the appropriate middleware class as follows:

```php
'middleware_pipeline' => [
    [
        'middleware' => \Zend\Expressive\Router\Middleware\RouteMiddleware::class,
    ],
    [
        'middleware' => \Zend\Expressive\Router\Middleware\DispatchMiddleware::class,
    ],
],
```

## Implicit HEAD and OPTIONS middleware

These middleware have moved to the zend-expressive-router package. While they
still exist within the zend-expressive package, we have added deprecation
notices indicating their removal in v3. As such, update either of the following
statements, if they exist in your application:

```php
$app->pipe(\Zend\Expressive\Middleware\ImplicitHeadMiddleware::class);
$app->pipe(\Zend\Expressive\Middleware\ImplicitOptionsMiddleware::class);
```

to:

```php
$app->pipe(\Zend\Expressive\Router\Middleware\ImplicitHeadMiddleware::class);
$app->pipe(\Zend\Expressive\Router\Middleware\ImplicitOptionsMiddleware::class);
```

> The command `./vendor/bin/expressive migrate:expressive-v2.2` will do this for
> you.

## Response prototypes

A number of services expect a _response prototype_ which will be used in order
to generate and return a response. Previously, we did not expose a service for
this, and instead hard-coded factories to create a zend-diactoros `Response`
instance when creating a service.

In version 3, we plan to instead compose a _response factory_ in such services.
This is done to ensure a unique response prototype instance is generated for
each use; this is particularly important if you wish to use such services with
async web servers such as Swoole, ReactPHP, AMP, etc.

To prepare for that, Expressive 2.2 does the following:

- Creates `Zend\Expressive\Container\ResponseFactoryFactory`, and maps it to the
  service name `Psr\Http\Response\ResponseInterface`. It returns a _callable_
  that will generate a zend-diactoros `Response` instance each time it is
  called.

- Creates `Zend\Expressive\Container\StreamFactoryFactory`, and maps it to the
  service name `Psr\Http\Response\StreamInterface`. It returns a _callable_
  that will generate a zend-diactoros `Stream` instance (backed by a read/write
  `php://temp` stream) each time it is called.

The various factories that hard-coded generation of a response previously now
pull the `ResponseInterface` service and, if it is callable, call it to produce
a response, but otherwise use the return value.

This change should not affect most applications, _unless they were defining a
`ResponseInterface` service previously_. In such cases, ensure your factory
mapping has precedence by placing it in a `config/autoload/` configuration file.

## Double-Pass middleware

_Double-pass middleware_ refers to middleware that has the following signature:

```php
function (
    ServerReqeustInterface $request,
    ResponseInterface $response,
    callable $next
) : ResponseInterface
```

where `$next` will receive _both_ a request _and_ a response instance (this
latter is the origin of the "double-pass" phrasing).

Such middleware was used in v1 releases of Expressive, and we have continued to
support it through v2. However, starting in v3, we will no longer allow you to
directly pipe or route such middleware.

If you need to continue using such middleware, you will need to decorate it
using `Zend\Stratigility\Middleware\DoublePassMiddlewareDecorator()`. This
decorator class accepts the middleware and a response prototype as constructor
arguments, and decorates it to be used as http-interop middleware. (In version
3, it will decorate it as PSR-15 middleware.)

The zend-stratigility package provides a convenience function,
`Zend\Stratigility\doublePassMiddleware()`, to simplify this for you:

```php
use Zend\Diactoros\Response;
use function Zend\Stratigility\doublePassMiddleware;

// Piping:
$app->pipe(doublePassMiddleware($someMiddleware, new Response()));

// Routing:
$app->get('/foo', doublePassMiddleware($someMiddleware, new Response()));
```

## Other deprecations

The following classes, traits, and instance methods were deprecated, and will be
removed in version 3:

- `Zend\Expressive\AppFactory`: if you are using this, you will need to switch
  to direct usage of `Zend\Expressive\Application` or a
  `Zend\Stratigility\MiddlewarePipe` instance.

- `Zend\Expressive\Application`: deprecates the following methods:
  - `pipeRoutingMiddleware()`: [see the section above](#routing-and-dispatch-middleware)
  - `pipeDispatchMiddleware()`: [see the section above](#routing-and-dispatch-middleware)
  - `getContainer()`: this method is removed in version 3; container access will only be via the bootstrap.
  - `getDefaultDelegate()`: the concept of a default delegate is removed in version 3.
  - `getEmitter()`: emitters move to a different collaborator in version 3.
  - `injectPipelineFromConfig()` andd `injectRoutesFromConfig()` are methods
    defined by the `ApplicationConfigInjectionTrait`, which will be removed in
    version 3. See the section on the [ApplicationConfigInjectionDelegator](#applicationconfiginjectiondelegator)
    for an alternate, forwards-compatible, approach.

- `Zend\Expressive\ApplicationConfigInjectionTrait`: if you are using it, it is
  marked internal, and deprecated; it will be removed in version 3.

- `Zend\Expressive\Container\NotFoundDelegateFactory`: the `NotFoundDelegate`
  will be renamed to `Zend\Expressive\Handler\NotFoundHandler` in version 3,
  making this factory obsolete.

- `Zend\Expressive\Delegate\NotFoundDelegate`: this class becomes
  `Zend\Expressive\Handler\NotFoundHandler` in v3, and the new class is added in
  version 2.2 as well.

- `Zend\Expressive\Emitter\EmitterStack`: the emitter concept is extracted from
  zend-diactoros to a new component, zend-httphandlerrunner. This latter
  component is used in version 3, and defines the `EmitterStack` class. Unless
  you are extending it or interacting with it directly, this change should not
  affect you; the `Zend\Diactoros\Response\EmitterInterface` service will be
  directed to the new class in that version.

- `Zend\Expressive\IsCallableInteropMiddlewareTrait`: if you are using it, it is
  marked internal, and deprecated; it will be removed in version 3.

- `Zend\Expressive\MarshalMiddlewareTrait`: if you are using it, it is marked
  internal, and deprecated; it will be removed in version 3.

- `Zend\Expressive\Middleware\DispatchMiddleware`: [see the section above](#routing-and-dispatch-middleware).

- `Zend\Expressive\Middleware\ImplicitHeadMiddleware`: [see the section above](#implicit-head-and-options-middleware).

- `Zend\Expressive\Middleware\ImplicitOptionsMiddleware`: [see the section above](#implicit-head-and-options-middleware).

- `Zend\Expressive\Middleware\NotFoundHandler`: this will be removed in version 3, where you can instead pipe `Zend\Expressive\Handler\NotFoundHandler` directly instead.

- `Zend\Expressive\Middleware\RouteMiddleware`: [see the section above](#routing-and-dispatch-middleware).

## ApplicationConfigInjectionDelegator

In addition to the above deprecations, we also provide a new class,
`Zend\Expressive\Container\ApplicationConfigInjectionDelegator`. This class
services two purposes:

- It can act as a [delegator factory](../features/container/delegator-factories.md)
  for the `Zend\Expressive\Application` service; when enabled, it will
  look for `middleware_pipeline` and `routes` configuration, and use them to
  inject the `Application` instance before returning it.
- It defines static methods for injecting pipelines and routes to an
  `Application` instance.

To enable the delegator as a delegator factory, add the following configuration
to a `config/autoload/` configuration file, or a configuration provider class:

```php
'dependencies' => [
    'delegators' => [
        \Zend\Expressive\Application::class => [
            \Zend\Expressive\Container\ApplicationConfigInjectionDelegator::class,
        ],
    ],
],
```

To manually inject an `Application` instance, you can do the following:

```php
use Zend\Expressive\Container\ApplicationConfigInjectionDelegator;

// assuming $config is the application configuration:
ApplicationConfigInjectionDelegator::injectPipelineFromConfig($app, $config);
ApplicationConfigInjectionDelegator::injectRoutesFromConfig($app, $config);
```

These changes will be forwards-compatible with version 3.
