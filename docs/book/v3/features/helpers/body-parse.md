# Body Parsing Middleware

`Zend\Expressive\Helper\BodyParams\BodyParamsMiddleware` provides generic
[PSR-15](https://www.php-fig.org/psr/psr-15/) middleware for parsing the request
body into parameters, and returning a new request instance that composes them.
The subcomponent provides a strategy pattern around matching the request
`Content-Type`, and then parsing it, giving you a flexible approach that can
grow with your accepted content types.

By default, this middleware will detect the following content types:

- `application/x-www-form-urlencoded` (standard web-based forms, without file
  uploads)
- `application/json`, `application/*+json` (JSON payloads)

## Registering the middleware

You can register it programmatically:

```php
$app->pipe(BodyParamsMiddleware::class);
```

Since body parsing does not necessarily need to happen for every request, you
can also choose to incorporate it in route-specific middleware pipelines:

```php
$app->post('/login', [
    BodyParamsMiddleware::class,
    LoginMiddleware::class,
]);
```

Using route-based middleware pipelines has the advantage of ensuring that the
body parsing middleware only executes for routes that require the processing.
While the middleware has some checks to ensure it only triggers for HTTP
methods that accept bodies, those checks are still overhead that you might want
to avoid; the above strategy of using the middleware only with specific routes
can accomplish that.

## Strategies

If you want to intercept and parse other payload types, you can add *strategies*
to the middleware. Strategies implement `Zend\Expressive\Helper\BodyParams\StrategyInterface`:

```php
namespace Zend\Expressive\Helper\BodyParams;

use Psr\Http\Message\ServerRequestInterface;

interface StrategyInterface
{
    /**
     * Match the content type to the strategy criteria.
     */
    public function match(string $contentType) : bool;

    /**
     * Parse the body content and return a new request.
     */
    public function parse(ServerRequestInterface $request) : ServerRequestInterface;
}
```

You then register them with the middleware using the `addStrategy()` method:

```php
$bodyParams->addStrategy(new MyCustomBodyParamsStrategy());
```

To automate the registration, we recommend writing a factory for the
`BodyParamsMiddleware`, and replacing the `invokables` registration with a
registration in the `factories` section of the `middleware-pipeline.config.php`
file:

```php
use Zend\Expressive\Helper\BodyParams\BodyParamsMiddleware;

class MyCustomBodyParamsStrategyFactory
{
    public function __invoke($container)
    {
        $bodyParams = new BodyParamsMiddleware();
        $bodyParams->addStrategy(new MyCustomBodyParamsStrategy());
        return $bodyParams;
    }
}

// In config/autoload/middleware-pipeline.config.php:
use Zend\Expressive\Helper;

return [
    'dependencies' => [
        'invokables' => [
            // Remove this line:
            Helper\BodyParams\BodyParamsMiddleware::class => Helper\BodyParams\BodyParamsMiddleware::class,
            /* ... */
        ],
        'factories' => [
            // Add this line:
            Helper\BodyParams\BodyParamsMiddleware::class => MyCustomBodyParamsStrategyFactory::class,
            /* ... */
        ],
    ],
];
```

Alternately, use a [delegator factory](../container/factories.md).

## Removing the default strategies

By default, `BodyParamsMiddleware` composes the following strategies:

- `Zend\Expressive\Helper\BodyParams\FormUrlEncodedStrategy`
- `Zend\Expressive\Helper\BodyParams\JsonStrategy`

These provide the most basic approaches to parsing the request body. They
operate in the order they do to ensure the most common content type &mdash;
`application/x-www-form-urlencoded` &mdash; matches first, as the middleware
delegates parsing to the first match.

If you do not want to use these default strategies, you can clear them from the
middleware using `clearStrategies()`:

```php
$bodyParamsMiddleware->clearStrategies();
```

Note: if you do this, **all** strategies will be removed! As such, we recommend
doing this only immediately before registering any custom strategies you might
be using.
