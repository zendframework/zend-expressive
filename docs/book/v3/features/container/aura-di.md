# Using Aura.Di

[Aura.Di](https://github.com/auraphp/Aura.Di/) provides a serializable dependency
injection container with the following features:

- constructor and setter injection.
- inheritance of constructor parameter and setter method values from parent
  classes.
- inheritance of setter method values from interfaces and traits.
- lazy-loaded instances, services, includes/requires, and values.
- instance factories.
- optional auto-resolution of typehinted constructor parameter values.

## Installing Aura.Di

Aura.Di implements [PSR-11](https://www.php-fig.org/psr/psr-11/) as of
version 3. To use Aura.Di as a dependency injection container, we recommend using
[zendframework/zend-auradi-config](https://github.com/zendframework/zend-auradi-config),
which helps you to configure its container. First, install the package:

```bash
$ composer require zendframework/zend-auradi-config
```

## Configuration

To configure Aura.Di, create the file `config/container.php` with the following
contents:

```php
<?php
use Zend\AuraDi\Config\Config;
use Zend\AuraDi\Config\ContainerFactory;

$config = require __DIR__ . '/config.php';
$factory = new ContainerFactory();
return $factory(new Config($config));
```

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

For more information, please see the
[zend-auradi-config documentation](https://github.com/zendframework/zend-auradi-config/blob/master/README.md)
