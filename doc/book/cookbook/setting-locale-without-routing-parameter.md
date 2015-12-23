# How can I setup the locale without an additional parameter?

It is a common task to have an localized web application where the setting of
the locale (and therefor the language) depends on the routing. In this recipe 
we will automatically add the language to the route without changing all of 
the existing routes.

## Setup a middleware to extract the language from the URI ##

First, we need to setup a middleware that extracts the language param directly
from the request URI. If if doesn't find any it sets a default. If it does
it uses the language to setup the locale. It also amends the request to add
the language as an attribute. 

```php
namespace Application\I18n;

class SetLanguageMiddleware
{
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
        
        $path = substr($path, 3);
        $request = $request
            ->withUri($uri->withPath($path))
            ->withAttribute('lang', $lang);
            
        return $next($request, $response);
    }
}
```

Afterwards you need to configure the `SetLanguageMiddleware` in your 
`/config/autoload/middleware-pipeline.global.php` file so that it is executed 
on every request.

```php
return [
    'dependencies' => [
        'invokables' => [
            /* ... */
            
            Application\I18n\SetLanguageMiddleware::class =>
                Application\I18n\SetLanguageMiddleware::class,
        ],

        /* ... */
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

The primary problem with it will be URI generation, as the router will be 
generating URIs without the language prefix, which will require a custom URI 
helper. However, this could even be something as simple as:

```php
namespace Application\View\Helper;

use Locale;
use Zend\Expressive\ZendView\UrlHelper;

class LocalizedUrlHelper extends UrlHelper
{
    public function __invoke($route = null, $params = [])
    {
        return sprintf(
            '/%s%s', 
            Locale::getDefault(), 
            parent::__invoke($route, $params)
        );
    }
}
```

You will also need a factory for the new url helper:

```php
namespace Application\View\Helper;

use Interop\Container\ContainerInterface;

class LocalizedUrlHelperFactory extends UrlHelper
{
    public function __invoke(ContainerInterface $container)
    {
        return new LocalizedUrlHelper($container->get(RouterInterface::class));
    }
}
```

You can easily configure the extended url helper by changing its configuration 
within the `/config/autoload/dependencies.global.php` file.

```php
return [
    'dependencies' => [
        'invokables' => [
            /* ... */
            
            Zend\Expressive\Helper\UrlHelper::class =>
                Application\View\Helper\LocalizedUrlHelperFactory::class,
        ],

        /* ... */
    ]
];
```

## Redirecting within your middleware ##

If you want to add the language parameter when creating URIs within your 
action middleware you just need to do the following:


```php
public function __invoke(
    ServerRequestInterface $request,
    ResponseInterface $response,
    callable $next = null
) {
    /* ... */
    
    $routeParams = [
        'id'   => $id,
        'lang' => $request->getAttribute('lang'),
    ];
    
    return new RedirectResponse(
        $this->router->generateUri('article.show', $routeParams)
    );
}
```

