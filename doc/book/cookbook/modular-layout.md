How can I make my application modular?
======================================

ZF2 applications are built with modules - independent units that can provide
configuration, services and hooks to MVC lifecycle.
 
While ZF ModuleManager can be used with Expressive, we suggest different approach: 
modules that are based only on configuration. This is pretty powerful, and (contrary
to ZF modules) doesn't affect performance. It still offers flexibility: each module
can provide its own services (with factories), default configuration and routes. 

This cookbook will show how to organize modules with 
[`mtymek/expressive-config-manager`](https://github.com/mtymek/expressive-config-manager)
- lightweight library that aggregates and merges configuration, optionally caching it.

## Install configuration manager

Configuration manager is available in Packagist:

```bash
$ composer require mtymek/expressive-config-manager
```

## Generate your config

Default Expressive Skeleton comes with `config/config.php` file - main place that
aggregates all configuration. Replace it with following code: 

```php
<?php

use Zend\Expressive\ConfigManager\ConfigManager;
use Zend\Expressive\ConfigManager\PhpFileProvider;

$configManager = new ConfigManager(
    [
        new PhpFileProvider('config/autoload/{{,*.}global,{,*.}local}.php'),
    ]
);

return new ArrayObject($configManager->getMergedConfig());
```

If you open application in a browser, it should still work in exactly the same way
as it was before. Now you can start adding your modules.

## First module

`ConfigManager` does not force you to use any particular structure for your module.
Only requirement is to expose default configuration using "config provider" - an
invokable class that returns configuration array. For instance, this is how your
module can provide its own routes:

```php
namespace App;

class AppConfig
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

## Enabling module

Finally, you can enable your module by adding config class `ConfigManager` constructor in
`config/config.php` file:

```php
$configManager = new ConfigManager(
    [
        App\AppConfig::class,
        new PhpFileProvider('config/autoload/{{,*.}global,{,*.}local}.php'),
    ]
);
```

This looks simple, but it is flexible:
- You pass list of config providers to `ConfigManager` constructor.
- Configuration is merged in the same order as it is passed.
- You can override default configuration using `*.global.php` and `*.local.php` files.
- If cached config is found, `ConfigManager` does not iterate over provider list.

For more details, please refer to [Config Manager Documentation](https://github.com/mtymek/expressive-config-manager#expressive-configuration-manager).
