# Command Line Tooling

Expressive offers a number of tools for assisting in project development. This
page catalogues each.

## Development Mode

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

The package [zendframework/zend-expressive-tooling](https://github.com/zendframework/zend-expressive-tooling)
provides the script `vendor/bin/expressive`, which contains a number of commands
related to migration, modules, and middleware.

You can install it if it is not already present in your application:

```bash
$ composer require --dev zendframework/zend-expressive-tooling
```

Once installed, invoking the binary without arguments will give a listing of
available tools:

```bash
$ ./vendor/bin/expressive
```

> #### Integration with Composer
>
> In the skeleton application, we provide direct integration with Composer,
> allowing you to invoke the tooling using:
>
> ```bash
> $ composer expressive
> ```
>
> You can use either that form, or invoke the script directly as detailed above.

Commands supported include:

- **`action:create [options] <action>`**: This is an alias for the
  `handler:create` command detailed below.

- **`factory:create [options] <class>`**: Create a factory for the named class.
  By default, the command will also register the class with its factory in the
  application container.

- **`handler:create [options] <handler>`**: Create a request handler named after
  `<handler>`. By default, the command will also generate a factory, register
  both with the application container, and, if a template renderer is
  discovered, generate a template in an appropriate location.

- **`middleware:create <middleware>`**: Create a class file for the named
  middleware class. The class _must_ use a namespace already declared in your
  application, and will be created relative to the path associated with that
  namespace.

- **`migrate:interop-middleware [options]`**: Migrates former http-interop
  middleware under the `src/` tree to PSR-15 middleware.

- **`migrate:middleware-to-request-handler [options]`**: Migrates PSR-15
  middleware under the `src/` tree to PSR-15 request handlers; it will only
  migrate those that never call on their `$handler` argument.

- **`module:create [--composer|-c] [--modules-path|-p] <module>`**: Create the
  named module including a filesystem skeleton, add and generate autoloading
  rules for it, and register the module's `ConfigProvider` with your
  application.

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
