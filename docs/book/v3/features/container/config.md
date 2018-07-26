# Container configuration

> This chapter is primarily written for container providers, so that they know
> what configuration features must be compatible, and what compatibility
> ultimately means within the project.

[PSR-11](https://www.php-fig.org/psr/psr-11/) defines an interface for
dependency injection containers, and that interface is geared towards
_consumption_ of the container &mdash; not _population_ of it.

Expressive _consumes_ a PSR-11 container, but also provides _configuration_ for
a container: it defines what services it needs, and how to create them.

As such, any container consumed by Expressive must also understand its
configuration format, and deliver consistent understanding of that format when
providing services based on it.

This document describes the configuration format, and details expectations for
implementations.

## The format

Container configuration is provided within the `dependencies` key of
configuration. That key is structured as follows:

```php
return [
    'dependencies' => [
        'services' => [
            // name => instance pairs
            'config' => $config,
        ],
        'aliases' => [
            // alias => target pairs
            'page-handler' => SomePageHandler::class,
        ],
        'factories' => [
            // service => factory pairs
            SomePageHandler::class => SomePageHandlerFactory::class,
        ],
        'invokables' => [
            // service => instantiable class pairs
            SomeInstantiableClass::class => SomeInstantiableClass::class,
            'an-alias-for' => SomeInstantiableClass::class,
        ],
        'delegators' => [
            // service => array of delegator factory pairs
            SomeInstantiableClass::class => [
                InjectListenersDelegator::class,
                InjectLoggerDelegator::class,
            ],
        ],
    ],
];
```

## Services

_Services_ are actual instances you want to retrieve later from the container.
These are generally provided at initial creation; the `config` service is
populated in this way.

When retrieving a service mapped in this way, you will always receive the
initial instance.

## Aliases

_Aliases_ map a service _alias_ to another service, and are provided as
key/value pairs. As an example:

```php
'aliases' => [
    'Zend\Expressive\Delegate\DefaultDelegate' => \Zend\Expressive\Handler\NotFoundHandler::class,
],
```

In this case, if the service named "Zend\\Expressive\\Delegate\\DefaultDelegate"
is requested, the container should resolve that to the service
`Zend\Expressive\Handler\NotFoundHandler` and return that instead.

Aliases may reference any other service defined in the container. These include
services defined under the keys:

- `services`
- `factories`
- `invokables`
- or even other `aliases`

When returning an aliased service, the container MUST return the same instance
as if the target service were retrieved. When aliases may reference other
aliases, the rule applies to the final resolved service, and not any
intermediary aliases.

## Factories

_Factories_ map a service name to the factory capable of producing the instance.

A _factory_ is any PHP callable capable of producing the instance:

- Function names
- Closures
- Class instances that define the method `__invoke()`
- Callable references to static methods
- Array callables referencing static or instance methods

They may also be the _class name_ of a directly instantiable class (no
constructor arguments) that defines `__invoke()`. Generally, this latter
convention is used, as class names are serializable, while closures, objects,
and array callables often are not.

Factories are guaranteed to receive the PSR-11 container as an argument,
allowing you to pull other services from the container as necessary to fulfill
dependencies of the class being created and returned. Additionally, containers
SHOULD pass the service name requested as the second argument; factories can
determine whether that argument is necessary.

A typical factory will generally ignore the second argument:

```php
use Psr\Container\ContainerInterface;
use Zend\Expressive\Template\TemplateRendererInterface;

class SomePageHandlerFactory
{
    public function __invoke(ContainerInterface $container)
    {
        return new SomePageHandler(
            $container->get(TemplateRendererInterface::class)
        );
    }
}
```

You can, however, re-use a factory for multiple services by accepting the second
argument and varying creation based on it:

```php
use Psr\Container\ContainerInterface;
use Zend\Expressive\Template\TemplateRendererInterface;

class PageFactory
{
    public function __invoke(ContainerInterface $container, string $serviceName)
    {
        $name = strtolower($serviceName);
        return new PageHandler(
            $container->get(TemplateRendererInterface::class),
            $name
        );
    };
}
```

The above could be mapped for several services:

```php
return [
    'dependencies' => [
        'factories' => [
            'hello-world' => PageFactory::class,
            'about'       => PageFactory::class,
        ],
    ],
];
```

In general, services should be cached by the container after initial creation;
factories should only be called once for any given service name.

## Invokables

_Invokables_ refer to any class that may be instantiated without any constructor
arguments. In other words, one should be able to create an instance solely be
calling `new $className()`.

Configuration for invokables looks verbose; it's a map of the service name to
the class name to instantiate, and, generally, these are the same values.

However, you can _also_ provide a different service name. In those situations,
containers MUST treat the service name as an alias to the final class name, and
allow retrieving the service by EITHER the alias OR the class name.

As an example, given the following configuration:

```php
'dependencies' => [
    'invokables' => [
        'HelloWorld' => PageAction::class,
    ],
],
```

the container should allow retrieval of both the services "HelloWorld" as well
as the "PageAction" class.

## Delegator Factories

Delegator factories are factories that may be used to _decorate_ or _manipulate_
a service before returning it from the container. They are covered in detail [in
another chapter](delegator-factories.md), and delegator factories have the
following signature:

```php
use Psr\Container\ContainerInterface;

function (
    ContainerInterface $container,
    string $serviceName,
    callable $callback
)
```

Configuration for delegator factories is using the "delegators" sub-key of the
"dependencies" configuration. Each entry is a service name pointing to an
_array_ of delegator factories.

Delegator factories are called in the order they appear in configuration. For
the first delegator factory, the `$callback` argument will be essentially the
return value of `$container->get()` for the given service _if there were no
delegator factories attached to it_; in other words, it would be the
[invokable](#invokables) or service returned by a [factory](#factories), after
[alias](#aliases) resolution.

> Delegators **DO NOT** operate on items in the `services` configuration!
> All items in the `services` configuration are considered complete, and will
> always be served as-is.

Each delegator then returns a value, and that value will be what `$callback`
returns for the next delegator. If the delegator is the last in the list, then
what it returns becomes the final value for the service in the container;
subsequent calls to `$container->get()` for that service will return that value.
Delegators MUST return a value!

For container implementors, delegators MUST only be called when initially
creating the service, and not each time a service is retrieved.

Common use cases for delegators include:

- Decorating an instance so that it may be used in another context (e.g.,
  decorating a PHP `callable` to be used as PSR-15 middleware).
- Injecting collaborators (e.g., adding listeners to the `ErrorHandler`).
- Conditionally replacing an instance based on configuration (e.g., swapping
  debug-enabled middleware for production middleware).

## Other capabilities

Selection of a dependency injection container should be based on capabilities
that implementation provides. This may be performance, or it may be additional
features beyond those specified here. We encourage _application developers_ to
make full use of the container they select. The only caveat is that the above
features MUST be supported by implementations for compatibility purposes, and
the above are the only features _package providers_ may count on when providing
container configuration.

Examples of how the above capabilities may be implemented include:

- [zendframework/zend-auradi-config](https://github.com/zendframework/zend-auradi-config)
- [zendframework/zend-pimple-config](https://github.com/zendframework/zend-pimple-config)
- [jsoumelidis/zend-sf-di-config](https://github.com/zendframework/jsoumelidis/zend-sf-di-config)
