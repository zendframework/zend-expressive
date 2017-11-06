# Using Aura.Router

[Aura.Router](https://github.com/auraphp/Aura.Router) provides a plethora of
methods for further configuring the router instance. One of the more useful
configuration is to provide default specifications:

- A regular expression that applies the same for a given routing match:

  ```php
  // Parameters named "id" will only match digits by default:
  $router->addTokens([
    'id' => '\d+',
  ]);
  ```

- A default parameter and/or its default value to always provide:

  ```php
  // mediatype defaults to "application/xhtml+xml" and will be available in all
  // requests:
  $router->addValues([
    'mediatype' => 'application/xhtml+xml',
  ]);
  ```

- Only match if secure (i.e., under HTTPS):

  ```php
  $router->setSecure(true);
  ```

In order to specify these, you need access to the underlying Aura.Router
instance, however, and the `RouterInterface` does not provide an accessor!

The answer, then, is to use dependency injection. This can be done in two ways:
programmatically, or via a factory to use in conjunction with your container
instance.

## Installing Aura.Router

To use Aura.Router, you will first need to install the Aura.Router integration:

```bash
$ composer require zendframework/zend-expressive-aurarouter
```

## Quick Start

At its simplest, you can instantiate a `Zend\Expressive\Router\AuraRouter` instance
with no arguments; it will create the underlying Aura.Router objects required
and compose them for you:

```php
use Zend\Expressive\Router\AuraRouter;

$router = new AuraRouter();
```

## Programmatic Creation

If you need greater control over the Aura.Router setup and configuration, you
can create the instances necessary and inject them into
`Zend\Expressive\Router\AuraRouter` during instantiation.

```php
<?php
use Aura\Router\RouterFactory;
use Zend\Expressive\AppFactory;
use Zend\Expressive\Router\AuraRouter as AuraBridge;

$auraRouter = (new RouterFactory())->newInstance();
$auraRouter->setSecure(true);
$auraRouter->addValues([
    'mediatype' => 'application/xhtml+xml',
]);

$router = new AuraBridge($auraRouter);

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
two strategies for creating your Aura.Router implementation.

### Basic Router

If you don't need to provide any setup or configuration, you can simply
instantiate and return an instance of `Zend\Expressive\Router\AuraRouter` for the
service name `Zend\Expressive\Router\RouterInterface`.

A factory would look like this:

```php
// in src/Application/Container/RouterFactory.php
namespace Application\Container;

use Psr\Container\ContainerInterface;
use Zend\Expressive\Router\AuraRouter;

class RouterFactory
{
    /**
     * @param ContainerInterface $container
     * @return AuraRouter
     */
    public function __invoke(ContainerInterface $container)
    {
        return new AuraRouter();
    }
}
```

You would register this with zend-servicemanager using:

```php
$container->setFactory(
    Zend\Expressive\Router\RouterInterface::class,
    Application\Container\RouterFactory::class
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
    Zend\Expressive\Router\AuraRouter::class
);
```

### Advanced Configuration

If you want to provide custom setup or configuration, you can do so. In this
example, we will be defining two factories:

- A factory to register as and generate an `Aura\Router\Router` instance.
- A factory registered as `Zend\Expressive\Router\RouterInterface`, which
  creates and returns a `Zend\Expressive\Router\AuraRouter` instance composing the
  `Aura\Router\Router` instance.

Sound difficult? It's not; we've essentially done it above already!

```php
// in src/Application/Container/AuraRouterFactory.php:
namespace Application\Container;

use Aura\Router\RouterFactory;
use Psr\Container\ContainerInterface;

class AuraRouterFactory
{
    /**
     * @param ContainerInterface $container
     * @return \Aura\Router\Router
     */
    public function __invoke(ContainerInterface $container)
    {
        $router = (new RouterFactory())->newInstance();
        $router->setSecure(true);
        $router->addValues([
            'mediatype' => 'application/xhtml+xml',
        ]);

        return $router;
    }
}

// in src/Application/Container/RouterFactory.php
namespace Application\Container;

use Psr\Container\ContainerInterface;
use Zend\Expressive\Router\AuraRouter as AuraBridge;

class RouterFactory
{
    /**
     * @param ContainerInterface $container
     * @return AuraBridge
     */
    public function __invoke(ContainerInterface $container)
    {
        return new AuraBridge($container->get('Aura\Router\Router'));
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
    'Aura\Router\Router',
    Application\Container\AuraRouterFactory::class
);
$container->addFactory(
    Zend\Expressive\Router\RouterInterface::class,
    'Application\Container\RouterFactory'
);

// Alternately, via configuration:
return [
    'factories' => [
        'Aura\Router\Router' => Application\Container\AuraRouterFactory::class,
        Zend\Expressive\Router\RouterInterface::class => 'Application\Container\RouterFactory::class,
    ],
];
```

For Pimple, configuration looks like:

```php
use Application\Container\AuraRouterFactory;
use Application\Container\RouterFactory;
use Interop\Container\Pimple\PimpleInterop as Pimple;

$container = new Pimple();
$container['Aura\Router\Router'] = new AuraRouterFactory();
$container[Zend\Expressive\Router\RouterInterface::class] = new RouterFactory();
```
