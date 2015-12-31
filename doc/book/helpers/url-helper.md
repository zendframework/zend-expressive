# UrlHelper

`Zend\Expressive\Helper\UrlHelper` provides the ability to generate a URI path
based on a given route defined in the `Zend\Expressive\Router\RouterInterface`.
If registered as a route result observer, and the route being used was also
the one matched during routing, you can provide a subset of routing
parameters, and any not provided will be pulled from those matched.

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
function ($routeName, array $params = []) : string
```

Where:

- `$routeName` is the name of a route defined in the composed router. You may
  omit this argument if you want to generate the path for the currently matched
  request.
- `$params` is an array of substitutions to use for the provided route, with the
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

Each method will raise an exception if:

- No `$routeName` is provided, and no `RouteResult` is composed.
- No `$routeName` is provided, a `RouteResult` is composed, but that result
  represents a matching failure.
- The given `$routeName` is not defined in the router.

## Creating an instance

In order to use the helper, you will need to instantiate it with the current
`RouterInterface`. The factory `Zend\Expressive\Helper\UrlHelperFactory` has
been provided for this purpose, and can be used trivially with most
dependency injection containers implementing container-interop. Additionally,
it is most useful when injected with the current results of routing, and as
such should be registered as a route result observer with the application. The
following steps should be followed to register and configure the helper:

- Register the `UrlHelper` as a service in your container, using the provided
  factory.
- Register the `UrlHelperMiddleware` as a service in your container, using the
  provided factory.
- Register the `UrlHelperMiddleware` as pre_routing pipeline middleware.

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

To register the `UrlHelperMiddleware` as pre-routing pipeline middleware:

```php
use Zend\Expressive\Helper\UrlHelperMiddleware;

// Do this early, before piping other middleware or routes:
$app->pipe(UrlHelperMiddleware::class);

// Or use configuration:
// [
//     'middleware_pipeline' => [
//         'pre_routing' => [
//             ['middleware' => UrlHelperMiddleware::class],
//         ],
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
        'pre_routing' => [
            ['middleware' => UrlHelperMiddleware::class],
        ],
    ],
]
```

> #### Skeleton configures helpers
>
> If you started your project using the Expressive skeleton package, the
> `UrlHelper` and `UrlHelperMiddleware` factories are already registered for
> you, as is the `UrlHelperMiddleware` pre_routing pipeline middleware.

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
