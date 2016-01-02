# How can I setup the locale without an additional parameter?

It is a common task to have a localized web application where the setting of
the locale (and therefore the language) depends on the routing. In this recipe 
we will automatically add the language to the route without changing all of 
the existing routes.

## Setup a middleware to extract the language from the URI ##

First, we need to setup a middleware that extracts the language param directly
from the request URI. If if doesn't find any it sets a default. If it does
it uses the language to setup the locale. It also amends the request to add
the language as an attribute. 

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
        $urlHelper = $container->get(UrlHelper::class);

        return new SetLanguageMiddleware($urlHelper);
    }
}
```


Afterwards you need to configure the `SetLanguageMiddleware` in your 
`/config/autoload/middleware-pipeline.global.php` file so that it is executed 
on every request.

```php
return [
    'dependencies' => [
        'factories' => [
            Application\I18n\SetLanguageMiddleware::class =>
                Application\I18n\SetLanguageMiddlewareFactory::class,
        ],
    ]

    'middleware_pipeline' => [
        'pre_routing' => [
            [
                'middleware' => [
                    Application\I18n\SetLanguageMiddleware::class,
                    
                    /* ... */
                ],
            ],
        ],

        'post_routing' => [
        ],
    ],
];
```

## Url generation in the view ##

Since the `UrlHelper` has the language set as a base path you don't need 
to worry about generating URLs within your view. Just use the helper to 
generate an URL and it will do the rest.

```php
<?php echo $this->url('your-route') ?>
```

## Redirecting within your middleware ##

If you want to add the language parameter when creating URIs within your 
action middleware you just need to inject the `UrlHelper` into your 
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

Of course you will need a factory as well to inject the `UrlHelper` into
the `RedirectAction` middleware:

Then you will need a factory for the `SetLanguageMiddleware` to inject the
`UrlHelper` instance.

```php
namespace Application\Action;

use Interop\Container\ContainerInterface;
use Zend\Expressive\Helper\UrlHelper;

class SetLanguageMiddlewareFactory
{
    public function __invoke(ContainerInterface $container)
    {
        $urlHelper = $container->get(UrlHelper::class);

        return new RedirectAction($urlHelper);
    }
}
```
