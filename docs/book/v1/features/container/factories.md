# Provided Factories

Expressive provides several factories compatible with container-interop to
facilitate setting up common dependencies. The following is a list of provided
containers, what they will create, the suggested service name, and any
additional dependencies they may require.

All factories, unless noted otherwise, are in the `Zend\Expressive\Container`
namespace, and define an `__invoke()` method that accepts an
`Interop\Container\ContainerInterface` instance as the sole argument.

## ApplicationFactory

- **Provides**: `Zend\Expressive\Application`
- **Suggested Name**: `Zend\Expressive\Application`
- **Requires**: no additional services are required.
- **Optional**:
    - `Zend\Expressive\Router\RouterInterface`. When provided, the service will
      be used to construct the `Application` instance; otherwise, an FastRoute router
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

- `middleware_pipeline` can be used to seed the middleware pipeline:

  ```php
  'middleware_pipeline' => [
      // An array of middleware to register.
      [ /* ... */ ],
      Zend\Expressive\Container\ApplicationFactory::ROUTING_MIDDLEWARE,
      Zend\Expressive\Container\ApplicationFactory::DISPATCH_MIDDLEWARE,
      [ /* ... */ ],
  ],
  ```

  Each item of the array, other than the entries for routing and dispatch
  middleware, must be an array itself, with the following structure:

  ```php
  [
      // required:
      'middleware' => 'Name of middleware service, or a callable',
      // optional:
      'path'  => '/path/to/match',
      'error' => true,
      'priority' => 1, // Integer
  ],
  ```

  The `middleware` key itself is the middleware to execute, and must be a
  callable or the name of another defined service. If the `path` key is present,
  that key will be used to segregate the middleware to a specific matched path
  (in other words, it will not execute if the path is not matched). If the
  `error` key is present and boolean `true`, then the middleware will be
  registered as error middleware. (This is necessary due to the fact that the
  factory defines a callable wrapper around middleware to enable lazy-loading of
  middleware.) The `priority` defaults to 1, and follows the semantics of
  [SplPriorityQueue](http://php.net/SplPriorityQueue): higher integer values
  indicate higher priority (will execute earlier), while lower/negative integer
  values indicate lower priority (will execute last). Default priority is 1; use
  granular priority values to specify the order in which middleware should be
  piped to the application.

  You *can* specify keys for each middleware specification. These will be
  ignored by the factory, but can be useful when merging several configurations
  into one for the application.

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
    - `Zend\Expressive\Template\TemplateRendererInterface`. If not provided, the error
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
    - `Zend\Expressive\Template\TemplateRendererInterface`. If not provided, the error
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

## PlatesRendererFactory

- **Provides**: `Zend\Expressive\Plates\PlatesRenderer`
- **FactoryName**: `Zend\Expressive\Plates\PlatesRendererFactory`
- **Suggested Name**: `Zend\Expressive\Template\TemplateRendererInterface`
- **Requires**: no additional services are required.
- **Optional**:
    - `config`, an array or `ArrayAccess` instance. This will be used to further
      configure the `Plates` instance, specifically with the filename extension
      to use, and paths to inject.

It consumes the following `config` structure:

```php
'templates' => [
    'extension' => 'file extension used by templates; defaults to html',
    'paths' => [
        // namespace / path pairs
        //
        // Numeric namespaces imply the default/main namespace. Paths may be
        // strings or arrays of string paths to associate with the namespace.
    ],
]
```

One note: Due to a limitation in the Plates engine, you can only map one path
per namespace when using Plates.

## TwigRendererFactory

- **Provides**: `Zend\Expressive\Twig\TwigRenderer`
- **FactoryName**: `Zend\Expressive\Twig\TwigRendererFactory`
- **Suggested Name**: `Zend\Expressive\Template\TemplateRendererInterface`
- **Requires**: no additional services are required.
- **Optional**:
    - `Zend\Expressive\Router\RouterInterface`; if found, it will be used to
      seed a `Zend\Expressive\Twig\TwigExtension` instance for purposes
      of rendering application URLs.
    - `config`, an array or `ArrayAccess` instance. This will be used to further
      configure the `Twig` instance, specifically with the filename extension,
      paths to assets (and default asset version to use), and template paths to
      inject.

It consumes the following `config` structure:

```php
'debug' => boolean,
'templates' => [
    'cache_dir' => 'path to cached templates',
    'assets_url' => 'base URL for assets',
    'assets_version' => 'base version for assets',
    'extension' => 'file extension used by templates; defaults to html.twig',
    'paths' => [
        // namespace / path pairs
        //
        // Numeric namespaces imply the default/main namespace. Paths may be
        // strings or arrays of string paths to associate with the namespace.
    ],
]
```

When `debug` is true, it disables caching, enables debug mode, enables strict
variables, and enables auto reloading. The `assets_*` values are used to seed
the `TwigExtension` instance (assuming the router was found).

## ZendViewRendererFactory

- **Provides**: `Zend\Expressive\ZendView\ZendViewRenderer`
- **FactoryName**: `Zend\Expressive\ZendView\ZendViewRendererFactory`
- **Suggested Name**: `Zend\Expressive\Template\TemplateRendererInterface`
- **Requires**: no additional services are required.
    - `Zend\Expressive\Router\RouterInterface`, in order to inject the custom
      url helper implementation.
- **Optional**:
    - `config`, an array or `ArrayAccess` instance. This will be used to further
      configure the `ZendView` instance, specifically with the layout template
      name, entries for a `TemplateMapResolver`, and and template paths to
      inject.
    - `Zend\View\HelperPluginManager`; if present, will be used to inject the
      `PhpRenderer` instance.

It consumes the following `config` structure:

```php
'templates' => [
    'layout' => 'name of layout view to use, if any',
    'map'    => [
        // template => filename pairs
    ],
    'paths'  => [
        // namespace / path pairs
        //
        // Numeric namespaces imply the default/main namespace. Paths may be
        // strings or arrays of string paths to associate with the namespace.
    ],
]
```

When creating the `PhpRenderer` instance, it will inject it with a
`Zend\View\HelperPluginManager` instance (either pulled from the container, or
instantiated directly). It injects the helper plugin manager with custom url and
serverurl helpers, `Zend\Expressive\ZendView\UrlHelper` and
`Zend\Expressive\ZendView\ServerUrlHelper`, respetively.
