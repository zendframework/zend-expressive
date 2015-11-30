# Route Result Observers

Occasionally, you may have need of the `RouteResult` within other application
code. As a primary example, a URI generator may want this information to allow
creating "self" URIs, or to allow presenting a subset of parameters to generate
a URI.

Consider this URI:

```php
'/api/v{version:\d+}/post/{post_id:\d+}/comment/{comment_id:\d+}'
```

If you wanted to generate URIs to a list of related comments, you may not want
to pass the `$version` and `$post_id` parameters each and every time, but
instead just the `$comment_id`. As such, *route result observers* exist to allow
you to notify such utilities of the results of matching.

## RouteResultObserverInterface

Route result observers must implement the `RouteResultObserverInterface`:

```php
namespace Zend\Expressive\Router;

interface RouteResultObserverInterface
{
    /**
     * Observe a route result.
     *
     * @param RouteResult $result
     */
    public function update(RouteResult $result);
}
```

These can then be attached to the `Application` instance:

```php
$app->attachRouteResultObserver($observer);
```

As noted, the observer receives the `RouteResult` from attempting to match a
route.

You can detach an existing observer as well, by passing its instance to the
`detachRouteResultObserver()` method:

```php
$app->detachRouteResultObserver($observer);
```

## Example

For this example, we'll build a simple URI generator. It will compose a
`RouterInterface` implementation, implement `RouteResultObserverInterface`, and,
when invoked, generate a URI.

```php
use Zend\Expressive\Router\RouterInterface;
use Zend\Expressive\Router\RouteResult;
use Zend\Expressive\Router\RouteResultObserverInterface;

class UriGenerator implements RouteResultObserverInterface
{
    private $defaults = [];

    private $routeName;

    private $router;

    public function __construct(RouterInterface $router)
    {
        $this->router = $router;
    }

    public function update(RouteResult $result)
    {
        if ($result->isFailure()) {
            return;
        }

        $this->routeName = $result->getMatchedRouteName();
        $this->defaults  = $result->getMatchedParams();
    }

    public function __invoke($route = null, array $params = [])
    {
        if (! $route && ! $this->routeName) {
            throw new InvalidArgumentException('Missing route, and no route was matched to use as a default!');
        }

        $route = $route ?: $this->routeName;

        if ($route === $this->routeName) {
            $params = array_merge($this->defaults, $params);
        }

        return $this->router->generateUri($route, $params);
    }
}
```

Now that we've defined the `UriGenerator`, we need:

- a factory for creating it
- a way to attach it to the application

First, the factory, which is essentially a one-liner wrapped in a class:

```php
use Container\Interop\ContainerInterface;
use Zend\Expressive\Router\RouterInterface;

class UriGeneratorFactory
{
    public function __invoke(ContainerInterface $container)
    {
        return new UriGenerator($container->get(RouterInterface::class));
    }
}
```

Attaching the observer to the application can happen in one of two ways:

- Via modification of the bootstrap script.
- Via container-specific "extension" or "delegation" features.

### Modifying the bootstrap script

If you choose this method, you will modify your `public/index.php` script (or
whatever script you've defined as the application gateway.) The following
assumes you're using the `public/index.php` generated for you when using the
Expressive skeleton.

In this case, you would attach any observers between the line where you fetch
the application from the container, and the line when you run it.

```php
$app = $container->get('Zend\Expressive\Application');

// Attach observers
$app->attachRouteResultObserver($container->get(UriGenerator::class));

$app->run();
```

### Container-specific Delegation

Pimple offers a feature called "extension" to allow modification of a service
after creation, and zend-servicemanager provides a [delegator factories](http://framework.zend.com/manual/current/en/modules/zend.service-manager.delegator-factories.html)
feature for a similar purpose.

Both examples below assume you are using the Expressive skeleton to generate
your initial project; if not, read the examples, and adapt them to your own
configuration and container initialization strategy.

To make use of this in Pimple, you would modify the `config/container.php` file
to add the following just prior to returning the container instance:

```php
$container->extend('Zend\Expressive\Application', function ($app, $container) {
    $app->attachRouteResultObserver($container->get(UriGenerator::class));
    return $app;
});
```

For zend-servicemanager, you will do two things:

- Create a delegator factory
- Add the delegator factory to your configuration

The delegator factory will look like this:

```php
use Zend\ServiceManager\DelegatorFactoryInterface;
use Zend\ServiceManager\ServiceLocatorInterface;

class UriGeneratorDelegatorFactory
{
    public function createDelegatorWithName(
        ServiceLocatorInterface $container,
        $name,
        $requestedName,
        $callback
    ) {
        $app = $callback();
        $app->attachRouteResultObserver($container->get(UriGenerator::class));
        return $app;
    }
}
```

From here, you can register the delegator factory in any configuration file
where you're specifying application dependencies; we recommend a
`config/autoload/dependencies.global.php` file for this.

```php
use Zend\Expressive\Application;

return [
    'dependencies' => [
        'factories' => [
            UriGenerator::class => UriGeneratorFactory::class,
        ],
        'delegator_factories' => [
            Application::class => [
                UriGeneratorDelegatorFactory::class,
            ],
        ],
    ],
];
```

Note: You may see code like the above already, for either example, depending on
the selections you made when creating your project!
