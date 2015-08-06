# FastRoute Usage

[FastRoute](https://github.com/nikic/FastRoute) provides a number of different
combinations for how to both parse routes and match incoming requests against
them.

To use FastRoute, you will first need to install it:

```bash
$ composer require nikic/fast-route
```

Internally, we use the standard route parser (`FastRoute\RouterParser\Std`) to
parse routes, a `RouteCollector` to collect them, and the "Group Count Based"
dispatcher to match incoming requests against routes.

If you wish to use a different combination — e.g., to use the Group Position
Based route matcher — you will need to create your own instances and inject them
into the `Zend\Expressive\Router\FastRoute` class, at instantiation.

The `FastRoute` bridge class accepts two arguments at instantiation:

- A `FastRoute\RouteCollector` instance
- A callable that will return a `FastRoute\Dispatcher\RegexBasedAbstract`
  instance.

Injection can be done either programmatically or via a factory to use in
conjunction with your container instance.

## Programmatic Creation

To handle it programmatically, you will need to setup your `RouteCollector`
instance and/or optionally callable to return your `RegexBasedAbstract` instance
manually, inject them in your `Zend\Expressive\Router\FastRoute` instance, and
inject use that when creating your `Application` instance.

```php
<?php
use FastRoute;
use FastRoute\Dispatcher\GroupPosBased as FastRouteDispatcher;
use FastRoute\RouteCollector;
use FastRoute\RouteGenerator;
use FastRoute\RouteParser\Std as RouteParser;
use Zend\Expressive\AppFactory;
use Zend\Expressive\Router\FastRoute as FastRouteBridge;

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

## Factory-Driven Creation

We recommend using an Inversion of Control container for your applications;
doing so provides the ability to substitute alternate implementations, and
removes the logic of creating instances from your code, so you can focus on the
business logic.

Some containers will auto-wire based on discovery in your code. Other IoC
containers require your to register factories with the code for
creating and configuring your instances. We tend to prefer code-driven
factories, as they allow you to fully shape the instantiation and configuration
process.

In this case, we'll define two factories:

- A factory to register as and generate a `FastRoute\RouteCollector` instance.
- A factory to register as `FastRoute\DispatcherFactory` and return a callable
  factory that returns a `RegexBasedAbstract` instance.
- A factory registered as `Zend\Expressive\Router\RouterInterface`, which
  creates and returns a `Zend\Expressive\Router\FastRoute` instance composing the
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
     * @param ContainerInterface $services
     * @return RouteCollector
     */
    public function __invoke(ContainerInterface $services)
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
     * @param ContainerInterface $services
     * @return callable
     */
    public function __invoke(ContainerInterface $services)
    {
        return function ($data) {
            return new FastRouteDispatcher($data);
        };
    }
}

// in src/Application/Container/RouterFactory.php
namespace Application\Container;

use Interop\Container\ContainerInterface;
use Zend\Expressive\Router\FastRoute as FastRouteBridge;

class FastRouteFactory
{
    /**
     * @param ContainerInterface $services
     * @return FastRouteBridge
     */
    public function __invoke(ContainerInterface $services)
    {
        return new FastRouteBridge(
            $services->get('FastRoute\RouteCollector'),
            $services->get('FastRoute\DispatcherFactory'),
        );
    }
}
```

From here, you will need to register your factories with your IoC container.

If you are using `Zend\ServiceManager`, this might look like the following:

```php
use Zend\ServiceManager\ServiceManager;

$services = new ServiceManager();
$services->addFactory(
    'FastRoute\RouteCollector',
    'Application\Container\FastRouteCollectorFactory'
);
$services->addFactory(
    'FastRoute\DispatcherFactory',
    'Application\Container\FastRouteDispatcherFactory'
);
$services->addFactory(
    'Zend\Expressive\Router\RouterInterface',
    'Application\Container\RouterFactory'
);

// alternately, via service_manager configuration:
return [
    'service_manager' => [
        'factories' => [
            'FastRoute\RouteCollector' => 'Application\Container\FastRouteCollectorFactory',
            'FastRoute\DispatcherFactory' => 'Application\Container\FastRouteDispatcherFactory',
            'Zend\Expressive\Router\RouterInterface' => 'Application\Container\RouterFactory',
        ],
    ],
];
```

[Pimple-interop](https://github.com/moufmouf/pimple-interop) is a version of
[Pimple](http://pimple.sensiolabs.org/) that supports
[container-interop](https://github.com/container-interop/container-interop).
Configuration of that container looks like the following.

```php
use Application\Container\FastRouteCollectorFactory;
use Application\Container\FastRouteDispatcherFactory;
use Application\Container\RouterFactory;
use Interop\Container\Pimple\PimpleInterop;

$pimple = new PimpleInterop();
$pimple['FastRoute\RouteCollector'] = new FastRouteCollectorFactory();
$pimple['FastRoute\RouteDispatcher'] = new FastRouteDispatcherFactory();
$pimple['Zend\Expressive\Router\RouterInterface'] = new RouterFactory();
```
