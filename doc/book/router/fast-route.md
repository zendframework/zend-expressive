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
use Zend\Expressive\Router\FastRoute;

$router = new FastRoute();
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
use FastRoute;
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
// in src/Application/Container/RouterFactory.php
namespace Application\Container;

use Interop\Container\ContainerInterface;
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
    'Zend\Expressive\Router\RouterInterface',
    'Application\Container\RouterFactory'
);
```

And in Pimple:

```php
$pimple['Zend\Expressive\Router\RouterInterface'] = new Application\Container\RouterFactory();
```

For zend-servicemanager, you can omit the factory entirely, and register the
class as an invokable:

```php
$container->setInvokableClass(
    'Zend\Expressive\Router\RouterInterface',
    'Zend\Expressive\Router\FastRouteRouter'
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
// in src/Application/Container/FastRouteCollectorFactory.php:
namespace Application\Container;

use FastRoute\RouteCollector;
use FastRoute\RouteGenerator;
use FastRoute\RouteParser\Std as RouteParser;
use Interop\Container\ContainerInterface;

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

// in src/Application/Container/FastRouteDispatcherFactory:
namespace Application\Container;

use FastRoute\Dispatcher\GroupPosBased as FastRouteDispatcher;
use Interop\Container\ContainerInterface;

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

// in src/Application/Container/RouterFactory.php
namespace Application\Container;

use Interop\Container\ContainerInterface;
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
            $container->get('FastRoute\RouteCollector'),
            $container->get('FastRoute\DispatcherFactory'),
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
    'FastRoute\RouteCollector',
    'Application\Container\FastRouteCollectorFactory'
);
$container->addFactory(
    'FastRoute\DispatcherFactory',
    'Application\Container\FastRouteDispatcherFactory'
);
$container->addFactory(
    'Zend\Expressive\Router\RouterInterface',
    'Application\Container\RouterFactory'
);

// Alternately, via configuration:
return [
    'factories' => [
        'FastRoute\RouteCollector' => 'Application\Container\FastRouteCollectorFactory',
        'FastRoute\DispatcherFactory' => 'Application\Container\FastRouteDispatcherFactory',
        'Zend\Expressive\Router\RouterInterface' => 'Application\Container\RouterFactory',
    ],
];
```

For Pimple, configuration looks like:

```php
use Application\Container\FastRouteCollectorFactory;
use Application\Container\FastRouteDispatcherFactory;
use Application\Container\RouterFactory;
use Interop\Container\Pimple\PimpleInterop as Pimple;

$container = new Pimple();
$container['FastRoute\RouteCollector'] = new FastRouteCollectorFactory();
$container['FastRoute\RouteDispatcher'] = new FastRouteDispatcherFactory();
$container['Zend\Expressive\Router\RouterInterface'] = new RouterFactory();
```
