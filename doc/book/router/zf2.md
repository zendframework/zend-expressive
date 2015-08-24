# Using the ZF2 Router

[zend-mvc](https://github.com/zendframework/zend-mvc) provides a router
implementation; for HTTP applications, the default used in ZF2 applications is
`Zend\Mvc\Router\Http\TreeRouteStack`, which can compose a number of different
routes of differing types in order to perform routing.

The ZF2 bridge we provide, `Zend\Expressive\Router\Zf`, uses the
`TreeRouteStack`, and injects `Segment` routes to it; these are in turn injected
with `Method` routes, and a special "method not allowed" route at negative
priority to enable us to distinguish between failure to match the path and
failure to match the HTTP method.

If you instantiate it with no arguments, it will create an empty
`TreeRouteStack`. Thus, the simplest way to start with this router is:

```php
use Zend\Expressive\AppFactory;
use Zend\Expressive\Router\Zf2 as Zf2Router;

$app = AppFactory(null, new Zf2Router());
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

To use the ZF2 router, you will need to install two dependencies,
`zendframework/zend-mvc`, and `zendframework/zend-psr7bridge`; the latter is
used to convert the PSR-7 `ServerRequestInterface` request instances used by
zend-expressive into zend-http equivalents to pass to the `TreeRouteStack`. You
can add these via Composer by executing the following in your project root:

```bash
$ composer require zendframework/zend-mvc zendframework/zend-psr7bridge
```

## Programmatic Creation

To configure the ZF2 router programmatically, you will need to setup the
`TreeRouteStack` instance manually, inject it into a
`Zend\Expressive\Router\Zf2` instance, and inject use that when creating your
`Application` instance.

```php
use Zend\Expressive\AppFactory;
use Zend\Expressive\Router\Zf2 as Zf2Bridge;
use Zend\Mvc\Router\Http\TreeRouteStack;

$zf2Router = new TreeRouteStack();
$zf2Router->addPrototypes(/* ... */);
$zf2Router->setBaseUrl(/* ... */);

$router = new Zf2Bridge($zf2Router);

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
defining two factories:

- A factory to register as and generate an `Zend\Mvc\Router\Http\TreeRouteStack`
  instance.
- A factory registered as `Zend\Expressive\Router\RouterInterface`, which
  creates and returns a `Zend\Expressive\Router\Zf2` instance composing the
  `Zend\Mvc\Router\Http\TreeRouteStack` instance.

Sound difficult? It's not; we've essentially done it above already!

```php
// in src/Application/Container/TreeRouteStackFactory.php:
namespace Application\Container;

use Interop\Container\ContainerInterface;
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

// in src/Application/Container/RouterFactory.php
namespace Application\Container;

use Interop\Container\ContainerInterface;
use Zend\Expressive\Router\Zf2 as Zf2Bridge;

class RouterFactory
{
    /**
     * @param ContainerInterface $container
     * @return Zf2Bridge
     */
    public function __invoke(ContainerInterface $container)
    {
        return new Zf2Bridge($container->get('Zend\Mvc\Router\Http\TreeRouteStack'));
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
    'Zend\Mvc\Router\Http\TreeRouteStack',
    'Application\Container\TreeRouteStackFactory'
);
$container->addFactory(
    'Zend\Expressive\Router\RouterInterface',
    'Application\Container\RouterFactory'
);

// Alternately, via configuration:
return [
    'factories' => [
        'Zend\Mvc\Router\Http\TreeRouteStack' => 'Application\Container\TreeRouteStackFactory',
        'Zend\Expressive\Router\RouterInterface' => 'Application\Container\RouterFactory',
    ],
];
```

For Pimple, configuration looks like:

```php
use Application\Container\TreeRouteStackFactory;
use Application\Container\ZfRouterFactory;
use Interop\Container\Pimple\PimpleInterop;

$container = new PimpleInterop();
$container['Zend\Mvc\Router\Http\TreeRouteStackFactory'] = new TreeRouteStackFactory();
$container['Zend\Expressive\Router\RouterInterface'] = new RouterFactory();
```
