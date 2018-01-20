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

## Installing and configuring Aura.Di

Aura.Di implements [container-interop](https://github.com/container-interop/container-interop)
as of version 3. To use Aura.Di as dependency injection container we
recommend using [zendframework/zend-auradi-config](https://github.com/zendframework/zend-auradi-config)
which helps you to configure the PSR-11 container. First install the package:

```bash
$ composer require zendframework/zend-auradi-config
```

Then to configure Aura.Di use the following script
(we'll put that in `config/container.php`):

```php
use Zend\AuraDi\Config\Config;
use Zend\AuraDi\Config\ContainerFactory;

$config  = require __DIR__ . '/config.php';
$factory = new ContainerFactory();

return $factory(new Config($config));
```

For more information please see
[documentation of zend-auradi-config](https://github.com/zendframework/zend-auradi-config/blob/master/README.md).

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
