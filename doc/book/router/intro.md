# Routing

One fundamental feature of zend-expressive is that it provides mechanisms for
implementing dynamic routing, a feature required in most modern web
applications. As an example, you may want to allow matching both a resource, as
well as individual items of that resource:

- `/books` might return a collection of books
- `/books/zend-expressive` might return the individual book identified by
  "zend-expressive".

zend-expressive does not provide routing on its own; you must choose a routing
adapter that implements `Zend\Expressive\Router\RouterInterface` and provide it
to the `Application` instance. This allows you to choose the router with the
capabilities that best match your own needs, while still providing a common
abstraction for defining and aggregating routes and their related middleware.

## URI generation

Because routers have knowledge of the various paths they can match, they are
also typically used within applications to generate URIs to other application
resources. zend-expressive provides this capability in the `RouterInterface`,
either delegating to the underlying router implementations or providing a
compatible implementation of its own.
