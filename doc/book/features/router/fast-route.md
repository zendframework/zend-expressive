# Using FastRoute

[FastRoute](https://github.com/nikic/FastRoute) provides a number of different
combinations for how to both parse routes and match incoming requests against
them.

Internally, we use the standard route parser (`FastRoute\RouterParser\Std`) to
parse routes, a `RouteCollector` to collect them, and the "Group Count Based"
dispatcher to match incoming requests against routes.

If you wish to use a different combination — e.g., to use the Group Position
Based route matcher — you will need to create your own instances and inject them
into the `Zend\Expressive\Router\FastRouteRouter` class, at instantiation.

The `FastRouteRouter` bridge class accepts two arguments at instantiation:

- A `FastRoute\RouteCollector` instance
- A callable that will return a `FastRoute\Dispatcher\RegexBasedAbstract`
  instance.

Injection can be done either programmatically or via a factory to use in
conjunction with your container instance.

## Installing FastRoute

To use FastRoute, you will first need to install the FastRoute integration:

```bash
$ composer require zendframework/zend-expressive-fastroute
```

## Quick Start

At its simplest, you can instantiate a `Zend\Expressive\Router\FastRouteRouter` instance
with no arguments; it will create the underlying FastRoute objects required
and compose them for you:

```php
use Zend\Expressive\Router\FastRouteRouter;

$router = new FastRouteRouter();
```

## Programmatic Creation

If you need greater control over the FastRoute setup and configuration, you
can create the instances necessary and inject them into
`Zend\Expressive\Router\FastRouteRouter` during instantiation.

To do so, you will need to setup your `RouteCollector` instance and/or
optionally callable to return your `RegexBasedAbstract` instance manually,
inject them in your `Zend\Expressive\Router\FastRouteRouter` instance, and inject use
that when creating your `Application` instance.

```php
<?php
use FastRoute\Dispatcher\GroupPosBased as FastRouteDispatcher;
use FastRoute\RouteCollector;
use FastRoute\RouteGenerator;
use FastRoute\RouteParser\Std as RouteParser;
use Zend\Expressive\AppFactory;
use Zend\Expressive\Router\FastRouteRouter as FastRouteBridge;

$fastRoute = new RouteCollector(
    new RouteParser(),
    new RouteGenerator()
);
$getDispatcher = function ($data) {
    return new FastRouteDispatcher($data);
};


$router = new FastRouteBridge($fastRoute, $getDispatcher);

// First argument is the container to use, if not using the default;
// second is the router.
$app = AppFactory::create(null, $router);
```

> ### Piping the route middleware
>
> As a reminder, you will need to ensure that middleware is piped in the order
> in which it needs to be executed; please see the section on "Controlling
> middleware execution order" in the [piping documentation](piping.md). This is
> particularly salient when defining routes before injecting the router in the
> application instance!

## Factory-Driven Creation

[We recommend using an Inversion of Control container](../container/intro.md)
for your applications; as such, in this section we will demonstrate 
two strategies for creating your FastRoute implementation.

### Basic Router

If you don't need to provide any setup or configuration, you can simply
instantiate and return an instance of `Zend\Expressive\Router\FastRouteRouter` for the
service name `Zend\Expressive\Router\RouterInterface`.

A factory would look like this:

```php
// in src/App/Container/RouterFactory.php
namespace App\Container;

use Psr\Container\ContainerInterface;
use Zend\Expressive\Router\FastRouteRouter;

class RouterFactory
{
    /**
     * @param ContainerInterface $container
     * @return FastRouteRouter
     */
    public function __invoke(ContainerInterface $container)
    {
        return new FastRouteRouter();
    }
}
```

You would register this with zend-servicemanager using:

```php
$container->setFactory(
    Zend\Expressive\Router\RouterInterface::class,
    App\Container\RouterFactory::class
);
```

And in Pimple:

```php
$pimple[Zend\Expressive\Router\RouterInterface::class] = new App\Container\RouterFactory();
```

For zend-servicemanager, you can omit the factory entirely, and register the
class as an invokable:

```php
$container->setInvokableClass(
    Zend\Expressive\Router\RouterInterface::class,
    Zend\Expressive\Router\FastRouteRouter::class
);
```

### Advanced Configuration

If you want to provide custom setup or configuration, you can do so. In this
example, we will be defining three factories:

- A factory to register as and generate a `FastRoute\RouteCollector` instance.
- A factory to register as `FastRoute\DispatcherFactory` and return a callable
  factory that returns a `RegexBasedAbstract` instance.
- A factory registered as `Zend\Expressive\Router\RouterInterface`, which
  creates and returns a `Zend\Expressive\Router\FastRouteRouter` instance composing the
  two services.

Sound difficult? It's not; we've essentially done it above already!

```php
<?php
// in src/App/Container/FastRouteCollectorFactory.php:
namespace App\Container;

use FastRoute\RouteCollector;
use FastRoute\RouteGenerator;
use FastRoute\RouteParser\Std as RouteParser;
use Psr\Container\ContainerInterface;

class FastRouteCollectorFactory
{
    /**
     * @param ContainerInterface $container
     * @return RouteCollector
     */
    public function __invoke(ContainerInterface $container)
    {
        return new RouteCollector(
            new RouteParser(),
            new RouteGenerator()
        );
    }
}

// in src/App/Container/FastRouteDispatcherFactory.php:
namespace App\Container;

use FastRoute\Dispatcher\GroupPosBased as FastRouteDispatcher;
use Psr\Container\ContainerInterface;

class FastRouteDispatcherFactory
{
    /**
     * @param ContainerInterface $container
     * @return callable
     */
    public function __invoke(ContainerInterface $container)
    {
        return function ($data) {
            return new FastRouteDispatcher($data);
        };
    }
}

// in src/App/Container/RouterFactory.php
namespace App\Container;

use Psr\Container\ContainerInterface;
use Zend\Expressive\Router\FastRouteRouter as FastRouteBridge;

class RouterFactory
{
    /**
     * @param ContainerInterface $container
     * @return FastRouteBridge
     */
    public function __invoke(ContainerInterface $container)
    {
        return new FastRouteBridge(
            $container->get(FastRoute\RouteCollector::class),
            $container->get(FastRoute\DispatcherFactory::class)
        );
    }
}
```

From here, you will need to register your factories with your IoC container.

If you are using zend-servicemanager, this will look like:

```php
// Programmatically:
use Zend\ServiceManager\ServiceManager;

$container = new ServiceManager();
$container->addFactory(
    FastRoute\RouteCollector::class,
    App\Container\FastRouteCollectorFactory::class
);
$container->addFactory(
    FastRoute\DispatcherFactory::class,
    App\Container\FastRouteDispatcherFactory::class
);
$container->addFactory(
    Zend\Expressive\Router\RouterInterface::class,
    App\Container\RouterFactory::class
);

// Alternately, via configuration:
return [
    'factories' => [
        'FastRoute\RouteCollector' => App\Container\FastRouteCollectorFactory::class,
        'FastRoute\DispatcherFactory' => App\Container\FastRouteDispatcherFactory::class,
        Zend\Expressive\Router\RouterInterface::class => App\Container\RouterFactory::class,
    ],
];
```

For Pimple, configuration looks like:

```php
use App\Container\FastRouteCollectorFactory;
use App\Container\FastRouteDispatcherFactory;
use App\Container\RouterFactory;
use Interop\Container\Pimple\PimpleInterop as Pimple;

$container = new Pimple();
$container[FastRoute\RouteCollector::class] = new FastRouteCollectorFactory();
$container[FastRoute\RouteDispatcher::class] = new FastRouteDispatcherFactory();
$container[Zend\Expressive\Router\RouterInterface::class] = new RouterFactory();
```

### FastRoute caching support

- Since zend-expressive-fastroute 1.3.0.

Starting from version 1.3.0, zend-expressive-fastroute comes with support 
for FastRoute native dispatch data caching.

Enabling this feature requires changes to your configuration. Typically, router
configuration occurs in `config/autoload/routes.global.php`; as such, we will
reference that file when indicating configuration changes.

The changes required are:

- You will need to delegate creation of the router instance to a new factory.

- You will need to add a new configuration entry, `$config['router']['fastroute']`. 
  The options in this entry will be used by the factory to build the router
  instance in order to toggle caching support and to specify a custom cache
  file.

As an example:

``` php
// File config/autoload/routes.global.php

return [
    'dependencies' => [
        //..
        'invokables' => [
            /* ... */
            // Comment out or remove the following line:
            // Zend\Expressive\Router\RouterInterface::class => Zend\Expressive\Router\FastRouteRouter::class,
            /* ... */
        ],
        'factories' => [
            /* ... */
            // Add this line; the specified factory now creates the router instance:
            Zend\Expressive\Router\RouterInterface::class => Zend\Expressive\Router\FastRouteRouterFactory::class,
            /* ... */
        ],
    ],
    
    // Add the following to enable caching support:
    'router' => [
        'fastroute' => [
             // Enable caching support:
            'cache_enabled' => true,
             // Optional (but recommended) cache file path:
            'cache_file'    => 'data/cache/fastroute.php.cache',
        ],
    ],

    'routes' => [ /* ... */ ],
]
```

The FastRoute-specific caching options are as follows:

- `cache_enabled` (bool) is used to toggle caching support. It's advisable to enable 
  caching in a production environment and leave it disabled for the development
  environment. Commenting or omitting this option is equivalent to having it set
  to `false`. We recommend enabling it in `config/autoload/routes.global.php`,
  and, in development, disabling it within `config/autoload/routes.local.php` or
  `config/autoload/local.php`.

- `cache_file` (string) is an optional parameter that represents the path of 
  the dispatch data cache file. It can be provided as an absolute file path or
  as a path relative to the zend-expressive working directory. 

  It defaults to `data/cache/fastroute.php.cache`, where `data/cache/` is the
  cache directory defined within the zend-expressive skeleton application.  An
  explicit absolute file path is recommended since the php `include` construct
  will skip searching the `include_path` and the current directory.

  If you choose a custom path, make sure that the directory exists and is
  writable by the owner of the PHP process. As with any other zend-expressive
  cached configuration, you will need to purge this file in order to enable any
  newly added route when FastRoute caching is enabled.
