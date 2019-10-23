# Containers

Expressive promotes and advocates the usage of
[Dependency Injection](http://www.martinfowler.com/articles/injection.html)/[Inversion of Control](https://en.wikipedia.org/wiki/Inversion_of_control)
(also referred to as DI — or DIC — and IoC, respectively)
containers when writing your applications. These should be used for the
following:

- Defining *application* dependencies: routers, template engines, error
  handlers, even the `Application` instance itself.

- Defining *middleware* and related dependencies.

The application skeleton wires together dependency configuration, which is then
used to create a container. This in turn is used to seed a
`Zend\Expressive\MiddlewareContainer` for purposes of retrieving middleware for
the `Application` instance (via another intermediary,
`Zend\Expressive\MiddlewareFactory`). When the application is ready to execute
middleware or a handler, it will fetch it from the container. This approach
encourages the idea of defining middleware-specific dependencies, and factories
for ensuring they are injected.

To facilitate this and allow you as a developer to choose the container you
prefer, zend-expressive typehints against [PSR-11 Container](https://www.php-fig.org/psr/psr-11/),
and throughout this manual, we attempt to show using a variety of containers in
examples.

At this time, we document support for the following specific containers:

- [zend-servicemanager](zend-servicemanager.md)
- [pimple-interop](pimple.md)
- [aura.di](aura-di.md)

> ### Service Names
>
> We recommend using fully-qualified class names whenever possible as service
> names, with one exception: in cases where a service provides an implementation
> of an interface used for typehints, use the interface name.
>
> Following these practices encourages the following:
>
> - Consumers have a reasonable idea of what the service should return.
> - Using interface names as service names promotes re-use and substitution.
>
> In a few cases, we define "virtual service" names. These are cases where there is no
> clear typehint to follow. For example, we may want to imply specific
> configuration is necessary; [Whoops](http://filp.github.io/whoops/) requires
> specific configuration to work correctly with Expressive, and thus we do not
> want a generic service name for it. We try to keep these to a minimum, however.
