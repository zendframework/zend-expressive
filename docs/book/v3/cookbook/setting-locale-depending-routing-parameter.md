# How can I setup the locale depending on a routing parameter?

Localized web applications often set the locale (and therefor the language)
based on a routing parameter, the session, or a specialized sub-domain.
In this recipe we will concentrate on using a routing parameter.

> ### Routing parameters
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

In the following examples, we use the `locale` parameter, which should consist
of two lowercase alphabetical characters.

### Dependency configuration

The examples assume the following middleware dependency configuration:

```php
use App\Handler;

return [
    'dependencies' => [
        'factories' => [
            Handler\HomePageHandler::class    => Handler\HomePageHandlerFactory::class,
            Handler\ContactPageHandler::class => Handler\ContactPageFactory::class,
        ],
    ],
];
```

### Programmatic routes

The following describes routing configuration for use when using a
programmatic application.

```php
use App\Handler\ContactPageHandler;
use App\Handler\HomePageHandler;

$localeOptions = ['locale' => '[a-z]{2,3}([-_][a-zA-Z]{2}|)'];

$app->get('/:locale', HomePageHandler::class, 'home')
    ->setOptions($localeOptions);
$app->get('/:locale/contact', ContactPageHandler::class, 'contact')
    ->setOptions($localeOptions);
```

> ### Note: Routing may differ based on router
>
> The routing examples in this recipe use syntax for the zend-mvc router, and,
> as such, may not work in your application.
>
> For Aura.Router, the 'home' route as listed above would read:
>
> ```php
> $app->get('/{locale}', HomePageHandler::class, 'home')
>     ->setOptions([
>         'tokens' => [
>             'locale' => '[a-z]{2,3}([-_][a-zA-Z]{2}|)',
>         ],
>     ]);
> ```
>
> For FastRoute:
>
> ```php
> $app->get(
>     '/{locale:[a-z]{2,3}([-_][a-zA-Z]{2}|)}',
>     HomePageHandler::class,
>     'home'
> );
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
namespace App\I18n;

use Locale;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

class LocalizationMiddleware implements MiddlewareInterface
{
    public const LOCALIZATION_ATTRIBUTE = 'locale';

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler) : ResponseInterface
    {
        // Get locale from route, fallback to the user's browser preference
        $locale = $request->getAttribute(
            'locale',
            Locale::acceptFromHttp(
                $request->getServerParams()['HTTP_ACCEPT_LANGUAGE'] ?? 'en_US'
            )
        );

        // Store the locale as a request attribute
        return $handler->handle($request->withAttribute(self::LOCALIZATION_ATTRIBUTE, $locale));
    }
}
```

> ### Locale::setDefault is unsafe
>
> Do not use `Locale::setDefault($locale)` to set a global static locale.
> PSR-15 apps may run in async processes, which could lead to another process
> overwriting the value, and thus lead to unexpected results for your users.
>
> Use a request parameter as detailed above instead, as the request is created
> specific to each process.

Register this new middleware in either `config/autoload/middleware-pipeline.global.php`
or `config/autoload/dependencies.global.php`:

```php
return [
    'dependencies' => [
        'invokables' => [
            LocalizationMiddleware::class => LocalizationMiddleware::class,
            /* ... */
        ],
        /* ... */
    ],
];
```

Pipe it immediately after your routing middleware:

```php
use App\I18n\LocalizationMiddleware;

/* ... */
$app->pipe(RouteMiddleware::class);
$app->pipe(LocalizationMiddleware::class);
/* ... */
```
