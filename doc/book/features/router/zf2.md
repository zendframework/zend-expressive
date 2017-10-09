# Using the ZF2 Router

[zend-mvc](https://github.com/zendframework/zend-mvc) provides a router
implementation; for HTTP applications, the default used in ZF2 applications is
`Zend\Mvc\Router\Http\TreeRouteStack`, which can compose a number of different
routes of differing types in order to perform routing.

The ZF2 bridge we provide, `Zend\Expressive\Router\ZendRouter`, uses the
`TreeRouteStack`, and injects `Segment` routes to it; these are in turn injected
with `Method` routes, and a special "method not allowed" route at negative
priority to enable us to distinguish between failure to match the path and
failure to match the HTTP method.

If you instantiate it with no arguments, it will create an empty
`TreeRouteStack`. Thus, the simplest way to start with this router is:

```php
use Zend\Expressive\AppFactory;
use Zend\Expressive\Router\ZendRouter;

$app = AppFactory::create(null, new ZendRouter());
```

The `TreeRouteStack` offers some unique features:

- Route "prototypes". These are essentially like child routes that must *also*
  match in order for a given route to match. These are useful for implementing
  functionality such as ensuring the request comes in over HTTPS, or over a
  specific subdomain.
- Base URL functionality. If a base URL is injected, comparisons will be
  relative to that URL. This is mostly unnecessary with Stratigility-based
  middleware, but could solve some edge cases.

To specify these, you need access to the underlying `TreeRouteStack`
instance, however, and the `RouterInterface` does not provide an accessor!

The answer, then, is to use dependency injection. This can be done in two ways:
programmatically, or via a factory to use in conjunction with your container
instance.

## Installing the ZF2 Router

To use the ZF2 router, you will need to install the zend-mvc router integration:

```bash
$ composer require zendframework/zend-expressive-zendrouter
```

## Quick Start

At its simplest, you can instantiate a `Zend\Expressive\Router\ZendRouter` instance
with no arguments; it will create the underlying zend-mvc routing objects
required and compose them for you:

```php
use Zend\Expressive\Router\ZendRouter;

$router = new ZendRouter();
```

## Programmatic Creation

If you need greater control over the zend-mvc router setup and configuration,
you can create the instances necessary and inject them into
`Zend\Expressive\Router\ZendRouter` during instantiation.

```php
use Zend\Expressive\AppFactory;
use Zend\Expressive\Router\ZendRouter as Zf2Bridge;
use Zend\Mvc\Router\Http\TreeRouteStack;

$zendRouter = new TreeRouteStack();
$zendRouter->addPrototypes(/* ... */);
$zendRouter->setBaseUrl(/* ... */);

$router = new Zf2Bridge($zendRouter);

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
two strategies for creating your zend-mvc router implementation.

### Basic Router

If you don't need to provide any setup or configuration, you can simply
instantiate and return an instance of `Zend\Expressive\Router\ZendRouter` for the
service name `Zend\Expressive\Router\RouterInterface`.

A factory would look like this:

```php
// in src/App/Container/RouterFactory.php
namespace App\Container;

use Psr\Container\ContainerInterface;
use Zend\Expressive\Router\ZendRouter;

class RouterFactory
{
    /**
     * @param ContainerInterface $container
     * @return ZendRouter
     */
    public function __invoke(ContainerInterface $container)
    {
        return new ZendRouter();
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
$pimple[Zend\Expressive\Router\RouterInterface::class] = new Application\Container\RouterFactory();
```

For zend-servicemanager, you can omit the factory entirely, and register the
class as an invokable:

```php
$container->setInvokableClass(
    Zend\Expressive\Router\RouterInterface::class,
    Zend\Expressive\Router\ZendRouter::class
);
```

### Advanced Configuration

If you want to provide custom setup or configuration, you can do so. In this
example, we will be defining two factories:

- A factory to register as and generate an `Zend\Mvc\Router\Http\TreeRouteStack`
  instance.
- A factory registered as `Zend\Expressive\Router\RouterInterface`, which
  creates and returns a `Zend\Expressive\Router\ZendRouter` instance composing the
  `Zend\Mvc\Router\Http\TreeRouteStack` instance.

Sound difficult? It's not; we've essentially done it above already!

```php
// in src/App/Container/TreeRouteStackFactory.php:
namespace App\Container;

use Psr\Container\ContainerInterface;
use Zend\Http\Mvc\Router\TreeRouteStack;

class TreeRouteStackFactory
{
    /**
     * @param ContainerInterface $container
     * @return TreeRouteStack
     */
    public function __invoke(ContainerInterface $container)
    {
        $router = new TreeRouteStack();
        $router->addPrototypes(/* ... */);
        $router->setBaseUrl(/* ... */);

        return $router;
    }
}

// in src/App/Container/RouterFactory.php
namespace App\Container;

use Psr\Container\ContainerInterface;
use Zend\Expressive\Router\ZendRouter as Zf2Bridge;

class RouterFactory
{
    /**
     * @param ContainerInterface $container
     * @return Zf2Bridge
     */
    public function __invoke(ContainerInterface $container)
    {
        return new Zf2Bridge($container->get(Zend\Mvc\Router\Http\TreeRouteStack::class));
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
    Zend\Mvc\Router\Http\TreeRouteStack::class,
    App\Container\TreeRouteStackFactory::class
);
$container->addFactory(
    Zend\Expressive\Router\RouterInterface::class,
    App\Container\RouterFactory::class
);

// Alternately, via configuration:
return [
    'factories' => [
        Zend\Mvc\Router\Http\TreeRouteStack::class => App\Container\TreeRouteStackFactory::class,
        Zend\Expressive\Router\RouterInterface::class => App\Container\RouterFactory::class,
    ],
];
```

For Pimple, configuration looks like:

```php
use Application\Container\TreeRouteStackFactory;
use Application\Container\ZfRouterFactory;
use Interop\Container\Pimple\PimpleInterop;

$container = new PimpleInterop();
$container[Zend\Mvc\Router\Http\TreeRouteStackFactory::class] = new TreeRouteStackFactory();
$container[Zend\Expressive\Router\RouterInterface::class] = new RouterFactory();
```
