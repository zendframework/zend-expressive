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

## Setup a middleware to extract the locale from the URI

First, we need to setup middleware that extracts the locale param directly
from the request URI's path. If if doesn't find one, it sets a default.

If it does find one, it uses the value to setup the locale. It also:

- amends the request with a truncated path (removing the locale segment).
- adds the locale segment as the base path of the `UrlHelper`.

```php
namespace Application\I18n;

use Locale;
use Zend\Expressive\Helper\UrlHelper;

class SetLocaleMiddleware
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
        
        if (! preg_match('#^/(?P<locale>[a-z]{2})/#', $path, $matches)) {
            Locale::setDefault('de_DE');
            return $next($request, $response);
        }
        
        $locale = $matches['locale'];
        Locale::setDefault($locale);
        $this->helper->setBasePath($locale);
        
        return $next(
            $request->withUri(
                $uri->withPath(substr($path, 3))
            ),
            $response
        );
    }
}
```

Then you will need a factory for the `SetLocaleMiddleware` to inject the
`UrlHelper` instance.

```php
namespace Application\I18n;

use Interop\Container\ContainerInterface;
use Zend\Expressive\Helper\UrlHelper;

class SetLocaleMiddlewareFactory
{
    public function __invoke(ContainerInterface $container)
    {
        return new SetLocaleMiddleware(
            $container->get(UrlHelper::class)
        );
    }
}
```

Afterwards, you need to configure the `SetLocaleMiddleware` in your 
`/config/autoload/middleware-pipeline.global.php` file so that it is executed 
on every request.

```php
return [
    'dependencies' => [
        /* ... */
        'factories' => [
            Application\I18n\SetLocaleMiddleware::class =>
                Application\I18n\SetLocaleMiddlewareFactory::class,
            /* ... */
        ],
    ]

    'middleware_pipeline' => [
        [
            'middleware' => [
                Application\I18n\SetLocaleMiddleware::class,
                /* ... */
            ],
            'priority' => 1000,
        ],

        /* ... */

        'routing' => [
            'middleware' => [
                Zend\Expressive\Container\ApplicationFactory::ROUTING_MIDDLEWARE,
                Zend\Expressive\Helper\UrlHelperMiddleware::class,
                Zend\Expressive\Container\ApplicationFactory::DISPATCH_MIDDLEWARE,
            ],
            'priority' => 1,
        ],

        /* ... */
    ],
];
```

## Url generation in the view

Since the `UrlHelper` has the locale set as a base path, you don't need 
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

If you want to add the locale parameter when creating URIs within your 
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
