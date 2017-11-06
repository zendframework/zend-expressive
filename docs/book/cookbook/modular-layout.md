# How can I make my application modular?

> ## DEPRECATED
>
> Starting in Expressive 2.0, we now ship features to allow modular applications
> both at the time of installation, or to add following installation. Please
> see the chapter on [modular applications](../features/modular-applications.md)
> for more details.

Zend Framework 2 applications have a concept of modules, independent units that
can provide configuration, services, and hooks into its MVC lifecycle. This
functionality is provided by zend-modulemanager.
 
While zend-modulemanager could be used with Expressive, we suggest another
approach: modules that are based only on configuration. This powerful approach
doesn't affect performance, and offers extensive flexibility: each module can
provide its own services (with factories), default configuration, and routes. 

This cookbook will show how to organize modules using 
[mtymek/expressive-config-manager](https://github.com/mtymek/expressive-config-manager),
a lightweight library that aggregates and merges configuration, optionally caching it.

## Install the configuration manager

The configuration manager is available in Packagist:

```bash
$ composer require mtymek/expressive-config-manager
```

## Generate your config

The default Expressive skeleton installs a `config/config.php` file, which
aggregates all configuration. When using the configuration manager, you will
need to replace the contents of that file with the following code: 

```php
<?php

use Zend\Expressive\ConfigManager\ConfigManager;
use Zend\Expressive\ConfigManager\PhpFileProvider;

$configManager = new ConfigManager([
    new PhpFileProvider('config/autoload/{{,*.}global,{,*.}local}.php'),
]);

return new ArrayObject($configManager->getMergedConfig());
```

If you open your application in a browser, it should still work in exactly the
same way as it was before. Now you can start adding your modules.

## First module

`ConfigManager` does not force you to use any particular structure for your
module; its only requirement is to expose default configuration using a "config
provider", which is simply an invokable class that returns a configuration
array.

For instance, this is how your module could provide its own routes:

```php
namespace MyModule;

class ModuleConfig
{
    public function __invoke()
    {
        return [
            'routes' => [
                [
                    'name' => 'api.list-transactions',
                    'path' => '/api/transactions',
                    'middleware' => App\Action\ListTransactionsAction::class,
                    'allowed_methods' => ['GET'],
                ],
                [
                    'name' => 'api.refund-transaction',
                    'path' => '/api/refund',
                    'middleware' => App\Action\RefundAction::class,
                    'allowed_methods' => ['POST'],
                ],
            ],
        ];
    }
}
```

## Enabling the module

Finally, you can enable your module by adding a reference to your config class
within the arguments of the `ConfigManager` constructor in the `config/config.php`
file:

```php
$configManager = new ConfigManager([
    MyModule\ModuleConfig::class,
    new PhpFileProvider('config/autoload/{{,*.}global,{,*.}local}.php'),
]);
```

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

## Final notes

This approach may look simple, but it is flexible and powerful:

- You pass a list of config providers to the `ConfigManager` constructor.
- Configuration is merged in the same order as it is passed, with later entries
  having precedence.
- You can override module configuration using `*.global.php` and `*.local.php` files.
- If cached config is found, `ConfigManager` does not iterate over provider list.

For more details, please refer to the
[Config Manager Documentation](https://github.com/mtymek/expressive-config-manager#expressive-configuration-manager).
