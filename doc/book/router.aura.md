# Aura.Router Usage

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

> ### Piping the route middleware
>
> If you programmatically configure the router and add routes without using
> `Application::route()`, you may run into issues with the order in which piped
> middleware (middleware added to the application via the `pipe()` method) is
> executed.
>
> To ensure that everything executes in the correct order, you can call
> `Application::pipeRouteMiddleware()` at any time to pipe it to the
> application. As an example, after you have created your application
> instance:
>
> ```php
> $app->pipe($middlewareToExecuteFirst);
> $app->pipeRouteMiddleware();
> $app->pipe($errorMiddleware);
> ```
>
> If you fail to add any routes via `Application::route()` or to call
> `Application::pipeRouteMiddleware()`, the routing middleware will be called
> when executing the application. **This means that it will be last in the
> middleware pipeline,** which means that if you registered any error
> middleware, it can never be invoked.

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

- A factory to register as and generate an `Aura\Router\Router` instance.
- A factory registered as `Zend\Expressive\Router\RouterInterface`, which
  creates and returns a `Zend\Expressive\Router\Aura` instance composing the
  `Aura\Router\Router` instance.

Sound difficult? It's not; we've essentially done it above already!

```php
<?php
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

If you are using `Zend\ServiceManager`, this might look like the following:

```php
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

// alternately, via service_manager configuration:
return [
    'service_manager' => [
        'factories' => [
            'Aura\Router\Router' => 'Application\Container\AuraRouterFactory',
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
use Application\Container\AuraRouterFactory;
use Application\Container\RouterFactory;
use Interop\Container\Pimple\PimpleInterop;

$container = new PimpleInterop();
$container['Aura\Router\Router'] = new AuraRouterFactory();
$container['Zend\Expressive\Router\RouterInterface'] = new RouterFactory();
```
