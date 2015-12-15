# How can I setup the locale depending on a routing parameter?

It is a common task to have an localized web application where the setting of
the locale (and therefor the language) depends on the routing. There are other 
ways of setting a language like using the session or a specialized sub-domain.
In this recipe we will concentrate on using the a routing parameter.

## Setting up the route ##

If you want to set the locale depending on an routing parameter, you first have
to add a language parameter to your routes. In this example we use the `lang` 
parameter which should consist of two small letters,

```php
return [
    'dependencies' => [
        'invokables' => [
            Zend\Expressive\Router\RouterInterface::class =>
                Zend\Expressive\Router\ZendRouter::class,
        ],
        'factories' => [
            Application\Action\HomePageAction::class =>
                Application\Action\HomePageFactory::class,
            Application\Action\ContactPageAction::class =>
                Application\Action\ContactPageFactory::class,
        ],
    ],
    'routes' => [
        [
            'name' => 'home',
            'path' => '/:lang',
            'middleware' => Application\Action\HomePageAction::class,
            'allowed_methods' => ['GET'],
            'options'         => [
                'constraints' => [
                    'lang' => '[a-z]{2}',
                ],
            ],
        ],
        [
            'name' => 'contact',
            'path' => '/:lang/contact',
            'middleware' => Application\Action\ContactPageAction::class,
            'allowed_methods' => ['GET'],
            'options'         => [
                'constraints' => [
                    'lang' => '[a-z]{2}',
                ],
            ],
        ],
    ],
];
```

## Create a route result observer class for localization ##

To make sure that you can setup the locale after the routing has been processed
you need to implement a localization observer based which implements the 
`RouteResultObserverInterface`. All classes that implement this interface and
that are attached to the `Zend\Expressive` application instance get called 
whenever the `RouteResult` has changed.

Such a `LocalizationObserver` class could look similar to this.

```php
namespace Application\I18n;

use Locale;
use Zend\Expressive\Router\RouteResult;
use Zend\Expressive\Router\RouteResultObserverInterface;

class LocalizationObserver implements RouteResultObserverInterface
{
    public function update(RouteResult $result)
    {
        if ($result->isFailure()) {
            return;
        }

        $matchedParams = $result->getMatchedParams();

        if (isset($matchedParams['lang'])) {
            Locale::setDefault($matchedParams['lang']);
        } else {
            Locale::setDefault('de_DE');
        }
    }
}
```

Afterwards you need to configure the `LocalizationObserver` in your 
`/config/autoload/dependencies.global.php` file: 

```php
return [
    'dependencies' => [
        'invokables' => [
            /* ... */
            
            Application\I18n\LocalizationObserver::class =>
                Application\I18n\LocalizationObserver::class,
        ],

        /* ... */
    ]
];
```

## Attach the localization observer to the application ##

There are five approaches you can take to attach the `LocalizationObserver` to 
the application instance, each with pros and cons:

### Bootstrap script ###

Modify the bootstrap script `/public/index.php` to attach the observer:

```php
use Application\I18n\LocalizationObserver;

/* ... */

$app = $container->get('Zend\Expressive\Application');
$app->attachRouteResultObserver(
    $container->get(LocalizationObserver::class)
);
$app->run();
```

This is likely the simplest way, but means that there may be a growing 
amount of code in that file.

### Observer factory ###

Alternately, in the factory for your observer, have it self-attach to the 
application instance:

```php
// get instance of observer...

// and now check for the Application:
if ($container->has(Application::class)) {
    $container->get(Application::class)->attachRouteResultObserver($observer);
}

return $observer;
```

There are two things to be careful of with this approach:

- Circular dependencies. If a a dependency of the Application is dependent on 
  your observer, you'll run into this.
- Late registration. If this is injected as a dependency for another class after 
  routing has happened, then your observer will never be triggered.

If you can prevent circular dependencies, and ensure that the factory is invoked 
early enough, then this is a great, portable way to accomplish it.

### Delegator factory ###

If you're using zend-servicemanager, you can use a delegator factory on the 
Application service to pull and register the observer:

```php
use Zend\Expressive\Application;
use Zend\ServiceManager\DelegatorFactoryInterface;
use Zend\ServiceManager\ServiceLocatorInterface;

class ApplicationObserverDelegatorFactory implements DelegatorFactoryInterface
{
    public function createDelegatorForName(
        ServiceLocatorInterface $container,
        $name,
        $requestedName,
        $callback
    ) {
        $application = $callback();

        if (! $container->has(LocalizationObserver::class)) {
            return $application;
        }

        $application->attachRouteResultObserver(
            $container->get(LocalizationObserver::class)
        );
        return $application;
    }
}
```

Then register it as a delegator factory in config/autoload/dependencies.global.php:

```php
return [
    'dependencies' => [
        'delegator_factories' => [
            Zend\Expressive\Application::class => [
                ApplicationObserverDelegatorFactory::class,
            ],
        ],
        /* ... */
    ],
];
```

This approach removes the probability of a circular dependency, and ensures 
that the observer is attached as early as possible.

The problem with this approach, though, is portability. You can do something 
similar to this with Pimple:

```php
$pimple->extend(Application::class, function ($app, $container) {
    $app->attachRouteResultObserver($container->get(LocalizationObserver::class));
    return $app;
});
```

and there are ways to accomplish it in Aura.Di as well — but they're all 
different, making the approach non-portable.

### Extend the Application factory ###

Alternately, extend the Application factory:

```php
class MyApplicationFactory extends ApplicationFactory
{
    public function __invoke($container)
    {
        $app = parent::__invoke($container);
        $app->attachRouteResultObserver($container->get(LocalizationObserver::class));
        return $app;
    }
}
```

Then alter the line in config/autoload/dependencies.global.php that registers 
the Application factory to point at your own factory.

This approach will work across all container types, and is essentially a 
portable way of doing delegator factories.

### Use middleware ###

Alternately, use `pre_routing` middleware to accomplish the task; the middleware 
will get both the observer and application as dependencies, and simply register 
the observer with the application:

```php
use Zend\Expressive\Router\RouteResultSubjectInterface;

class LocalizationObserverMiddleware
{
    private $application;
    private $observer;

    public function __construct(LocalizationObserver $observer, RouteResultSubjectInterface $application)
    {
        $this->observer = $observer;
        $this->application = $application;
    }

    public function __invoke($request, $response, callable $next)
    {
        $this->application->attachRouteResultObserver($this->observer);
        return $next($request, $response);
    }
}
```

The factory would inject the observer and application instances; I'm sure you 
can figure that part out on your own.

In your `config/autoload/middleware-pipeline.global.php`, you'd do the following:

```php
return [
    'dependencies' => [
        'factories' => [
            LocalizationObserverMiddleware::class => LocalizationObserverMiddlewareFactory::class,
            /* ... */
        ],
        /* ... */
    ],
    'middleware_pipeline' => [
        'pre_routing' => [
            [ 'middleware' => LocalizationObserverMiddleware::class ],
            /* ... */
        ],
        'post_routing' => [
            /* ... */
        ],
    ],
];
```

This approach is also portable, but, as you can see, requires more setup (a 
middleware class + factory + factory registration + middleware registration). 
On the flip side, it's portable between applications, which could be something 
to consider if you were to make the functionality into a discrete package.

(This is the approach we took for the ServerUrl and Url helpers.)
