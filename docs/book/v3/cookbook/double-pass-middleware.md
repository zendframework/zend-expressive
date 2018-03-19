# Using Double-Pass Middleware

Expressive uses [PSR-15](https://www.php-fig.org/psr/psr-15/) middleware and
request handlers exclusively as of version 3.

In previous releases, however, we supported "double-pass" middleware, and a
number of third-party packages provided double-pass middleware. How can you use
this middleware with Expressive 3?

> ### What is Double-Pass Middleware?
>
> Double pass middleware receives both the request and a response in addition to
> the handler, and passes both the request and response to the handler when
> invoking it:
>
> ```php
> function (ServerRequestInterface $request, ResponseInterface $response, callable $next)
> {
>     $response = $next($request, $response);
>     return $response->withHeader('X-Test', time());
> }
> ```
>
> It is termed "double pass" because you pass _both_ the request _and_ response when
> delegating to the next layer.

## doublePassMiddleware function

zend-stratigility v2.2 and v3.0 ship a utility function,
`Zend\Stratigility\doublePassMiddleware()`, that will decorate a callable
double-pass middleware using a `Zend\Stratigility\Middleware\DoublePassMiddlewareDecorator` 
instance; this latter is a PSR-15 impelementation, and can thus be used in your
middleware pipelines.

The function (and class) also expects a [PSR-7](https://www.php-fig.org/psr/psr-7/)
`ResponseInterface` instance as a second argument; this is then passed as the
`$response` argument to the double-pass middleware. The following examples
demostrate both piping and routing to double pass middleware using this
technique, and using zend-diactoros to provide the response instance.

```php
use Zend\Diactoros\Response;

use function Zend\Stratigility\doublePassMiddleware;

$app->pipe(doublePassMiddleware(function ($request, $response, $next) {
    $response = $next($request, $response);
    return $response->withHeader('X-Clacks-Overhead', 'GNU Terry Pratchett');
}, new Response())); // <-- note the response

$app->get('/api/ping', doublePassMiddleware(function ($request, $response, $next) {
    return new Response\JsonResponse([
        'ack' => time(),
    ]);
}, new Response())); // <-- note the response
```

## Double-Pass Middleware Services

What if you're piping or routing to a _service_ &mdash; for instance, a class
provided by a third-party implementation?

In this case, you have one of two options:

- Decorate the middleware before returning it from the factory that creates it.
- Use a [delegator factory](../features/container/delegator-factories.md) to
  decorate the middleware.

### Decorating via factory

If you have control of the factory that creates the double-pass middleware you
will be using in your application, you can use the strategy outlined above to
decorate your middleware before returning it, with one minor change: you can
pull a response factory from the container as well.

To demonstrate:

```php
use Psr\Container\ContainerInterface;
use Psr\Http\Message\ResponseInterface;

use function Zend\Stratigility\doublePassMiddleware;

class SomeDoublePassMiddlewareFactory
{
    public function __invoke(ContainerInterface $container)
    {
        // Create the middleware instance somehow. This example
        // assumes it is in `$middleware` when done.

        return doublePassMiddleware(
            $middleware,
            ($container->get(ResponseInterface::class))()
        );
    }
}
```

That last line may look a little strange.

The `Psr\Http\Response\ResponseInterface` service returns a callable _factory_
for producing response instances, and not a response instance itself. As such,
we pull it, and then invoke it to produce the response instance for our
double-pass middleware.

This approach will work, but it means code duplication everywhere you have
double-pass middleware. Let's look at the delegator factory solution.

### Decorating via delegator factory

Delegator factories can be re-used for multiple services. In our case, we'll
re-use it to decorate double-pass middleware.

The delegator factory would look like this:

```php
use Psr\Container\ContainerInterface;
use Psr\Http\Message\ResponseInterface;

use function Zend\Stratigility\doublePassMiddleware;

class DoublePassMiddlewareDelegator
{
    public function __invoke(Container $container, string $serviceName, callable $callback)
    {
        return doublePassMiddleware(
            $callback(),
            ($container->get(ResponseInterface::class))()
        );
    }
}
```

This looks similar to our previous solution, but is self-contained; we rely on
the `$callback` argument to produce the middleware we want to decorate.

Then, for each service we have that represents double-pass middleware, we can
provide configuration like the following:

```php
return [
    'dependencies' => [
        'delegators' => [
            SomeDoublePassMiddleware::class => [
                DoublePassMiddlewareDelegator::class,
            ],
        ],
    ],
];
```

This approach has a couple of benefits:

- We do not need to change existing factories.
- We do not need to extend factories from third-party services.
- We can see explicitly in our configuration all services we consume that are
  double-pass middleware. This will help us identify projects we want to
  contribute PSR-15 patches to, or potentially migrate away from, or middleware
  of our own we need to refactor.

### Extending the MiddlewareContainer

Another possibility is to extend `Zend\Expressive\MiddlewareContainer` to add
awareness of double-pass middleware, and have it auto-decorate them for you.

A contributor has created such a library:

- https://github.com/Moln/expressive-callable-middleware-compat

You can install it using `composer require moln/expressive-callable-middleware-compat`.
Once installed, add its `Moln\ExpressiveCallableCompat\ConfigProvider` as an
entry in your `config/config.php` **after** the `Zend\Expressive\ConfigProvider`
entry. This last point is particularly important: providers are merged in the order
presented, with later entries having precedence; you need to ensure the new
package overrides the `MiddlewareContainer` service provided by zend-expressive!

When you use this approach, it will automatically detect double-pass middleware
and decorate it for you.

The main drawback with such an approach is that it will not help you identify
double-pass middleware in your system.
