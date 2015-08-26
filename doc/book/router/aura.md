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

To use Aura.Router, you will first need to install it:

```bash
$ composer require aura/router
```

## Programmatic Creation

To handle it programmatically, you will need to setup the Aura.Router instance
manually, inject it into a `Zend\Expressive\Router\Aura` instance, and inject
use that when creating your `Application` instance.

```php
<?php
use Aura\Router\RouterFactory;
use Zend\Expressive\AppFactory;
use Zend\Expressive\Router\Aura as AuraBridge;

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

### Piping the route middleware

As a reminder, you will need to ensure that middleware is piped in the order
in which it needs to be executed; please see the section on "Controlling
middleware execution order" in the [piping documentation](piping.md). This is
particularly salient when defining routes before injecting the router in the
application instance!

## Factory-Driven Creation

[We recommend using an Inversion of Control container](../container/intro.md)
for your applications; as such, in this section we will demonstrate 
defining two factories:

- A factory to register as and generate an `Aura\Router\Router` instance.
- A factory registered as `Zend\Expressive\Router\RouterInterface`, which
  creates and returns a `Zend\Expressive\Router\Aura` instance composing the
  `Aura\Router\Router` instance.

Sound difficult? It's not; we've essentially done it above already!

```php
// in src/Application/Container/AuraRouterFactory.php:
namespace Application\Container;

use Aura\Router\RouterFactory;
use Interop\Container\ContainerInterface;

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

use Interop\Container\ContainerInterface;
use Zend\Expressive\Router\Aura as AuraBridge;

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
    'Application\Container\AuraRouterFactory'
);
$container->addFactory(
    'Zend\Expressive\Router\RouterInterface',
    'Application\Container\RouterFactory'
);

// Alternately, via configuration:
return [
    'factories' => [
        'Aura\Router\Router' => 'Application\Container\AuraRouterFactory',
        'Zend\Expressive\Router\RouterInterface' => 'Application\Container\RouterFactory',
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
$container['Zend\Expressive\Router\RouterInterface'] = new RouterFactory();
```
