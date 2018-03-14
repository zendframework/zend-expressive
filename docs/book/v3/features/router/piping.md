# Routing vs Piping

Expressive provides two mechanisms for adding middleware to your
application:

- piping, which is a foundation feature of the underlying
  [zend-stratigility](https://docs.zendframework.com/zend-stratigility/)
  implementation.
- routing, which is an additional feature provided by zend-expressive.

## Piping

zend-stratigility provides a mechanism termed *piping* for composing middleware
in an application. When you *pipe* middleware to the application, it is added to
a queue, and dequeued in order until a middleware returns a response instance.

Expressive adds the ability to segregate middleware to a specific path; as an
example:

```php
$app->pipe('/api', $apiMiddleware);
```

will execute `$apiMiddleware` only if the path matches `/api`; otherwise, it
will skip over that middleware.

This path segregation, however, is limited: it will only match literal paths.
This is done purposefully, to provide excellent baseline performance, and to
prevent feature creep in the library.

> #### Path segregation
>
> Internally, when `Application::pipe()` detects two arguments, it calls
> `Zend\Stratigility\path()` using the two arguments in order to create a
> `Zend\Stratigility\Middleware\PathMiddlewareDecorator` instance; this latter is
> what performs the actual path checking.

Expressive uses and exposes piping to users, with one addition: **middleware may
be specified by service name or an array of service names, and zend-expressive
will lazy-load the service only when the middleware is invoked**.

In order to accomplish the lazy-loading, zend-expressive wraps the calls to
fetch and dispatch the middleware inside a
`Zend\Expressive\Middleware\LazyLoadingMiddleware` instance; as such, there is
no overhead to utilizing service-based middleware _until it is dispatched_.

## Routing

Routing is the process of discovering values from the incoming request based on
defined criteria. That criteria might look like:

- `/book/:id` (ZF2)
- `/book/{id}` (Aura.Router)
- `/book/{id:\d+}` (FastRoute)

In each of the above, if the router determines that the request matches the
criteria, it will indicate:

- the route that matched
- the `id` parameter was matched, and the value matched

Most routers allow you to define arbitrarily complex rules, and many even allow
you to define:

- default values for unmatched parameters
- criteria for evaluating a match (such as a regular expression)
- additional criteria to meet (such as SSL usage, allowed query string
  variables, etc.)

As such, routing is more powerful than the literal path matching used when
piping, but it is also more costly (though routers such as FastRoute largely
make such performance issues moot).

## When to Pipe

In Expressive, we recommend that you pipe middleware in the following
circumstances:

- It should (potentially) run on every execution. Examples for such usage
  include:
    - Logging requests
    - Performing content negotiation
    - Handling cookies
- Error handling.
- Application segregation. You can write re-usable middleware, potentially even
  based off of Expressive, that contains its own routing logic, and compose it
  such that it only executes if it matches a sub-path.

## When to Route

Use routing when:

- Your middleware is reacting to a given path.
- You want to use dynamic routing.
- You want to restrict usage of middleware to specific HTTP methods.
- You want to be able to generate URIs to your middleware.

The above cover most use cases; *in other words, most middleware should be added
to the application as routed middleware*.

## Controlling middleware execution order

As noted in the earlier section on piping, piped middleware is *queued*, meaning
it has a FIFO ("first in, first out") execution order.

Additionally, zend-expressive's routing and dispatch capabilities are themselves
implemented as piped middleware.

To ensure your middleware is piped correctly, keep in mind the following:

- If middleware should execute on _every request_, pipe it early.
- Pipe routing and dispatch middleware using their dedicated application methods
  (more on this below), optionally with middleware between them to further shape
  application flow.
- Pipe middleware guaranteed to return a response (such as a "not found" handler
  or similar) _last_.

As an example:

```php
$app->pipe(OriginalMessages::class);
$app->pipe(ServerUrlMiddleware::class);
$app->pipe(XClacksOverhead::class);
$app->pipe(ErrorHandler::class);
$app->pipe(RouteMiddleware::class);
$app->pipe(ImplicitHeadMiddleware::class);
$app->pipe(ImplicitOptionsMiddleware::class);
$app->pipe(MethodNotAllowedMiddleware::class);
$app->pipe(UrlHelperMiddleware::class);
$app->pipe(AuthorizationCheck::class);
$app->pipe(DispatchMiddleware::class);
$app->pipe(NotFoundHandler::class);
```
