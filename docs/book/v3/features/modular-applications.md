# Modular applications

Zend Framework 2+ applications have a concept of _modules_, independent units that
can provide configuration, services, and hooks into its MVC lifecycle. This
functionality is provided by zend-modulemanager.

Expressive provides similar functionality by incorporating two packages within
the default skeleton application:

- [zendframework/zend-config-aggregator](https://github.com/zendframework/zend-config-aggregator),
  which provides features for aggregating configuration from a variety of
  sources, including:
    - PHP files globbed from the filesystem that return an array of configuration.
    - [zend-config](https://docs.zendframework.com/zend-config)-compatible
      configuration files globbed from the filesystem.
    - Configuration provider classes; these are invokable classes which return an
      array of configuration.
- [zendframework/zend-component-installer](https://github.com/zendframework/zend-component-installer),
  a Composer plugin that looks for an `extra.zf.config-provider` entry in a
  package to install, and, if found, adds an entry for that provider to the
  `config/config.php` file (if it uses zend-config-aggregator).

These features allow you to install packages via composer and expose their
configuration &mdash; which may include dependency information &mdash; to your
application.

## Making your application modular

When using the Expressive installer via the skeleton application, the first
question asked is the installation type, which includes the options:

- Minimal (no default middleware, templates, or assets; configuration only)
- Flat (flat source code structure; default selection)
- Modular (modular source code structure; recommended)

We recommend choosing the "Modular" option from the outset.

If you do not, you can still create and use modules in your application;
however, the initial "App" module will not be modular.

## Module structure

Expressive does not force you to use any particular structure for your
module; its only requirement is to expose default configuration using a "config
provider", which is simply an invokable class that returns a configuration
array.

We generally recommend that a module have a [PSR-4](http://www.php-fig.org/psr/psr-4/)
structure, and that the module contain a `src/` directory at the minimum, along
with directories for other module-specific content, such as templates, tests, and
assets:

```text
src/
  Acme/
    src/
      ConfigProvider.php
      Helper/
        AuthorizationHelper.php
      Middleware/
        VerifyUser.php
        VerifyUserFactory.php
    templates/
      verify-user.php
    test/
      Helper/
        AuthorizationHelperTest.php
      Middleware/
        VerifyUserTest.php
```

If you use the above structure, you would then add an entry in your
`composer.json` file to provide autoloading:

```json
"autoload": {
    "psr-4": {
        "Acme\\": "src/Acme/src/"
    }
}
```

Don't forget to execute `composer dump-autoload` after making the change!

## Creating and enabling a module

The only _requirement_ for creating a module is that you define a "config
provider", which is simply an invokable class that returns a configuration
array.

Generally, a config provider will return dependency information, and
module-specific configuration:

```php
namespace Acme;

class ConfigProvider
{
    public function __invoke()
    {
        return [
            'dependencies' => $this->getDependencies(),
            'acme' => [
                'some-setting' => 'default value',
            ],
            'templates' => [
                'paths' => [
                    'acme' => [__DIR__ . '/../templates'],
                ],
            ]
        ];
    }

    public function getDependencies()
    {
        return [
            'invokables' => [
                Helper\AuthorizationHelper::class => Helper\AuthorizationHelper::class,
            ],
            'factories' => [
                Middleware\VerifyUser::class => Container\VerifyUserFactory::class,
            ],
        ];
    }
}
```

You would then add the config provider to the top (or towards the top) of your
`config/config.php`:

```php
$aggregator = new ConfigAggregator([
    Acme\ConfigProvider::class,
    /* ... */
```

This approach allows your `config/autoload/*` files to take precedence over the
module configuration, allowing you to override the values.

## Caching configuration

In order to provide configuration caching, two things must occur:

- First, you must define a `config_cache_enabled` key in your configuration
  somewhere.
- Second, you must pass a second argument to the `ConfigManager`, the location
  of the cache file to use.

The `config_cache_enabled` key can be defined in any of your configuration
providers, including the autoloaded configuration files. We recommend defining
them in two locations:

- `config/autoload/global.php` should define the value to `true`, as the
  production setting.
- `config/autoload/local.php` should also define the setting, and use a value
  appropriate to the current environment. In development, for instance, this
  would be `false`.

```php
// config/autoload/global.php

return [
    'config_cache_enabled' => true,
    /* ... */
];

// config/autoload/local.php

return [
    'config_cache_enabled' => false, // <- development!
    /* ... */
];
```

You would then alter your `config/config.php` file to add the second argument.
The following example builds on the previous, and demonstrates having the
`AppConfig` entry enabled. The configuration will be cached to
`data/config-cache.php` in the application root:

```php
$configManager = new ConfigManager([
    App\AppConfig::class,
    new PhpFileProvider('config/autoload/{{,*.}global,{,*.}local}.php'),
], 'data/config-cache.php');
```

When the configuration cache path is present, if the `config_cache_enabled` flag
is enabled, then configuration will be read from the cached configuration,
instead of parsing and merging the various configuration sources.

## Tooling support

The skeleton ships with zend-expressive-tooling by default, which allows you
to execute the following command in order to create a module skeleton, add and
enable autoloading rules for it, and register it with your application:

```bash
$ composer expressive module:create {ModuleName}
```

We recommend using this tool when creating new modules.

## Final notes

This approach may look simple, but it is flexible and powerful:

- You pass a list of config providers to the `ConfigAggregator` constructor.
- Configuration is merged in the same order as it is passed, with later entries
  having precedence.
- You can override module configuration using `*.global.php` and `*.local.php` files.
- If cached config is found, `ConfigAggregator` does not iterate over provider list.

For more details, please refer to the [zend-config-aggregator
documentation](https://docs.zendframework.com/zend-config-aggregator/).
