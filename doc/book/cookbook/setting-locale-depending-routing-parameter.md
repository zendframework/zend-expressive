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


## Create a route result middleware class for localization

To make sure that you can setup the locale after the routing has been processed,
you need to implement localization middleware that acts on the route result, and
registered in the pipeline immediately following the routing middleware.

Such a `LocalizationObserver` class could look similar to this:

```php
namespace Application\I18n;

use Locale;
use Zend\Expressive\Router\RouteResult;

class LocalizationObserver
{
    public function __invoke($request, $response, $next)
    {
        $result = $request->getAttribute(RouteResult::class, false);
        if (! $result) {
            return $next($request, $response);
        }

        $matchedParams = $result->getMatchedParams();

        $lang = isset($matchedParams['lang']) ? $matchedParams['lang'] : 'de_DE';
        Locale::setDefault($matchedParams['lang']);

        return $next($request, $response);
    }
}
```

In your `config/autoload/middleware-pipeline.global.php`, you'd register the
dependency, and inject the middleware into the pipeline following the routing
middleware:

```php
return [
    'dependencies' => [
        'invokables' => [
            LocalizationObserver::class => LocalizationObserver::class,
            /* ... */
        ],
        /* ... */
    ],
    'middleware_pipeline' => [
        /* ... */
        [
            'middleware' => [
                Zend\Expressive\Container\ApplicationFactory::ROUTING_MIDDLEWARE,
                [ 'middleware' => LocalizationObserver::class ],
                Zend\Expressive\Container\ApplicationFactory::DISPATCH_MIDDLEWARE,
            ],
            'priority' => 1,
        ],
        /* ... */
    ],
];
```
