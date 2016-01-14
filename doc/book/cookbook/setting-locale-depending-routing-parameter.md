# How can I setup the locale depending on a routing parameter?

Localized web applications often set the locale (and therefor the language)
based on a routing parameter, the session, or a specialized sub-domain.
In this recipe we will concentrate on using a routing parameter.

> ## Routing parameters
>
> Using the approach in this chapter requires that you add a `/:lang` (or
> similar) segment to each and every route that can be localized, and, depending
> on the router used, may also require additional options for specifying
> constraints. If the majority of your routes are localized, this will become
> tedious quickly. In such a case, you may want to look at the related recipe
> on [setting the locale without routing parameters](setting-locale-without-routing-parameter.md).

## Setting up the route

If you want to set the locale depending on an routing parameter, you first have
to add a language parameter to each route that requires localization.

In this example we use the `lang` parameter, which should consist of two
lowercase alphabetical characters:

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
> ### Note: Routing may differ based on router
>
> The routing examples in this recipe use syntax for the zend-mvc router, and,
> as such, may not work in your application.
>
> For Aura.Router, the 'home' route as listed above would read:
>
> ```php
> [
>     'name' => 'home',
>     'path' => '/{lang}',
>     'middleware' => Application\Action\HomePageAction::class,
>     'allowed_methods' => ['GET'],
>     'options'         => [
>         'constraints' => [
>             'tokens' => [
>                 'lang' => '[a-z]{2}',
>             ],
>         ],
>     ],
> ]
> ```
>
> For FastRoute:
>
> ```php
> [
>     'name' => 'home',
>     'path' => '/{lang:[a-z]{2}}',
>     'middleware' => Application\Action\HomePageAction::class,
>     'allowed_methods' => ['GET'],
> ]
> ```
>
> As such, be aware as you read the examples that you might not be able to
> simply cut-and-paste them without modification.


## Create a route result observer class for localization

To make sure that you can setup the locale after the routing has been processed,
you need to implement a localization observer which implements the 
`RouteResultObserverInterface`. All classes that implement this interface and
that are attached to the `Zend\Expressive\Application` instance get called 
whenever the `RouteResult` has changed.

Such a `LocalizationObserver` class could look similar to this:

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

        $lang = isset($matchedParams['lang']) ? $matchedParams['lang'] : 'de_DE';
        Locale::setDefault($matchedParams['lang']);
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

## Attach the localization observer to the application

There are five approaches you can take to attach the `LocalizationObserver` to 
the application instance, each with pros and cons:

### Bootstrap script

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

### Observer factory

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

### Delegator factory

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

Then register it as a delegator factory in `config/autoload/dependencies.global.php`:

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

### Extend the Application factory

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

Then alter the line in `config/autoload/dependencies.global.php` that registers 
the `Application` factory to point at your own factory.

This approach will work across all container types, and is essentially a 
portable way of doing delegator factories.

### Use middleware

Alternately, use the middleware pipeline to accomplish the task. Register the
middleware early in the pipeline (before the routing middleware); the middleware 
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

The factory would inject the observer and application instances; we leave this
as an exercise to the reader.

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
        [ 'middleware' => LocalizationObserverMiddleware::class ],
        /* ... */
        Zend\Expressive\Container\ApplicationFactory::ROUTING_MIDDLEWARE,
        /* ... */
    ],
];
```

This approach is also portable, but, as you can see, requires more setup (a 
middleware class + factory + factory registration + middleware registration). 
On the flip side, it's portable between applications, which could be something 
to consider if you were to make the functionality into a discrete package.
