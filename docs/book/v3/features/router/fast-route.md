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

The package provides a factory for the router, and wires it to your container by
default. This will serve the majority of use cases.

If you want to provide custom setup or configuration, you can do so. In this
example, we will be defining three factories:

- A factory to register as and generate a `FastRoute\RouteCollector` instance.
- A factory to register as `FastRoute\DispatcherFactory` and return a callable
  factory that returns a `RegexBasedAbstract` instance.
- A factory registered as `Zend\Expressive\Router\RouterInterface`, which
  creates and returns a `Zend\Expressive\Router\FastRouteRouter` instance
  composing the two services.

The factories might look like the following:

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

```php
// in a config/autoload/ file, or within a ConfigProvider class:
return [
    'factories' => [
        \FastRoute\RouteCollector::class => \App\Container\FastRouteCollectorFactory::class,
        \FastRoute\DispatcherFactory::class => \App\Container\FastRouteDispatcherFactory::class,
        \Zend\Expressive\Router\RouterInterface::class => \App\Container\RouterFactory::class,
    ],
];
```

### FastRoute caching support

zend-expressive-fastroute comes with support for FastRoute native dispatch data
caching.

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
