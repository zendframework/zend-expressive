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

We provide and enable a factory for generating your Aura.Router instance when
you install the zend-expressive-aurarouter package. This will generally serve
your needs.

If you want to provide custom setup or configuration, you can do so. In this
example, we will be defining two factories:

- A factory to register as and generate an `Aura\Router\Router` instance.
- A factory registered as `Zend\Expressive\Router\RouterInterface`, which
  creates and returns a `Zend\Expressive\Router\AuraRouter` instance composing the
  `Aura\Router\Router` instance.

The factory might look like this:

```php
// in src/App/Container/AuraRouterFactory.php:
namespace App\Container;

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

// in src/App/Container/RouterFactory.php
namespace App\Container;

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

From here, you will need to register your factories with your IoC container:

```php
// in a config/autoload/ file, or within a ConfigProvider class:
return [
    'factories' => [
        \Aura\Router\Router::class => \App\Container\AuraRouterFactory::class,
        \Zend\Expressive\Router\RouterInterface::class => \App\Container\RouterFactory::class,
    ],
];
```
