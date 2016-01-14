# How can I setup the locale without routing parameters?

Localized web applications often set the locale (and therefor the language)
based on a routing parameter, the session, or a specialized sub-domain.
In this recipe we will concentrate on introspecting the URI path via middleware,
which allows you to have a global mechanism for detecting the locale without
requiring any changes to existing routes.

> ## Distinguishing between routes that require localization
>
> If your application has a mixture of routes that require localization, and
> those that do not, the solution in this recipe may lead to multiple URIs
> that resolve to the identical action, which may be undesirable. In such
> cases, you may want to prefix the specific routes that require localization
> with a required routing parameter; this approach is described in the
> ["Setting a locale based on a routing parameter" recipe](setting-locale-depending-routing-parameter.md).

## Setup a middleware to extract the language from the URI

First, we need to setup middleware that extracts the language param directly
from the request URI's path. If if doesn't find one, it sets a default.

If it does find one, it uses the language to setup the locale. It also:

- amends the request with a truncated path (removing the language segment).
- adds the language segment as the base path of the `UrlHelper`.

```php
namespace Application\I18n;

use Locale;
use Zend\Expressive\Helper\UrlHelper;

class SetLanguageMiddleware
{
    private $helper;
    
    public function __construct(UrlHelper $helper)
    {
        $this->helper = $helper;
    }
    
    public function __invoke($request, $response, callable $next)
    {
    
        $uri = $request->getUri();
        
        $path = $uri->getPath();
        
        if (! preg_match('#^/(?P<lang>[a-z]{2})/#', $path, $matches) {
            Locale::setDefault('de_DE');
            return $next($request, $response);
        }
        
        $lang = $matches['lang'];
        Locale::setDefault($lang);
        $this->helper->setBasePath($lang);
        
        return $next(
            $request->withUri(
                $uri->withPath(substr($path, 3))
            ),
            $response
        );
    }
}
```

Then you will need a factory for the `SetLanguageMiddleware` to inject the
`UrlHelper` instance.

```php
namespace Application\I18n;

use Interop\Container\ContainerInterface;
use Zend\Expressive\Helper\UrlHelper;

class SetLanguageMiddlewareFactory
{
    public function __invoke(ContainerInterface $container)
    {
        return new SetLanguageMiddleware(
            $container->get(UrlHelper::class)
        );
    }
}
```

Afterwards, you need to configure the `SetLanguageMiddleware` in your 
`/config/autoload/middleware-pipeline.global.php` file so that it is executed 
on every request.

```php
return [
    'dependencies' => [
        /* ... */
        'factories' => [
            Application\I18n\SetLanguageMiddleware::class =>
                Application\I18n\SetLanguageMiddlewareFactory::class,
            /* ... */
        ],
    ]

    'middleware_pipeline' => [
        'pre_routing' => [
            [
                'middleware' => [
                    Application\I18n\SetLanguageMiddleware::class,
                    /* ... */
                ],
                /* ... */
            ],
        ],

        'post_routing' => [
            /* ... */
        ],
    ],
];
```

## Url generation in the view

Since the `UrlHelper` has the language set as a base path, you don't need 
to worry about generating URLs within your view. Just use the helper to 
generate a URL and it will do the rest.

```php
<?php echo $this->url('your-route') ?>
```

> ### Helpers differ between template renderers
>
> The above example is specific to zend-view; syntax will differ for
> Twig and Plates.

## Redirecting within your middleware

If you want to add the language parameter when creating URIs within your 
action middleware, you just need to inject the `UrlHelper` into your 
middleware and use it for URL generation:

```php
namespace Application\Action;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Zend\Diactoros\Response\RedirectResponse;
use Zend\Expressive\Helper\UrlHelper;

class RedirectAction
{
    private $helper;
        
    public function __construct(UrlHelper $helper)
    {
        $this->helper = $helper;
    }
        
    /**
     * @param ServerRequestInterface $request
     * @param ResponseInterface      $response
     * @param callable|null          $next
     *
     * @return RedirectResponse
     */
    public function __invoke(
        ServerRequestInterface $request,
        ResponseInterface $response,
        callable $next = null
    ) {
        $routeParams = [ /* ... */ ];

        return new RedirectResponse(
            $this->helper->generate('your-route', $routeParams)
        );
    }
}
```

Injecting the `UrlHelper` into your middleware will also require that the
middleware have a factory that manages the injection. As an example, the
following would work for the above middleware:

```php
namespace Application\Action;

use Interop\Container\ContainerInterface;
use Zend\Expressive\Helper\UrlHelper;

class RedirectActionFactory
{
    public function __invoke(ContainerInterface $container)
    {
        return new RedirectAction(
            $container->get(UrlHelper::class)
        );
    }
}
```
