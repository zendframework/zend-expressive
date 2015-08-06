# ZF2 Router Usage

[zend-mvc](https://github.com/zendframework/zend-mvc) provides a router
implementation; for HTTP applications, the default used in ZF2 applications it
`Zend\Mvc\Router\Http\TreeRouteStack`, which can compose a number of different
routes of differing types in order to perform routing.

The ZF2 bridge we provide uses the `TreeRouteStack`, and injects `Segment`
routes to it. We emulate HTTP method negotiation in the implementation, however,
instead of creating `Method` routes, as the `TreeRouteStack` does not
differentiate between failure to route and failure due to HTTP method
negotiation.

To use the ZF2 router, you will need to install two dependencies,
`zendframework/zend-mvc`, and `zendframework/zend-psr7bridge`; the latter is
used to convert the PSR-7 `ServerRequestInterface` request instances used by
zend-expressive into zend-http equivalents to pass to the `TreeRouteStack`. You
can add these via Composer by executing the following in your project root:

```bash
$ composer require zendframework/zend-mvc zendframework/zend-psr7bridge
```

`Zend\Expressive\Router\Zf2` is the zend-expressive router implementation that
consumes a `TreeRouteStack`. If you instantiate it with no arguments, it will
create an empty `TreeRouteStack`. Thus, the simplest way to start with this
router is:

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

## Programmatic Creation

To handle it programmatically, you will need to setup the `TreeRouteStack` instance
manually, inject it into a `Zend\Expressive\Router\Zf2` instance, and inject
use that when creating your `Application` instance.

```php
<?php
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

- A factory to register as and generate an `Zend\Mvc\Router\Http\TreeRouteStack`
  instance.
- A factory registered as `Zend\Expressive\Router\RouterInterface`, which
  creates and returns a `Zend\Expressive\Router\Zf2` instance composing the
  `Zend\Mvc\Router\Http\TreeRouteStack` instance.

Sound difficult? It's not; we've essentially done it above already!

```php
<?php
// in src/Application/Container/TreeRouteStackFactory.php:
namespace Application\Container;

use Interop\Container\ContainerInterface;
use Zend\Http\Mvc\Router\TreeRouteStack;

class TreeRouteStackFactory
{
    /**
     * @param ContainerInterface $services
     * @return TreeRouteStack
     */
    public function __invoke(ContainerInterface $services)
    {
        $router = new TreeRouteStack();
        $router->addPrototypes(/* ... */);
        $router->setBaseUrl(/* ... */);

        return $router;
    }
}

// in src/Application/Container/Zf2RouterFactory.php
namespace Application\Container;

use Interop\Container\ContainerInterface;
use Zend\Expressive\Router\Zf2 as Zf2Bridge;

class Zf2RouterFactory
{
    /**
     * @param ContainerInterface $services
     * @return Zf2Bridge
     */
    public function __invoke(ContainerInterface $services)
    {
        return new Zf2Bridge($services->get('Zend\Mvc\Router\Http\TreeRouteStack'));
    }
}
```

From here, you will need to register your factories with your IoC container.

If you are using `Zend\ServiceManager`, this might look like the following:

```php
use Zend\ServiceManager\ServiceManager;

$services = new ServiceManager();
$services->addFactory(
    'Zend\Mvc\Router\Http\TreeRouteStack',
    'Application\Container\TreeRouteStackFactory'
);
$services->addFactory(
    'Zend\Expressive\Router\RouterInterface',
    'Application\Container\Zf2RouterFactory'
);

// alternately, via service_manager configuration:
return [
    'service_manager' => [
        'factories' => [
            'Zend\Mvc\Router\Http\TreeRouteStack' => 'Application\Container\TreeRouteStackFactory',
            'Zend\Expressive\Router\RouterInterface' => 'Application\Container\Zf2RouterFactory',
        ],
    ],
];
```

[Pimple-interop](https://github.com/moufmouf/pimple-interop) is a version of
[Pimple](http://pimple.sensiolabs.org/) that supports
[container-interop](https://github.com/container-interop/container-interop).
Configuration of that container looks like the following.

```php
use Application\Container\TreeRouteStackFactory;
use Application\Container\ZfRouterFactory;
use Interop\Container\Pimple\PimpleInterop;

$pimple = new PimpleInterop();
$pimple['Zend\Mvc\Router\Http\TreeRouteStackFactory'] = new TreeRouteStackFactory();
$pimple['Zend\Expressive\Router\RouterInterface'] = new Zf2RouterFactory();
```
