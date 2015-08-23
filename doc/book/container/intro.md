# Containers

zend-expressive promotes and advocates the usage of
[Dependency Injection](http://www.martinfowler.com/articles/injection.html)/[Inversion of Control](https://en.wikipedia.org/wiki/Inversion_of_control)
containers when writing your applications. These should be used for the
following:

- Defining *application* dependencies: routers, template engines, error
  handlers, even the `Application` instance itself.

- Defining *middleware* and related dependencies.

The `Application` instance itself stores a container, from which it fetches
middleware when ready to dispatch it; this encourages the idea of defining
middleware-specific dependencies, and factories for ensuring they are injected.

To facilitate this and allow you as a developer to choose the container you
prefer, zend-expressive typehints against [container-interop](https://github.com/container-interop/container-interop),
and throughout this manual, we attempt to show using a variety of containers in
examples.
