# UrlHelper

`Zend\Expressive\Helper\UrlHelper` provides the ability to generate a URI path
based on a given route defined in the `Zend\Expressive\Router\RouterInterface`.
If injected with a route result, and the route being used was also the one
matched during routing, you can provide a subset of routing parameters, and any
not provided will be pulled from those matched.

## Usage

When you have an instance, use either its `generate()` method, or call the
instance as an invokable:

```php
// Using the generate() method:
$url = $helper->generate('resource', ['id' => 'sha1']);

// is equivalent to invocation:
$url = $helper('resource', ['id' => 'sha1']);
```

The signature for both is:

```php
function (
    $routeName,
    array $routeParams = [],
    $queryParams = [],
    $fragmentIdentifier = null,
    array $options = []
) : string
```

Where:

- `$routeName` is the name of a route defined in the composed router. You may
  omit this argument if you want to generate the path for the currently matched
  request.
- `$routeParams` is an array of substitutions to use for the provided route, with the
  following behavior:
    - If a `RouteResult` is composed in the helper, and the `$routeName` matches
      it, the provided `$params` will be merged with any matched parameters, with
      those provided taking precedence.
    - If a `RouteResult` is not composed, or if the composed result does not match
      the provided `$routeName`, then only the `$params` provided will be used
      for substitutions.
    - If no `$params` are provided, and the `$routeName` matches the currently
      matched route, then any matched parameters found will be used.
      parameters found will be used.
    - If no `$params` are provided, and the `$routeName` does not match the
      currently matched route, or if no route result is present, then no
      substitutions will be made.
- `$queryParams` is an array of query string arguments to include in the
  generated URI.
- `$fragmentIdentifier` is a string to use as the URI fragment.
- `$options` is an array of options to provide to the router for purposes of
  controlling URI generation. As an example, zend-router can consume "translator"
  and "text_domain" options in order to provide translated URIs.

Each method will raise an exception if:

- No `$routeName` is provided, and no `RouteResult` is composed.
- No `$routeName` is provided, a `RouteResult` is composed, but that result
  represents a matching failure.
- The given `$routeName` is not defined in the router.

> ### Signature changes
>
> The signature listed above is current as of version 3.0.0 of
> zendframework/zend-expressive-helpers. Prior to that version, the helper only
> accepted the route name and route parameters.

## Creating an instance

