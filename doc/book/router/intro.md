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

## Supported implementations

Expressive currently ships with adapters for the following routers:

- [Aura.Router](aura.md)
- [FastRoute](fast-route.md)
- [zend-mvc Router](zf2.md)
