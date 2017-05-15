# Using zend-servicemanager

[zend-servicemanager](https://github.com/zendframework/zend-servicemanager) is a
code-driven dependency injection container provided as a standalone component by
Zend Framework. It features:

- lazy-loading of invokable (constructor-less) classes.
- ability to define factories for specific classes.
- ability to define generalized factories for classes with identical
  construction patterns (aka *abstract factories*).
- ability to create lazy-loading proxies.
- ability to intercept before or after instantiation to alter the construction
  workflow (aka *delegator factories*).
- interface injection (via *initializers*).

zend-servicemanager may either be created and populated programmatically, or via
configuration. Configuration uses the following structure:

```php
[
    'services' => [
        'service name' => $serviceInstance,
    ],
    'invokables' => [
        'service name' => 'class to instantiate',
    ],
    'factories' => [
        'service name' => 'callable, Zend\ServiceManager\FactoryInterface instance, or name of factory class returning the service',
    ],
    'abstract_factories' => [
        'class name of Zend\ServiceManager\AbstractFactoryInterface implementation',
    ],
    'delegators' => [
        'service name' => [
            'class name of Zend\ServiceManager\DelegatorFactoryInterface implementation',
        ],
    ],
    'lazy_services' => [
        'class_map' => [
            'service name' => 'Class\Name\Of\Service',
        ],
    ],
    'initializers' => [
        'callable, Zend\ServiceManager\InitializerInterface implementation, or name of initializer class',
    ],
]
```

Read more about zend-servicemanager in [its documentation](https://docs.zendframework.com/zend-servicemanager/).

## Installing zend-servicemanager

To use zend-servicemanager with zend-expressive, you can install it via
composer:

```bash
$ composer require zendframework/zend-servicemanager
```

## Configuring zend-servicemanager

You can configure zend-servicemanager either programmatically or via
configuration. We'll show you both methods.

### Programmatically

To use zend-servicemanager programatically, you'll need to create a
`Zend\ServiceManager\ServiceManager` instance, and then start populating it.

For this example, we'll assume your application configuration (used by several
factories to configure instances) is in `config/config.php`, and that that file
returns an array.

We'll create a `config/services.php` file that creates and returns a
`Zend\ServiceManager\ServiceManager` instance as follows:

```php
use Zend\ServiceManager\ServiceManager;

$container = new ServiceManager();

// Application and configuration
$container->setService('config', include 'config/config.php');
$container->setFactory(
    'Zend\Expressive\Application',
    'Zend\Expressive\Container\ApplicationFactory'
);

// Routing
// In most cases, you can instantiate the router you want to use without using a
// factory:
$container->setInvokableClass(
    'Zend\Expressive\Router\RouterInterface',
    'Zend\Expressive\Router\AuraRouter'
);

// Templating
// In most cases, you can instantiate the template renderer you want to use
// without using a factory:
$container->setInvokableClass(
    'Zend\Expressive\Template\TemplateRendererInterface',
    'Zend\Expressive\Plates\PlatesRenderer'
);

// These next two can be added in any environment; they won't be used unless
// you add the WhoopsErrorHandler as the FinalHandler implementation
// (Expressive 1.X) or the WhoopsErrorResponseGenerator as the
// ErrorResponseGenerator implementation (Expressive 2.X):
$container->setFactory(
    'Zend\Expressive\Whoops',
    'Zend\Expressive\Container\WhoopsFactory'
);
$container->setFactory(
    'Zend\Expressive\WhoopsPageHandler',
    'Zend\Expressive\Container\WhoopsPageHandlerFactory'
);

// Error Handling

// - Expressive 2.X, all environments:
$container->setFactory(
    'Zend\Expressive\Middleware\ErrorHandler',
    'Zend\Expressive\Container\ErrorHandlerFactory'
);

// If in development:
// - Expressive 1.X:
$container->setFactory(
    'Zend\Expressive\FinalHandler',
    'Zend\Expressive\Container\WhoopsErrorHandlerFactory'
);
// - Expressive 2.X:
$container->setFactory(
    'Zend\Expressive\Middleware\ErrorResponseGenerator',
    'Zend\Expressive\Container\WhoopsErrorResponseGeneratorFactory'
);

// If in production:
// - Expressive 1.X:
$container->setFactory(
    'Zend\Expressive\FinalHandler',
    'Zend\Expressive\Container\TemplatedErrorHandlerFactory'
);
// - Expressive 2.X:
$container->setFactory(
    'Zend\Expressive\Middleware\ErrorResponseGenerator',
    'Zend\Expressive\Container\ErrorResponseGeneratorFactory'
);

return $container;
```

Your bootstrap (typically `public/index.php`) will then look like this:

```php
chdir(dirname(__DIR__));
require 'vendor/autoload.php';
$container = require 'config/services.php';
$app = $container->get('Zend\Expressive\Application');

// Expressive 2.X:
require 'config/pipeline.php';
require 'config/routes.php';

// All versions:
$app->run();
```

### Configuration-Driven Container

Alternately, you can use a configuration file to define the container. As
before, we'll define our configuration in `config/config.php`, and our
`config/services.php` file will still return our service manager instance; we'll
define the service configuration in `config/dependencies.php`:

```php
return [
    'services' => [
        'config' => include __DIR__ . '/config.php',
    ],
    'aliases' => [
        // Expressive 2.0:
        'Zend\Expressive\Delegate\DefaultDelegate' => 'Zend\Expressive\Delegate\NotFoundDelegate',
    ],
    'invokables' => [
        'Zend\Expressive\Router\RouterInterface'     => 'Zend\Expressive\Router\AuraRouter',
        'Zend\Expressive\Template\TemplateRendererInterface' => 'Zend\Expressive\Plates\PlatesRenderer'
    ],
    'factories' => [
        'Zend\Expressive\Application'       => 'Zend\Expressive\Container\ApplicationFactory',
        'Zend\Expressive\Whoops'            => 'Zend\Expressive\Container\WhoopsFactory',
        'Zend\Expressive\WhoopsPageHandler' => 'Zend\Expressive\Container\WhoopsPageHandlerFactory',

        // Expressive 2.0:
        'Zend\Expressive\Middleware\ErrorHandler'    => 'Zend\Expressive\Container\ErrorHandlerFactory',
        'Zend\Expressive\Delegate\NotFoundDelegate'  => 'Zend\Expressive\Container\NotFoundDelegateFactory',
        'Zend\Expressive\Middleware\NotFoundHandler' => 'Zend\Expressive\Container\NotFoundHandlerFactory',
    ],
];
```

`config/services.php` becomes:

```php
use Zend\ServiceManager\Config;
use Zend\ServiceManager\ServiceManager;

return new ServiceManager(new Config(include 'config/dependencies.php'));
```

There is one problem, however: in both Expressive 1.X and 2.X, you may want to
vary error handling strategies based on whether or not you're in production:
You have two choices on how to approach this:

- Selectively inject the factory in the bootstrap.
- Define the final handler service in an environment specific file and use file
  globbing to merge files.

In the first case, you would change the `config/services.php` example to look
like this:

```php
use Zend\ServiceManager\Config;
use Zend\ServiceManager\ServiceManager;

$container = new ServiceManager(new Config(include 'config/services.php'));
switch ($variableOrConstantIndicatingEnvironment) {
    case 'development':
        // Expressive 1.X:
        $container->setFactory(
            'Zend\Expressive\FinalHandler',
            'Zend\Expressive\Container\WhoopsErrorHandlerFactory'
        );

        // Expressive 2.X:
        $container->setFactory(
            'Zend\Expressive\Middleware\ErrorResponseGenerator',
            'Zend\Expressive\Container\WhoopsErrorResponseGeneratorFactory'
        );
        break;
    case 'production':
    default:
        // Expressive 1.X:
        $container->setFactory(
            'Zend\Expressive\FinalHandler',
            'Zend\Expressive\Container\TemplatedErrorHandlerFactory'
        );

        // Expressive 2.X:
        $container->setFactory(
            'Zend\Expressive\Middleware\ErrorResponseGenerator',
            'Zend\Expressive\Container\ErrorResponseGeneratorFactory'
        );
}
return $container;
```

In the second case, you will need to install zend-config:

```bash
$ composer require zendframework/zend-config
```

Then, create the directory `config/autoload/`, and create two files,
`dependencies.global.php` and `dependencies.local.php`. In your `.gitignore`,
add an entry for `config/autoload/*local.php` to ensure "local"
(environment-specific) files are excluded from the repository.

`config/dependencies.php` will look like this:

```php
use Zend\Config\Factory as ConfigFactory;

return ConfigFactory::fromFiles(
    glob('config/autoload/dependencies.{global,local}.php', GLOB_BRACE)
);
```

`config/autoload/dependencies.global.php` will look like this:

```php
return [
    'services' => [
        'config' => include __DIR__ . '/config.php',
    ],
    'aliases' => [
        // Expressive 2.0:
        'Zend\Expressive\Delegate\DefaultDelegate' => 'Zend\Expressive\Delegate\NotFoundDelegate',
    ],
    'invokables' => [
        'Zend\Expressive\Router\RouterInterface'     => 'Zend\Expressive\Router\AuraRouter',
        'Zend\Expressive\Template\TemplateRendererInterface' => 'Zend\Expressive\Plates\PlatesRenderer'
    ],
    'factories' => [
        'Zend\Expressive\Application'       => 'Zend\Expressive\Container\ApplicationFactory',
        'Zend\Expressive\Whoops'            => 'Zend\Expressive\Container\WhoopsFactory',
        'Zend\Expressive\WhoopsPageHandler' => 'Zend\Expressive\Container\WhoopsPageHandlerFactory',

        // Expressive 1.X:
        'Zend\Expressive\FinalHandler'      => 'Zend\Expressive\Container\TemplatedErrorHandlerFactory',

        // Expressive 2.X:
        'Zend\Expressive\Middleware\ErrorResponseGenerator' => 'Zend\Expressive\Container\ErrorResponseGeneratorFactory',
        'Zend\Expressive\Middleware\ErrorHandler'    => 'Zend\Expressive\Container\ErrorHandlerFactory',
        'Zend\Expressive\Delegate\NotFoundDelegate'  => 'Zend\Expressive\Container\NotFoundDelegateFactory',
        'Zend\Expressive\Middleware\NotFoundHandler' => 'Zend\Expressive\Container\NotFoundHandlerFactory',
    ],
];
```

`config/autoload/dependencies.local.php` on your development machine can look
like this:

```php
return [
    'factories' => [
        'Zend\Expressive\Whoops'            => 'Zend\Expressive\Container\WhoopsFactory',
        'Zend\Expressive\WhoopsPageHandler' => 'Zend\Expressive\Container\WhoopsPageHandlerFactory',

        // Expressive 1.X:
        'Zend\Expressive\FinalHandler'      => 'Zend\Expressive\Container\WhoopsErrorHandlerFactory',

        // Expressive 2.X:
        'Zend\Expressive\Middleware\ErrorResponseGenerator' => 'Zend\Expressive\Container\WhoopsErrorResponseGeneratorFactory',
    ],
];
```

Using the above approach allows you to keep the bootstrap file minimal and
agnostic of environment. (Note: you can take a similar approach with
the application configuration.)
