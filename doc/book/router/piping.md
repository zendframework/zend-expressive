# Routing vs Piping

zend-expressive provides two mechanisms for adding middleware to your
application:

- piping, which is a foundation feature of the underlying
  [zend-stratigility](https://github.com/zendframework/zend-stratigility)
  implementation.
- routing, which is an additional feature provided by zend-expressive.

## Piping

zend-stratigility provides a mechanism termed *piping* for composing middleware
in an application. When you *pipe* middleware to the application, it is added to
a queue, and dequeued in order until a middleware returns a response instance.
If none ever returns a response instance, execution is delegated to a "final
handler", which determines whether or not to return an error, and, if so, what
kind of error to return.

Stratigility also allows you to segregate piped middleware to specific paths. As
an example:

```php
$app->pipe('/api', $apiMiddleware);
```

will execute `$apiMiddleware` only if the path matches `/api`; otherwise, it
will skip over that middleware.

This path segregation, however, is limited: it will only match literal paths.
This is done purposefully, to provide excellent baseline performance, and to
prevent feature creep in the library.

zend-expressive uses and exposes piping to users, with one addition: middleware
may be specified by service name, and zend-expressive will lazy-load the service
only when the middleware is invoked.

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
- Error handling. Typically these should be piped after any normal middleware.
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

Additionally, zend-expressive's routing capabilities are themselves implemented
as piped middleware.

As such, if you programmatically configure the router and add routes without
using `Application::route()`, you may run into issues with the order in which
piped middleware (middleware added to the application via the `pipe()` method)
is executed.

To ensure that everything executes in the correct order, you can call
`Application::pipeRouteMiddleware()` at any time to pipe it to the application.
As an example, after you have created your application instance:

```php
$app->pipe($middlewareToExecuteFirst);
$app->pipeRouteMiddleware();
$app->pipe($errorMiddleware);
```

If you fail to add any routes via `Application::route()` or to call
`Application::pipeRouteMiddleware()`, the routing middleware will be called
when executing the application. **This means that it will be last in the
middleware pipeline,** which means that if you registered any error
middleware, it can never be invoked.

To sum:

- Pipe middleware to execute on every request *before* routing any middleware
  and/or *before* calling `Application::pipeRouteMiddleware()`.
- Pipe error handling middleware *after* defining routes and/or *after* calling
  `Application::pipeRouteMiddleware()`.

If you use the provided `Zend\Expressive\Container\ApplicationFactory` for
retrieving your `Application` instance, you can do this by defining pre- and
post-pipeline middleware, and the factory will ensure everything is registered
correctly.
