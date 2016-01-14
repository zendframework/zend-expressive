# ServerUrlHelper

`Zend\Expressive\Helper\ServerUrlHelper` provides the ability to generate a full
URI by passing only the path to the helper; it will then use that path with the
current `Psr\Http\Message\UriInterface` instance provided to it in order to
generate a fully qualified URI.

## Usage

When you have an instance, use either its `generate()` method, or call the
instance as an invokable:

```php
// Using the generate() method:
$url = $helper->generate('/foo');

// is equivalent to invocation:
$url = $helper('/foo');
```

The helper is particularly useful when used in conjunction with the
[UrlHelper](url-helper.md), as you can then create fully qualified URIs for use
with headers, API hypermedia links, etc.:

```php
$url = $serverUrl($url('resource', ['id' => 'sha1']));
```

The signature for the ServerUrlHelper `generate()` and `__invoke()` methods is:

```php
function ($path = null) : string
```

Where:

- `$path`, when provided, can be a string path to use to generate a URI.

## Creating an instance

In order to use the helper, you will need to inject it with the current
`UriInterface` from the request instance. To automate this, we provide
`Zend\Expressive\Helper\ServerUrlMiddleware`, which composes a `ServerUrl`
instance, and, when invoked, injects it with the URI instance.

As such, you will need to:

- Register the `ServerUrlHelper` as a service in your container.
- Register the `ServerUrlMiddleware` as a service in your container.
- Register the `ServerUrlMiddleware` as pre_routing pipeline middleware.

The following examples demonstrate registering the services.

```php
use Zend\Expressive\Helper\ServerUrlHelper;
use Zend\Expressive\Helper\ServerUrlMiddleware;
use Zend\Expressive\Helper\ServerUrlMiddlewareFactory;

// zend-servicemanager:
$services->setInvokableClass(ServerUrlHelper::class, ServerUrlHelper::class);
$services->setFactory(ServerUrlMiddleware::class, ServerUrlMiddlewareFactory::class);

// Pimple:
$pimple[ServerUrlHelper::class] = function ($container) {
    return new ServerUrlHelper();
};
$pimple[ServerUrlMiddleware::class] = function ($container) {
    $factory = new ServerUrlMiddlewareFactory();
    return $factory($container);
};

// Aura.Di:
$container->set(ServerUrlHelper::class, $container->lazyNew(ServerUrlHelper::class));
$container->set(ServerUrlMiddlewareFactory::class, $container->lazyNew(ServerUrlMiddlewareFactory::class));
$container->set(
    ServerUrlMiddleware::class,
    $container->lazyGetCall(ServerUrlMiddlewareFactory::class, '__invoke', $container)
);
```

To register the `ServerUrlMiddleware` as pre-routing pipeline middleware:

```php
use Zend\Expressive\Helper\ServerUrlMiddleware;

// Do this early, before piping other middleware or routes:
$app->pipe(ServerUrlMiddleware::class);

// Or use configuration:
// [
//     'middleware_pipeline' => [
//         'pre_routing' => [
//             ['middleware' => ServerUrlMiddleware::class],
//         ],
//     ],
// ]
```

The following dependency configuration will work for all three when using the
Expressive skeleton:

```php
return [
    'dependencies' => [
        'invokables' => [
            ServerUrlHelper::class => ServerUrlHelper::class,
        ],
        'factories' => [
            ServerUrlMiddleware::class => ServerUrlMiddlewareFactory::class,
        ],
    ],
    'middleware_pipeline' => [
        'pre_routing' => [
            ['middleware' => ServerUrlMiddleware::class],
        ],
    ],
]
```

> ### Skeleton configures helpers
>
> If you started your project using the Expressive skeleton package, the
> `ServerUrlHelper` and `ServerUrlMiddleware` factories are already registered
> for you, as is the `ServerUrlMiddleware` pre_routing pipeline middleware.

## Using the helper in middleware

Compose the helper in your middleware (or elsewhere), and then use it to
generate URI paths:

```php
use Zend\Expressive\Helper\ServerUrlHelper;

class FooMiddleware
{
    private $helper;

    public function __construct(ServerUrlHelper $helper)
    {
        $this->helper = $helper;
    }

    public function __invoke($request, $response, callable $next)
    {
        $response = $response->withHeader(
            'Link',
            $this->helper->generate() . '; rel="self"'
        );
        return $next($request, $response);
    }
}
```
