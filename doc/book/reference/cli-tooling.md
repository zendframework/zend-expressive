# Command Line Tooling

Expressive offers a number of tools for assisting in project development. This
page catalogues each.

## Development Mode

- Since 2.0.

The package [zfcampus/zf-development-mode](https://github.com/zfcampus/zf-development-mode)
provides a simple way to toggle in and out of _development mode_. Doing so
allows you to ship known development-specific settings within your repository,
while ensuring they are not enabled in production. The tooling essentially
enables optional, development-specific configuration in your application by:

- Copying the file `config/development.config.php.dist` to
  `config/development.config.php`; this can be used to enable
  development-specific modules or settings (such as the `debug` flag).
- Copying the file `config/autoload/development.local.php.dist` to
  `config/autoload/development.local.php`; this can be used to provide local
  overrides of a number of configuration settings.

The package provides the tooling via `vendor/bin/zf-development-mode`. If you
are using the Expressive skeleton, it provides aliases via Composer:

```php
$ composer development-enable
$ composer development-disable
$ composer development-status
```

Add settings to your `development.*.php.dist` files, and commit those files to
your repository; always toggle out of and into development mode after making
changes, to ensure they pick up in your development environment.

## Expressive command-line tool

- Since zend-expressive-tooling 0.4.0 and zend-expressive-skeleton 2.0.2

The package [zendframework/zend-expressive-tooling](https://github.com/zendframework/zend-expressive-tooling)
provides the script `vendor/bin/expressive`, which contains a number of commands
related to migration, modules, and middleware.

You can install it if it is not already present in your application:

```bash
$ composer require --dev zendframework/zend-expressive-tooling
```

If you installed the Expressive skeleton prior to version 2.0.2, you will want
to update the tooling to get the latest release, which contains the `expressive`
binary, as follows:

```bash
$ composer require --dev "zendframework/zend-expressive-tooling:^0.4.1"
```

Once installed, invoking the binary without arguments will give a listing of
available tools:

```bash
$ ./vendor/bin/expressive
```

Commands supported include:

- **`middleware:create <middleware>`**: Create a class file for the named
  middleware class. The class _must_ use a namespace already declared in your
  application, and will be created relative to the path associated with that
  namespace.

- **`migrate:error-middleware-scanner [--dir|-d]`**: Scan the associated
  directory (defaults to `src`) for declarations of legacy Stratigility v1 error
  middleware, or invocations of `$next()` that provide an error argument. See
  the [section on detecting legacy error middleware](#detect-usage-of-legacy-error-middleware)
  for more details.

- **`migrate:original-messages [--src|-s]`**: Scan the associated source directory
  (defaults to `src`) for `getOriginal*()` method calls and replace them with
  `getAttribute()` calls. See the [section on detecting legacy
  calls](#detect-usage-of-legacy-getoriginal-calls) for more details.

- **`migrate:pipeline [--config-file|-c]`**: Convert configuration-driven
  pipelines and routing to programmatic declarations. See the [section on
  migrating to programmatic pipelines](#migrate-to-programmatic-pipelines) for
  more details.

- **`module:create [--composer|-c] [--modules-path|-p] <module>`**: Create the
  named module, add and generate autoloading rules for it, and register the
  module's `ConfigProvider` with your application.

- **`module:register [--composer|-c] [--modules-path|-p] <module>`**: Add and
  generate autoloading rules for the named module,  and register the module's
  `ConfigProvider` with your application.

- **`module:deregister [--composer|-c] [--modules-path|-p] <module>`**: Remove
  autoloading rules for the named module and regenerate autoloading rules;
  remove the module's `ConfigProvider` from the application configuration.

You may obtain full help for each command by invoking:

```bash
$ ./vendor/bin/expressive help <command>
```

## Modules

- Since 2.0.
- Deprecated since zend-expressive-tooling 0.4.0; see the [Expressive CLI tool
  section above](#expressive-command-line-tool).

The package [zendframework/zend-expressive-tooling](https://github.com/zendframework/zend-expressive-tooling)
provides the binary `vendor/bin/expressive-module`, which allows you to create,
register, and deregister modules, assuming you are using a [modular application
layout](../features/modular-applications.md).

> ### Adding tooling to existing applications
>
> If you have upgraded from Expressive 1.X, you can install
> zendframework/zend-expressive-tooling via Composer:
>
> ```bash
> $ composer require --dev zendframework/zend-expressive-tooling
> ```

For instance, if you wish to create a new module for managing users, you might
execute the following:

```bash
$ ./vendor/bin/expressive-module create User
```

Which would create the following tree:

```text
src/
  User/
    src/
      ConfigProvider.php
    templates/
```

It would also create an autoloading rule within your `composer.json` for the
`User` namespace, pointing it at the `src/User/src/` tree (and updating the
autoloader in the process), and register the new module's `ConfigProvider`
within your `config/config.php`.

The `register` command will take an existing module and:

- Add an autoloading rule for it to your `composer.json`, if necessary.
- Add an entry for the module's `ConfigProvider` class to your
  `config/config.php`, if possible.

```bash
$ ./vendor/bin/expressive-module register Account
```

The `deregister` command does the opposite of `register`.

```bash
$ ./vendor/bin/expressive-module deregister Account
```

## Migrate to programmatic pipelines

- Since 2.0.
- Deprecated since zend-expressive-tooling 0.4.0; see the [Expressive CLI tool
  section above](#expressive-command-line-tool).

Starting in 2.0, we recommend using _programmatic pipelines_, versus
configuration-defined pipelines. For those upgrading their applications from 1.X
versions, we provide a tool that will read their application configuration and
generate:

- `config/pipeline.php`, with the middleware pipeline
- `config/routes.php`, with routing directives
- `config/autoload/zend-expressive.global.php`, with settings to ensure
  programmatic pipelines are used, and new middleware provided for Expressive
  2.0 is registered.
- directives within `public/index.php` for using the generated pipeline and
  routes directives.

To use this feature, you will need to first install
zendframework/zend-expressive-tooling:

```bash
$ composer require --dev zendframework/zend-expressive-tooling
```

Invoke it as follows:

```bash
$ ./vendor/bin/expressive-pipeline-from-config generate
```

The tool will notify you of any errors, including whether or not it found (and
skipped) Stratigility v1-style "error middleware".

## Detect usage of legacy getOriginal*() calls

- Since 2.0.
- Deprecated since zend-expressive-tooling 0.4.0; see the [Expressive CLI tool
  section above](#expressive-command-line-tool).

When upgrading to version 2.0, you will also receive an upgrade to
zendframework/zend-stratigility 2.0. That version eliminates internal decorator
classes for the request and response instances, which were used to provide
access to the outermost request/response; internal layers could use these to
determine the full URI that resulted in their invocation, which is useful when
you pipe using a path argument (as the path provided during piping is stripped
from the URI when invoking the matched middleware).

This affects the following methods:

- `Request::getOriginalRequest()`
- `Request::getOriginalUri()`
- `Response::getOriginalResponse()`

To provide equivalent functionality, we provide a couple of tools.

First, Stratigility provides middleware, `Zend\Stratigility\Middleware\OriginalMessages`,
which will inject the current request, its URI, and, if invoked as double-pass
middleware, current response, as _request attributes_, named, respectively,
`originalRequest`, `originalUri`, and `originalResponse`. (Since Expressive 2.0
decorates double-pass middleware using a wrapper that composes a response, the
"original response" will be the response prototype composed in the `Application`
instance.) This should be registered as the outermost middleware layer.
Middleware that needs access to these instances can then use the following
syntax to retrieve them:

```php
$originalRequest = $request->getAttribute('originalRequest', $request);
$originalUri = $request->getAttribute('originalUri', $request->getUri();
$originalResponse = $request->getAttribute('originalResponse') ?: new Response();
```

> ### Original response is not trustworthy
>
> As noted above, the "original response" will likely be injected with the
> response prototype from the `Application` instance. We recommend not using it,
> and instead either composing a pristine response instance in your middleware,
> or creating a new instance on-the-fly.

To aid you in migrating your existing code to use the new `getAttribute()`
syntax, zendframework/zend-expressive-tooling provides a binary,
`vendor/bin/expressive-migrate-original-messages`. First, install that package:

```bash
$ composer require --dev zendframework/zend-expressive-tooling
```

Then invoke it as follows:

```bash
$ ./vendor/bin/expressive-migrate-original-messages scan
```

This script will update any `getOriginalRequest()` and `getOriginalUri()` calls,
and notify you of any `getOriginalResponse()` calls, providing you with details
on how to correct those manually.

## Detect usage of legacy error middleware

- Since 2.0.
- Deprecated since zend-expressive-tooling 0.4.0; see the [Expressive CLI tool
  section above](#expressive-command-line-tool).

When upgrading to version 2.0, you will also receive an upgrade to
zendframework/zend-stratigility 2.0. That version eliminates what was known as
"error middleware", middleware that either implemented
`Zend\Stratigility\ErrorMiddlewareInterface`, or duck-typed it by implementing
the signature `function ($error, $request, $response, callable $next)`.

Such "error middleware" allowed other middleware to invoke the `$next` argument
with an additional, third argument representing an error condition; when that
occurred, Stratigility/Expressive would start iterating through error middleware
until one was able to return a response. Each would receive the error as the
first argument, and determine how to act upon it.

With version 2.0 of each project, such middleware is now no longer accepted, and
users should instead be using [the new error handling
features](../features/error-handling.md). However, you may find that:

- You have defined error middleware in your application.
- You have standard middleware in your application that invokes `$next` with the
  third, error argument.

To help you identify such instances, zendframework/zend-expressive-tooling
provides the script `vendor/bin/expressive-scan-for-error-middleware`. First,
install that package:

```bash
$ composer require --dev zendframework/zend-expressive-tooling
```

Then invoke it as follows:

```bash
$ ./vendor/bin/expressive-scan-for-error-middleware scan
```

The script will notify you of any places where it finds either use case, and
provide feedback on how to update your application.
