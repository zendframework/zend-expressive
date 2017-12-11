# How can I set custom 404 page handling?

> ### Deprecated
>
> This recipe is deprecated with the release of Expressive 2.0, as that release
> now expects and requires that you provide innermost middleware that will
> return a response, and provides `Zend\Expressive\Middleware\NotFoundHandler`
> as a default implementation. Please see the [error handling chapter](../features/error-handling.md)
> for more information.

In some cases, you may want to handle 404 errors separately from the
[final handler](../features/error-handling.md). This can be done by registering
middleware that operates late &mdash; specifically, after the routing
middleware. Such middleware will be executed if no other middleware has
executed, and/or when all other middleware calls `return $next()`
without returning a response. Such situations typically mean that no middleware
was able to complete the request.

Your 404 handler can take one of two approaches:

- It can set the response status and call `$next()` with an error condition. In
  such a case, the final handler *will* likely be executed, but will have an
  explicit 404 status to work with.
- It can create and return a 404 response itself.

## Calling next with an error condition

In the first approach, the `NotFound` middleware can be as simple as this:

```php
namespace App;

class NotFound
{
    public function __invoke($req, $res, $next)
    {
        // Other things can be done here; e.g., logging
        return $next($req, $res->withStatus(404), 'Page Not Found');
    }
}
```

This example uses the third, optional argument to `$next()`, which is an error
condition. Internally, the final handler will typically see this, and return an
error page of some sort. Since we set the response status, and it's an error
status code, that status code will be used in the generated response.

The `TemplatedErrorHandler` will use the error template in this particular case,
so you will likely need to make some accommodations for 404 responses in that
template if you choose this approach.

## 404 Middleware

In the second approach, the `NotFound` middleware will return a full response.
In our example here, we will render a specific template, and use this to seed
and return a response.

```php
namespace App;

use Zend\Expressive\Template\TemplateRendererInterface;

class NotFound
{
    private $renderer;

    public function __construct(TemplateRendererInterface $renderer)
    {
        $this->renderer = $renderer;
    }

    public function __invoke($req, $res, $next)
    {
        // other things can be done here; e.g., logging
        // Now set the response status and write to the body
        $response = $res->withStatus(404);
        $response->getBody()->write($this->renderer->render('error::not-found'));
        return $response;
    }
}
```

This approach allows you to have an application-specific workflow for 404 errors
that does not rely on the final handler.

## Registering custom 404 handlers

We can register either `App\NotFound` class above as service in the
[service container](../features/container/intro.md). In the case of the second approach,
you would also need to provide a factory for creating the middleware (to ensure
you inject the template renderer).

From there, you still need to register the middleware. This middleware is not
routed, and thus needs to be piped to the application instance. You can do this
via either configuration, or manually.

To do this via configuration, add an entry under the `middleware_pipeline`
configuration, after the dispatch middleware:

```php
'middleware_pipeline' => [
    /* ... */
    'routing' => [
        'middleware' => [
            Zend\Expressive\Container\ApplicationFactory::ROUTING_MIDDLEWARE,
            Zend\Expressive\Helper\UrlHelperMiddleware::class,
            Zend\Expressive\Container\ApplicationFactory::DISPATCH_MIDDLEWARE,
        ],
        'priority' => 1,
    ],
    [
        'middleware' => 'App\NotFound',
        'priority' => -1,
    ],
    /* ... */
],
```

The above example assumes you are using the `ApplicationFactory` and/or the
Expressive skeleton to manage your application instantiation and configuration.

To manually add the middleware, you will need to pipe it to the application
instance:

```php
$app->pipe($container->get('App\NotFound'));
```

This must be done *after*:

- calling `$app->pipeDispatchMiddleware()`, **OR**
- pulling the `Application` instance from the service container (assuming you
  used the `ApplicationFactory`).

This is to ensure that the `NotFound` middleware executes *after* any routed
middleware, as you only want it to execute if no routed middleware was selected.
