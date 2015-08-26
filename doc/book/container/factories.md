# Provided Factories

Expressive provides several factories compatible with container-interop to
facilitate setting up common dependencies. The following is a list of provided
containers, what they will create, the suggested service name, and any
additional dependencies they may require.

All containers, unless noted otherwise, are in the `Zend\Expressive\Container`
namespace, and define an `__invoke()` method that accepts an
`Interop\Container\ContainerInterface` instance as the sole argument.

## ApplicationFactory

- **Provides**: `Zend\Expressive\Application`
- **Suggested Name**: `Zend\Expressive\Application`
- **Requires**: no additional services are required.
- **Optional**:
  - `Zend\Expressive\Router\RouterInterface`. When provided, the service will
    be used to construct the `Application` instance; otherwise, an Aura router
    implementation will be used.
  - `Zend\Expressive\FinalHandler`. This is a meta-service, as the only concrete
    type required is a callable that can be used as a final middleware in the
    case that the stack is exhausted before execution ends. By default, an
    instance of `Zend\Stratigility\FinalHandler` will be used.
  - `Zend\Diactoros\Response\EmitterInterface`. If none is provided, an instance
    of `Zend\Expressive\Emitter\EmitterStack` composing a
    `Zend\Diactoros\Response\SapiEmitter` instance will be used.
  - `config`, an array or `ArrayAccess` instance. This will be used to seed the
    application instance with pre/post pipeline middleware and/or routed
    middleware (see more below).

Additionally, the container instance itself is injected into the `Application`
instance.

When the `config` service is present, the factory can utilize several keys in
order to seed the `Application` instance:

- `middleware_pipeline` can be used to seed pre- and/or post-routing middleware:

```php
'middleware_pipeline' => [
  // An array of middleware to register prior to registration of the
  // routing middleware:
  'pre_routing' => [
  ],
  // An array of middleware to register after registration of the
  // routing middleware:
  'post_routing' => [
  ],
],
```

Each item of each array must be an array itself, with the following structure:

```php
[
  // required:
  'middleware' => 'Name of middleware service, or a callable',
  // optional:
  'path'  => '/path/to/match',
  'error' => true,
],
```

The `middleware` key itself is the middleware to execute, and must be a
callable or the name of another defined service. If the `path` key is present,
that key will be used to segregate the middleware to a specific matched path
(in other words, it will not execute if the path is not matched). If the
`error` key is present and boolean `true`, then the middleware will be
registered as error middleware. (This is necessary due to the fact that the
factory defines a callable wrapper around middleware to enable lazy-loading of
middleware.)

- `routes` is used to define routed middleware. The value must be an array,
consisting of arrays defining each middleware:

```php
'routes' => [
  [
      'path' => '/path/to/match',
      'middleware' => 'Middleware Service Name or Callable',
      'allowed_methods' => [ 'GET', 'POST', 'PATCH' ],
      'options' => [
          'stuff' => 'to',
          'pass'  => 'to',
          'the'   => 'underlying router',
      ],
  ],
  // etc.
],
```

Each route *requires*:

- `path`: the path to match. Format will be based on the router you choose for
your project.

- `middleware`: a callable or a service name for the middleware to execute
when the route matches.

Optionally, the route definition may provide:

- `allowed_methods`: an array of allowed HTTP methods. If not provided, the
application assumes any method is allowed.

- `name`: if not provided, the path will be used as the route name (and, if
specific HTTP methods are allowed, a list of those).

- `options`: a key/value set of additional options to pass to the underlying
router implementation for the given route. (Typical use cases include
passing constraints or default values.)

## TemplatedErrorHandlerFactory

- **Provides**: `Zend\Expressive\TemplatedErrorHandler`
- **Suggested Name**: `Zend\Expressive\FinalHandler`
- **Requires**: no additional services are required.
- **Optional**:
  - `Zend\Expressive\Template\TemplateInterface`. If not provided, the error
    handler will not use templated responses.
  - `config`, an array or `ArrayAccess` instance. This will be used to seed the
    `TemplatedErrorHandler` instance with template names to use for errors (see
    more below).

When the `config` service is present, the factory can utilize the
`zend-expressive` top-level key, with the `error_handler` second-level key, to
seed the `Templated` instance:

```php
'zend-expressive' => [
    'error_handler' => [
        'template_404'   => 'name of 404 template',
        'template_error' => 'name of error template',
    ],
],
```

## WhoopsErrorHandlerFactory

- **Provides**: `Zend\Expressive\TemplatedErrorHandler`
- **Suggested Name**: `Zend\Expressive\FinalHandler`
- **Requires**:
  - `Zend\Expressive\Whoops`, which should provide a `Whoops\Run` instance.
  - `Zend\Expressive\WhoopsPageHandler`, which should provide a
    `Whoops\Handler\PrettyPageHandler` instance.
- **Optional**:
  - `Zend\Expressive\Template\TemplateInterface`. If not provided, the error
    handler will not use templated responses.
  - `config`, an array or `ArrayAccess` instance. This will be used to seed the
    instance with template names to use for errors (see more below).

This factory uses `config` in the same way as the
`TemplatedErrorHandlerFactory`.

## WhoopsFactory

- **Provides**: `Whoops\Run`
- **Suggested Name**: `Zend\Expressive\Whoops`
- **Requires**:
  - `Zend\Expressive\WhoopsPageHandler`
- **Optional**:
  - `config`, an array or `ArrayAccess` instance. This will be used to seed
    additional page handlers, specifically the `JsonResponseHandler` (see
    more below).

This factory creates and configures a `Whoops\Run` instance so that it will work
properly with `Zend\Expressive\Application`; this includes disabling immediate
write-to-output, disabling immediate quit, etc. The `PrettyPageHandler` returned
for the `Zend\Expressive\WhoopsPageHandler` service will be injected.

It consumes the following `config` structure:

```php
'whoops' => [
    'json_exceptions' => [
        'display'    => true,
        'show_trace' => true,
        'ajax_only'  => true,
    ],
],
```

If no `whoops` top-level key is present in the configuration, a default instance
with no `JsonResponseHandler` composed will be created.

## WhoopsPageHandlerFactory

- **Provides**: `Whoops\Handler\PrettyPageHandler`
- **Suggested Name**: `Zend\Expressive\WhoopsPageHandler`
- **Optional**:
  - `config`, an array or `ArrayAccess` instance. This will be used to further
    configure the `PrettyPageHandler` instance, specifically with editor
    configuration (for linking files such that they open in the configured
    editor).

It consumes the following `config` structure:

```php
'whoops' => [
    'editor' => 'editor name, editor service name, or callable',
],
```

The `editor` value must be a known editor name (see the Whoops documentation for
pre-configured editor types), a callable, or a service name to use.
