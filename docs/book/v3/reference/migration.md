# Migration to Expressive 3.0

Expressive 3.0 should not result in many upgrade problems for users. However,
starting in this version, we offer a few changes affecting the following that
you should be aware of, and potentially update your application to adopt:

- [PHP 7.1 support](#php-7.1-support)
- [PSR-15 support](#psr-15-support)
- [New dependencies](#new-dependencies)
- [New features](#new-features)
- [Signature and behavior changes](#signature-and-behavior-changes)
- [Removed classes and traits](#removed-classes-and-traits)
- [Upgrading from v2](#upgrading)

## PHP 7.1 support

Starting in Expressive 3.0 we support only PHP 7.1+.

## PSR-15 Support

All middleware and delegators now implement interfaces from
[PSR-15](https://www.php-fig.org/psr/psr-15) instead of
http-interop/http-middleware (a PSR-15 precursor). This means the following
changes were made throughout Expressive:

- The `process()` method of all middleware now type hint the second argument
  against the PSR-15 `RequestHandlerInterface`, instead of the previous
  `DelegateInterface`.

- The `process()` method of all middleware now have a return type hint of
  `\Psr\Http\Message\ResponseInterface`.

- All "delegators" have become request handlers: these now implement the PSR-15
  interface `RequestHandlerInterface` instead of the former `DelegateInterface`.

- The `process()` method of handlers (formerly delegators) have been renamed to
  `handle()` and given a return type hint of
  `\Psr\Http\Message\ResponseInterface`.

This change also affects all middleware you, as an application developer, have
written, and your middleware will need to be update. We provide a tool for this
via zend-expressive-tooling. Make sure that package is up-to-date (a version 1
release should be installed), and run the following:

```php
$ ./vendor/bin/expressive migrate:interop-middleware
```

This tool will locate any http-interop middleware and update it to PSR-15
middleware.

## New dependencies

Expressive adds the following packages as dependencies:

- [psr/http-server-middleware](https://github.com/php-fig/http-server-middleware)
  provides the PSR-15 interfaces, and replaces the previous dependency on
  http-interop/http-middleware.

- [zendframework/zend-expressive-router](https://github.com/zendframework/zend-expressive-router);
  previously, we depended on this package indirectly; now it is a direct
  requirement.

- [zendframework/zend-expressive-tooling](https://github.com/zendframework/zend-expressive-tooling);
  this was suggested previously, but is now required as a development
  dependency.

- [zendframework/zend-httphandlerrunner](https://github.com/zendframework/zend-httphandlerrunner);
  this is now used for the purposes of marshaling the server request, dispatching
  the application, and emitting the response. The functionality is generalized
  enough to warrant a separate package.

## New features

The following classes were added in version 3:

- `Zend\Expressive\Container\ApplicationConfigInjectionDelegator` is a
  [delegator factory](../features/container/delegator-factories.md) capable of
  piping and routing middleware from configuration. See the [recipe on
  autowiring routes and pipeline middleware](../cookbook/autowiring-routes-and-pipelines.md)
  for more information.

- `Zend\Expressive\Container\ApplicationPipelineFactory` will produce an empty
  `MiddlewarePipe` for use with `Zend\Expressive\Application`.

- `Zend\Expressive\Container\EmitterFactory` will produce a
  `Zend\HttpHandlerRunner\Emitter\EmitterStack` instance for use with the
  `RequestHandlerRunner` instance composed by the `Application`. See the
  [chapter on emitters](../features/emitters.md) for more details.

- `Zend\Expressive\Container\MiddlewareContainerFactory` will produce a
  `MiddlewareContainer` composing the application container instance.

- `Zend\Expressive\Container\MiddlewareFactoryFactory` will produce a
  `MiddlewareFactory` composing a `MiddlewareContainer` instance.

- `Zend\Expressive\Container\RequestHandlerRunnerFactory` will produce a
  `Zend\HttpHandlerRunner\RequestHandlerRunner` instance for use with the
  `Application` instance. See the [zend-httphandlerrunner
  documentation](https://docs.zendframework.com/zend-httphandlerrunner) for more
  details on this collaborator.

- `Zend\Expressive\Container\ServerRequestErrorResponseGeneratorFactory` will
  produce a `Zend\Expressive\Response\ServerRequestErrorResponseGenerator`
  instance for use with the `RequestHandlerRunner`.

- `Zend\Expressive\Container\ServerRequestFactoryFactory` will produce a PHP
  callable capable of generating a PSR-7 `ServerRequestInterface` instance for use
  with the `RequestHandlerRunner`.

- `Zend\Expressive\MiddlewareContainer` decorates a PSR-11 container, and
  ensures that the values pulled are PSR-15 `MiddlewareInterface` instances.
  If the container returns a PSR-15 `RequestHandlerInterface`, it decorates it
  via `Zend\Stratigility\Middleware\RequestHandlerMiddleware`. All other types
  result in an exception being thrown.

- `Zend\Expressive\MiddlewareFactory` allows creation of `MiddlewareInterface`
  instances from a variety of argument types, and is used by `Application` to
  allow piping and routing to middleware services, arrays of services, and more.
  It composes a `MiddlewareContainer` internally.

- `Zend\Expressive\Response\ServerRequestErrorResponseGenerator` can act as a
  response generator for the `RequestHandlerRunner` when its composed server
  request factory raises an exception.

## Signature and behavior changes

The following signature changes were made that could affect _class extensions_
and/or consumers.

### Application

`Zend\Expressive\Application` was refactored dramatically for version 3.

If you were instantiating it directly previously, the constructor arguments are
now, in order:

- `Zend\Expressive\MiddlewareFactory`
- `Zend\Stratigility\MiddlewarePipeInterface`
- `Zend\Expressive\Router\RouteCollector`
- `Zend\HttpHandlerRunner\RequestHandlerRunner`
- `Zend\Expressive\Application::__construct(...)`

`Application` no longer supports piping or routing to double-pass middleware. If
you continue to need double-pass middleware (e.g., defined by a third-party
library), use `Zend\Stratigility\doublePassMiddleware()` to decorate it prior to
piping or routing to it:

```php
use Zend\Diactoros\Response;
use function Zend\Stratigility\doublePassMiddleware;

$app->pipe(doublePassMiddleware($someDoublePassMiddleware, new Response()));

$app->get('/foo', doublePassMiddleware($someDoublePassMiddleware, new Response()));
```

Additionally, the following methods were **removed**:

- `pipeRoutingMiddleware()`: use `pipe(\Zend\Expressive\Router\RouteMiddleware::class)`
  instead.
- `pipeDispatchMiddleware()`: use `pipe(\Zend\Expressive\Router\DispatchMiddleware::class)`
  instead.
- `getContainer()`
- `getDefaultDelegate()`: ensure you pipe middleware or a request handler
  capable of returning a response at the innermost layer;
  `Zend\Expressive\Handler\NotFoundHandler` can be used for this.
- `getEmitter()`: use the `Zend\HttpHandlerRunner\Emitter\EmitterInterface` service from the container.
- `injectPipelineFromConfig()`: use the new `ApplicationConfigInjectionDelegator` and/or the static method of the same name it defines.
- `injectRoutesFromConfig()`: use the new `ApplicationConfigInjectionDelegator` and/or the static method of the same name it defines.

### ApplicationFactory

`Zend\Expressive\Container\ApplicationFactory` no longer looks at the
`zend-expressive.programmatic_pipeline` flag, nor does it inject pipeline
middleware and/or routed middleware from configuration any longer.

If you want to use configuration-driven pipelines and/or middleware, you may
register the new class `Zend\Expressive\Container\ApplicationConfigInjectionDelegator`
as a delegator factory on the `Zend\Expressive\Application` service.

### NotFoundHandlerFactory

`Zend\Expressive\Container\NotFoundHandlerFactory` now returns an instance of
`Zend\Expressive\Handler\NotFoundHandler`, instead of
`Zend\Expressive\Middleware\NotFoundHandler` (which has been removed).

### LazyLoadingMiddleware

`Zend\Expressive\Middleware\LazyLoadingMiddleware` now composes a
`Zend\Expressive\MiddlewareContainer` instance instead of a more general PSR-11
container; this is to ensure that the value returned is a PSR-15
`MiddlewareInterface` instance.

## Removed classes and traits

- `Zend\Expressive\AppFactory` was removed. If you were using it previously,
  either use `Zend\Expressive\Application` directly, or a
  `Zend\Stratigility\MiddlewarePipe` instance.

- `Zend\Expressive\ApplicationConfigInjectionTrait`; the functionality of this
  trait was replaced by the `Zend\Expressive\Container\ApplicationConfigInjectionDelegator`.

- `Zend\Expressive\Delegate\NotFoundDelegate`; use `Zend\Expressive\Handler\NotFoundHandler`
  instead. Its factory, `Zend\Expressive\Container\NotFoundDelegateFactory`, was
  also removed.

- `Zend\Expressive\Emitter\EmitterStack`; use `Zend\HttpHandlerRunner\Emitter\EmitterStack`
  instead.

- `Zend\Expressive\IsCallableInteropMiddlewareTrait`; there is no functional
  equivalent, nor a need for this functionality as of version 3.

- `Zend\Expressive\MarshalMiddlewareTrait`; the functionality of this trait was
  replaced by a combination of `Zend\Expressive\MiddlewareContainer` and
  `Zend\Expressive\MiddlewareFactory`.

- `Zend\Expressive\Middleware\DispatchMiddleware`; use
  `Zend\Expressive\Router\Middleware\DispatchMiddleware` instead.

- `Zend\Expressive\Middleware\ImplicitHeadMiddleware`; use
  `Zend\Expressive\Router\Middleware\ImplicitHeadMiddleware` instead.

- `Zend\Expressive\Middleware\ImplicitOptionsMiddleware`; use
  `Zend\Expressive\Router\Middleware\ImplicitOptionsMiddleware` instead.

- `Zend\Expressive\Middleware\NotFoundHandler`; use `Zend\Expressive\Handler\NotFoundHandler`
  instead.

- `Zend\Expressive\Middleware\RouteMiddleware`; use
  `Zend\Expressive\Router\Middleware\RouteMiddleware` instead.

## Upgrading

We provide a package you can add to your existing v2 application in order to
upgrade it to version 2.

Before installing and running the migration tooling, make sure you have checked
in your latest changes (assuming you are using version control), or have a
backup of your existing code.

Install the migration tooling using the following command:

```bash
$ composer require --dev zendframework/zend-expressive-migration
```

Once installed, run the following command to migrate your application:

```bash
$ ./vendor/bin/expressive-migration migrate
```

This package does the following:

- Uninstalls all current dependencies (by removing the `vendor/` directory).
- Updates existing dependency constraints for known Expressive packages to their
  latest stable versions. (See the tools [README](https://github.com/zendframework/zend-expressive-migration)
  for details on what versions of which packages the tool uses.)
- Adds development dependencies on zendframework/zend-component-installer and
  zendframework/zend-expressive-tooling.
- Updates the `config/pipeline.php` file to:
    - add strict type declarations.
    - modify it to return a callable, per the v3 skeleton.
    - update the middleware pipeline as follows:
        - `pipeRoutingMiddleware()` becomes a `pipe()` operation referencing the
          zend-expressive-router `RouteMiddleware`.
        - `pipeDispatchMiddleware()` becomes a `pipe()` operation referencing the
          zend-expressive-router `DispatchMiddleware`.
        - update references to `ImplicitHeadMiddleware` to reference the version
          in zend-expressive-router.
        - update references to `ImplicitOptionsMiddleware` to reference the version
          in zend-expressive-router.
        - update references to `Zend\Expressive\Middleware\NotFoundHandler` to
          reference `Zend\Expressive\Handler\NotFoundHandler`.
        - add a `pipe()` entry for the zend-expressive-router
          `MethodNotAllowedMiddleware`.
- Updates the `config/routes.php` file to:
    - add strict type declarations.
    - modify it to return a callable, per the v3 skeleton.
- Replaces the `public/index.php` file with the latest version from the skeleton.
- Updates `config/container.php` when Pimple or Aura.Di are in use:
    - For Pimple:
        - The package `xtreamwayz/pimple-container-interop` is replaced with
          `zendframework/zend-pimple-config`.
        - The Pimple variant of `container.php` from the v3 skeleton is used.
    - For Aura.Di
        - The package `aura/di` is replaced with `zendframework/zend-auradi-config`.
        - The Aura.Di variant of `container.php` from the v3 skeleton is used.
- Executes `./vendor/bin/expressive migrate:interop-middleware`.
- Executes `./vendor/bin/expressive migrate:middleware-to-request-handler`.
- Runs `./vendor/bin/phpcbf` if it is installed.

These steps should take care of most migration tasks.

It **does not** update unit tests. These cannot be automatically updated, due to
the amount of variance in testing strategies.

When done, use a diffing tool to compare and verify all changes. Please be aware
that the tool is not designed for edge cases; there may be things it does not do
or cannot catch within your code. When unsure, refer to the other sections in
this document to determine what else you may need to change.
