# Routing

One fundamental feature of zend-expressive is that it provides mechanisms for
implementing dynamic routing, a feature required in most modern web
applications. As an example, you may want to allow matching both a resource, as
well as individual items of that resource:

- `/books` might return a collection of books
- `/books/zend-expressive` might return the individual book identified by
  "zend-expressive".

Expressive does not provide routing on its own; you must choose a routing
adapter that implements `Zend\Expressive\Router\RouterInterface` and provide it
to the `Application` instance. This allows you to choose the router with the
capabilities that best match your own needs, while still providing a common
abstraction for defining and aggregating routes and their related middleware.

## Retrieving matched parameters

Routing enables the ability to match dynamic path segments (or other
criteria). Typically, you will want access to the values matched. The routing
middleware injects any matched parameters as returned by the underlying router
into the request as *attributes*.

In the example above, let's assume the route was defined as `/books/:id`, where
`id` is the name of the dynamic segment. This means that in the middleware
invoked for this route, you can fetch the `id` attribute to discover what was
matched:

```php
$id = $request->getAttribute('id');
```

## Retrieving the matched route

When routing is successful, the routing middleware injects a
`Zend\Expressive\Router\RouteResult` instance as a request attribute, using that
class name as the attribute name. The `RouteResult` instance provides you access
to the following:

- The matched `Zend\Expressive\Router\Route` instance, via `$result->getMatchedRoute()`.
- The matched route name, via `$result->getMatchedRouteName()` (or via
  `$result->getMatchedRoute()->getName()`).
- The matched middleware, via `$result->getMatchedMiddleware()` (or via
  `$result->getMatchedRoute()->getMiddleware()`).
- Matched parameters, via `$result->getMatchedParams()` (as noted above, these
  are also each injected as discrete request attributes).
- Allowed HTTP methods, via `$result->getAllowedMethods()`.

As an example, you could use middleware similar to the following to return a 403
response if routing was successful, but no `Authorization` header is present:

```php
use Interop\Http\ServerMiddleware\DelegateInterface;
use Zend\Diactoros\Response\EmptyResponse;
use Zend\Expressive\Router\RouteResult;

function ($request, DelegateInterface $delegate) use ($routesRequiringAuthorization, $validator) {
    if (! ($result = $request->getAttribute(RouteResult::class, false))) {
        // No route matched; delegate to next middleware
        return $delegate->process($request);
    }

    if (! in_array($result->getMatchedRouteName(), $routesRequiringAuthorization, true)) {
        // Not a route requiring authorization
        return $delegate->process($request);
    }

    $header = $request->getHeaderLine('Authorization');
    if (! $validator($header)) {
        return new EmptyResponse(403);
    }

    return $delegate->process($request);
}
```

Note that the first step is to determine if we have a `RouteResult`; if we do
not have one, we should either delegate to the next middleware, or return some
sort of response (generally a 404). In the case of Expressive, a later
middleware will generate the 404 response for us, so we can safely delegate.

## URI generation

Because routers have knowledge of the various paths they can match, they are
also typically used within applications to generate URIs to other application
resources. Expressive provides this capability in the `RouterInterface`,
either delegating to the underlying router implementations or providing a
compatible implementation of its own.

At it's most basic level, you call the `generateUri()` method with a route name
and any substitutions you want to make:

```php
$uri = $router->generateUri('book', ['id' => 'zend-expressive']);
```

Some routers may support providing _options_ during URI generation. Starting in
zend-expressive-router 2.0, which ships with Expressive starting with version
2.0, you may also pass a third argument to `generateUri()`, an array of router
options:

```php
$uri = $router->generateUri('book', ['id' => 'zend-expressive'], [
    'translator'  => $translator,
    'text_domain' => $currentLocale,
]);
```

## Supported implementations

Expressive currently ships with adapters for the following routers:

- [Aura.Router](aura.md)
- [FastRoute](fast-route.md)
- [zend-mvc Router](zf2.md)
