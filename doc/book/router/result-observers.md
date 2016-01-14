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

use Zend\Expressive\Router\RouteResult;

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

> ### RouteResultSubjectInterface
>
> `Zend\Expressive\Application` implements `Zend\Expressive\Router\RouteResultSubjectInterface`,
> which defines methods for attaching and detaching route result observers, as
> well as a method for notifying observers. Typically you'll only see the
> `Application` class as an implementation of the interface, but you can always
> create your own implementations as well if desired &mdash; for instance, if
> you are implementing your own middleware runtime using the various interfaces
> Expressive provides.

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
- By updating the factory to register the observer with the application.

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

### Via the observer factory

This approach requires a slight change to the factory to:

- Check for a `Zend\Expressive\Application` service; and, if found,
- Attach the observer to it.

```php
use Container\Interop\ContainerInterface;
use Zend\Expressive\Application;
use Zend\Expressive\Router\RouterInterface;

class UriGeneratorFactory
{
    public function __invoke(ContainerInterface $container)
    {
        $generator = new UriGenerator($container->get(RouterInterface::class));

        if ($container->has(Application::class)) {
            $container
                ->get(Application::class)
                ->attachRouteResultObserver($generator);
        }

        return $generator;
    }
}
```

> Note: Helpers included!
>
> You do not need to create the above URI generator for your code; this
> functionality is already present in the [zendframework/zend-expressive-helpers](https://github.com/zendframework/zend-expressive-helpers)
> package, and, if you started with the Expressive skeleton, may already
> be installed by default!
>
> See the [helpers documentation](../helpers/intro.md) for more information.
