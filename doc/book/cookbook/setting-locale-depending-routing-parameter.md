# How can I setup the locale depending on a routing parameter?

Localized web applications often set the locale (and therefor the language)
based on a routing parameter, the session, or a specialized sub-domain.
In this recipe we will concentrate on using a routing parameter.

> ## Routing parameters
>
> Using the approach in this chapter requires that you add a `/:locale` (or
> similar) segment to each and every route that can be localized, and, depending
> on the router used, may also require additional options for specifying
> constraints. If the majority of your routes are localized, this will become
> tedious quickly. In such a case, you may want to look at the related recipe
> on [setting the locale without routing parameters](setting-locale-without-routing-parameter.md).

## Setting up the route

If you want to set the locale depending on an routing parameter, you first have
to add a locale parameter to each route that requires localization.

In this example we use the `locale` parameter, which should consist of two
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
            'path' => '/:locale',
            'middleware' => Application\Action\HomePageAction::class,
            'allowed_methods' => ['GET'],
            'options'         => [
                'constraints' => [
                    'locale' => '[a-z]{2,3}([-_][a-zA-Z]{2}|)',
                ],
            ],
        ],
        [
            'name' => 'contact',
            'path' => '/:locale/contact',
            'middleware' => Application\Action\ContactPageAction::class,
            'allowed_methods' => ['GET'],
            'options'         => [
                'constraints' => [
                    'locale' => '[a-z]{2,3}([-_][a-zA-Z]{2}|)',
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
>     'path' => '/{locale}',
>     'middleware' => Application\Action\HomePageAction::class,
>     'allowed_methods' => ['GET'],
>     'options'         => [
>         'constraints' => [
>             'tokens' => [
>                 'locale' => '[a-z]{2,3}([-_][a-zA-Z]{2}|)',
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
>     'path' => '/{locale:[a-z]{2,3}([-_][a-zA-Z]{2}|)}',
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

Such a `LocalizationMiddleware` class could look similar to this:

```php
<?php

namespace Application\I18n;

use Locale;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

class LocalizationMiddleware
{
    const LOCALIZATION_ATTRIBUTE = 'locale';

    public function __invoke(ServerRequestInterface $request, ResponseInterface $response, callable $next = null)
    {
        // Get locale from route, fallback to the user's browser preference
        $locale = $request->getAttribute(
            'locale',
            Locale::acceptFromHttp(
                isset($request->getServerParams()['HTTP_ACCEPT_LANGUAGE']) ? $request->getServerParams()['HTTP_ACCEPT_LANGUAGE'] : 'en_US'
            )
        );

        // Store the locale as a request attribute
        return $next($request->withAttribute(self::LOCALIZATION_ATTRIBUTE, $locale), $response);
    }
}
```

> ### Locale::setDefault is unsafe
>
> Do not use `Locale::setDefault($locale)` to set a global static locale.
> PSR-7 apps may run in async processes, which could lead to another process
> overwriting the value, and thus lead to unexpected results for your users.
>
> Use a request parameter as detailed above instead, as the request is created
> specific to each process.

In your `config/autoload/middleware-pipeline.global.php`, you'd register the
dependency, and inject the middleware into the pipeline following the routing
middleware:

```php
return [
    'dependencies' => [
        'invokables' => [
            LocalizationMiddleware::class => LocalizationMiddleware::class,
            /* ... */
        ],
        /* ... */
    ],
    'middleware_pipeline' => [
        /* ... */
        [
            'middleware' => [
                Zend\Expressive\Container\ApplicationFactory::ROUTING_MIDDLEWARE,
                Helper\UrlHelperMiddleware::class,
                LocalizationMiddleware::class,
                Zend\Expressive\Container\ApplicationFactory::DISPATCH_MIDDLEWARE,
            ],
            'priority' => 1,
        ],
        /* ... */
    ],
];
```