In order to use the helper, you will need to instantiate it with the current
`RouterInterface`. The factory `Zend\Expressive\Helper\UrlHelperFactory` has
been provided for this purpose, and can be used trivially with most
dependency injection containers implementing 
[PSR-11 Container](https://github.com/php-fig/container). Additionally,
it is most useful when injected with the current results of routing, which
requires registering middleware with the application that can inject the route
result. The following steps should be followed to register and configure the helper:

- Register the `UrlHelper` as a service in your container, using the provided
  factory.
- Register the `UrlHelperMiddleware` as a service in your container, using the
  provided factory.
- Register the `UrlHelperMiddleware` as pipeline middleware, immediately
  following the routing middleware.

### Registering the helper service

The following examples demonstrate programmatic registration of the `UrlHelper`
service in your selected dependency injection container.

```php
use Zend\Expressive\Helper\UrlHelper;
use Zend\Expressive\Helper\UrlHelperFactory;

// zend-servicemanager:
$services->setFactory(UrlHelper::class, UrlHelperFactory::class);

// Pimple:
$pimple[UrlHelper::class] = function ($container) {
    $factory = new UrlHelperFactory();
    return $factory($container);
};

// Aura.Di:
$container->set(UrlHelperFactory::class, $container->lazyNew(UrlHelperFactory::class));
$container->set(
    UrlHelper::class,
    $container->lazyGetCall(UrlHelperFactory::class, '__invoke', $container)
);
```

The following dependency configuration will work for all three when using the
Expressive skeleton:

```php
return ['dependencies' => [
    'factories' => [
        UrlHelper::class => UrlHelperFactory::class,
    ],
]]
```

> #### UrlHelperFactory requires RouterInterface
>
> The factory requires that a service named `Zend\Expressive\Router\RouterInterface` is present,
> and will raise an exception if the service is not found.

### Registering the pipeline middleware

To register the `UrlHelperMiddleware` as pipeline middleware following the
routing middleware:

```php
use Zend\Expressive\Helper\UrlHelperMiddleware;

// Programmatically:
$app->pipeRoutingMiddleware();
$app->pipe(UrlHelperMiddleware::class);
$app->pipeDispatchMiddleware();

// Or use configuration:
// [
//     'middleware_pipeline' => [
//         /* ... */
//         Zend\Expressive\Application::ROUTING_MIDDLEWARE,
//         ['middleware' => UrlHelperMiddleware::class],
//         Zend\Expressive\Application::DISPATCH_MIDDLEWARE,
//         /* ... */
//     ],
// ]
//
// Alternately, create a nested middleware pipeline for the routing, UrlHelper,
// and dispatch middleware:
// [
//     'middleware_pipeline' => [
//         /* ... */
//         'routing' => [
//             'middleware' => [
//                 Zend\Expressive\Application::ROUTING_MIDDLEWARE,
//                 UrlHelperMiddleware::class
//                 Zend\Expressive\Application::DISPATCH_MIDDLEWARE,
//             ],
//             'priority' => 1,
//         ],
//         /* ... */
//     ],
// ]

```

The following dependency configuration will work for all three when using the
Expressive skeleton:

```php
return [
    'dependencies' => [
        'factories' => [
            UrlHelper::class => UrlHelperFactory::class,
            UrlHelperMiddleware::class => UrlHelperMiddlewareFactory::class,
        ],
    ],
    'middleware_pipeline' => [
        Zend\Expressive\Application::ROUTING_MIDDLEWARE,
        ['middleware' => UrlHelperMiddleware::class],
        Zend\Expressive\Application::DISPATCH_MIDDLEWARE,
    ],
];

// OR:
return [
    'dependencies' => [
        'factories' => [
            UrlHelper::class => UrlHelperFactory::class,
            UrlHelperMiddleware::class => UrlHelperMiddlewareFactory::class,
        ],
    ],
    'middleware_pipeline' => [
        'routing' => [
            'middleware' => [
                Zend\Expressive\Application::ROUTING_MIDDLEWARE,
                UrlHelperMiddleware::class,
                Zend\Expressive\Application::DISPATCH_MIDDLEWARE,
            ],
            'priority' => 1,
        ],
    ],
];
```

> #### Skeleton configures helpers
>
> If you started your project using the Expressive skeleton package, the
> `UrlHelper` and `UrlHelperMiddleware` factories are already registered for
> you, as is the `UrlHelperMiddleware` pipeline middleware.

## Using the helper in middleware

Compose the helper in your middleware (or elsewhere), and then use it to
generate URI paths:

```php
use Zend\Expressive\Helper\UrlHelper;

class FooMiddleware
{
    private $helper;

    public function __construct(UrlHelper $helper)
    {
        $this->helper = $helper;
    }

    public function __invoke($request, $response, callable $next)
    {
        $response = $response->withHeader(
            'Link',
            $this->helper->generate('resource', ['id' => 'sha1'])
        );
        return $next($request, $response);
    }
}
```

## Base Path support

If your application is running under a subdirectory, or if you are running
pipeline middleware that is intercepting on a subpath, the paths generated
by the router may not reflect the *base path*, and thus be invalid. To
accommodate this, the `UrlHelper` supports injection of the base path; when
present, it will be prepended to the path generated by the router.

As an example, perhaps you have middleware running to intercept a language
prefix in the URL; this middleware could then inject the `UrlHelper` with the
detected language, before stripping it off the request URI instance to pass on
to the router:

```php
use Interop\Http\ServerMiddleware\DelegateInterface;
use Interop\Http\ServerMiddleware\MiddlewareInterface;
use Locale;
use Psr\Http\Message\ServerRequestInterface;
use Zend\Expressive\Helper\UrlHelper;

class LocaleMiddleware implements MiddlewareInterface
{
    private $helper;

    public function __construct(UrlHelper $helper)
    {
        $this->helper = $helper;
    }

    public function process(ServerRequestInterface $request, DelegateInterface $delegate)
    {
        $uri = $request->getUri();
        $path = $uri->getPath();
        if (! preg_match('#^/(?P<locale>[a-z]{2,3}([-_][a-zA-Z]{2}|))/#', $path, $matches)) {
            return $delegate->process($request);
        }

        $locale = $matches['locale'];
        Locale::setDefault(Locale::canonicalize($locale));
        $this->helper->setBasePath($locale);

        return $delegate->process($request->withUri(
            $uri->withPath(substr($path, (strlen($locale) + 1)))
        ));
    }
}
```

(Note: if the base path injected is not prefixed with `/`, the helper will add
the slash.)

Paths generated by the `UriHelper` from this point forward will have the
detected language prefix.
