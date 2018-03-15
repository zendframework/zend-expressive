# Using zend-router

[zend-router](https://docs.zendframework.com/zend-router/) provides several
router implementations used for ZF2+ applications; the default is
`Zend\Router\Http\TreeRouteStack`, which can compose a number of different
routes of differing types in order to perform routing.

The ZF2 bridge we provide, `Zend\Expressive\Router\ZendRouter`, uses the
`TreeRouteStack`, and injects `Segment` routes to it; these are in turn injected
with `Method` routes, and a special "method not allowed" route at negative
priority to enable us to distinguish between failure to match the path and
failure to match the HTTP method.

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

The package provides both a factory for the router, and a `ConfigProvider` that
wires the router with your application.

## Advanced configuration

If you want to provide custom setup or configuration, you can do so. In this
example, we will be defining two factories:

- A factory to register as and generate an `Zend\Router\Http\TreeRouteStack`
  instance.
- A factory registered as `Zend\Expressive\Router\RouterInterface`, which
  creates and returns a `Zend\Expressive\Router\ZendRouter` instance composing the
  `Zend\Mvc\Router\Http\TreeRouteStack` instance.

The factories might look like the following:

```php
// in src/App/Container/TreeRouteStackFactory.php:
namespace App\Container;

use Psr\Container\ContainerInterface;
use Zend\Http\Router\TreeRouteStack;

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

```php
// in a config/autoload/ file, or within a ConfigProvider class:
return [
    'factories' => [
        \Zend\Router\Http\TreeRouteStack::class => App\Container\TreeRouteStackFactory::class,
        \Zend\Expressive\Router\RouterInterface::class => App\Container\RouterFactory::class,
    ],
];
```
