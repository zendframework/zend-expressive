# Using Pimple

[Pimple](http://pimple.sensiolabs.org/) is a widely used code-driven dependency
injection container provided as a standalone component by SensioLabs. It
features:

- combined parameter and service storage.
- ability to define factories for specific classes.
- lazy-loading via factories.

Pimple only supports programmatic creation at this time.

## Installing and configuring Pimple

Pimple implements [PSR-11 Container](https://github.com/php-fig/container)
as of version 3.2. To use Pimple as dependency injection container we
recommend using [zendframework/zend-pimple-config](https://github.com/zendframework/zend-pimple-config)
which helps you to configure the PSR-11 container. First install the package:

```bash
$ composer require zendframework/zend-pimple-config
```

Then to configure Pimple use the following script
(we'll have that in `config/container.php`):

```php
use Zend\Pimple\Config\Config;
use Zend\Pimple\Config\ContainerFactory;

$config  = require __DIR__ . '/config.php';
$factory = new ContainerFactory();

return $factory(new Config($config));
```

For more information please see
[documentation of zend-pimple-config](https://github.com/zendframework/zend-pimple-config/blob/master/README.md).

Your bootstrap (typically `public/index.php`) will then look like this:

```php
chdir(dirname(__DIR__));
require 'vendor/autoload.php';

$container = require 'config/container.php';
$app = $container->get(Zend\Expressive\Application::class);

require 'config/pipeline.php';
require 'config/routes.php';

$app->run();
```
