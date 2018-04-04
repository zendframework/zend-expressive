# Provided Factories

Expressive provides several factories compatible with
[PSR-11 Container](https://www.php-fig.org/psr/psr-11/) to facilitate
setting up common dependencies. The following is a list of provided
factories, what they will create, the suggested service name, and any
additional dependencies they may require.

## zend-expressive

The zend-expressive package ships `Zend\Expressive\ConfigProvider`, which
defines configuration that references each of these factories, using the
suggested names; this provider is registered by default when using the skeleton
application.

All factories, unless noted otherwise, are in the `Zend\Expressive\Container`
namespace, and define an `__invoke()` method that accepts an
`Psr\Container\ContainerInterface` instance as the sole argument.

### ApplicationFactory

- **Provides**: `Zend\Expressive\Application`
- **Suggested Name**: `Zend\Expressive\Application`
- **Requires**:
    - `Zend\Expressive\MiddlewareFactory`
    - `Zend\Expressive\ApplicationPipeline`, which should resolve to a
      `Zend\Stratigility\MiddlewarePipe` instance.
    - `Zend\Expressive\Router\RouteCollector`
    - `Zend\HttpHandlerRunner\RequestHandlerRunner`
- **Optional**: no optional services are used.

### ApplicationPipelineFactory

- **Provides**: a `Zend\Stratigility\MiddlewarePipe` for use with the
  `Application` instance.
- **Suggested Name**: `Zend\Expressive\ApplicationPipeline`
- **Requires**: no additional services are required.
- **Optional**: no optional services are used.

### EmitterFactory

- **Provides**: `Zend\HttpHandlerRunner\Emitter\EmitterInterface`
- **Suggested Name**: `Zend\HttpHandlerRunner\Emitter\EmitterInterface`
- **Requires**: no additional services are required.
- **Optional**: no optional services are used.

This factory creates an instance of
`Zend\HttpHandlerRunner\Emitter\EmitterStack`, pushing a
`Zend\HttpHandlerRunner\Emitter\SapiEmitter` to it.

### ErrorHandlerFactory

- **Provides**: `Zend\Stratigility\Middleware\ErrorHandler`
- **Suggested Name**: `Zend\Stratigility\Middleware\ErrorHandler`
- **Requires**:
    - `Psr\Http\Message\ResponseInterface`, which should resolve to a _callable_
      capable of producing a `ResponseInterface` instance (and not directly to an
      instance itself)
- **Optional**:
    - `Zend\Expressive\Middleware\ErrorResponseGenerator`. If not provided, the error
      handler will not compose an error response generator, making it largely
      useless other than to provide an empty response.

### ErrorResponseGeneratorFactory

- **Provides**: `Zend\Expressive\Middleware\ErrorResponseGenerator`
- **Suggested Name**: `Zend\Expressive\Middleware\ErrorResponseGenerator`
- **Requires**: no additional services are required.
- **Optional**:
    - `config`, an array or `ArrayAccess` instance. This will be used to seed the
      `ErrorResponseGenerator` instance with a template name to use for errors (see
      more below), and/or a "debug" flag value.
    - `Zend\Expressive\Template\TemplateRendererInterface`. If not provided, the
      error response generator will provide a plain text response instead of a
      templated one.

When the `config` service is present, the factory can utilize two values:

- `debug`, a flag indicating whether or not to provide debug information when
  creating an error response.
- `zend-expressive.error_handler.template_error`, a name of an alternate
  template to use (instead of the default represented in the
  `Zend\Expressive\Middleware\ErrorResponseGenerator::TEMPLATE_DEFAULT`
  constant).

As an example:

```php
'debug' => true,
'zend-expressive' => [
    'error_handler' => [
        'template_error' => 'name of error template',
    ],
],
```

### MiddlewareContainerFactory

- **Provides**: a `Zend\Expressive\MiddlewareContainer`
- **Suggested Name**: `Zend\Expressive\MiddlewareContainer`
- **Requires**: no additional services are required.
- **Optional**: no optional services are used.

This factory returns an instance of `Zend\Expressive\MiddlewareContainer`
injected with the container instance itself.

The `MiddlewareContainer` is a PSR-11 container itself, but ensures that
instances pulled are PSR-15 `MiddlewareInterface` instances:

- It decorates PSR-15 `RequestHandlerInterface` instances using `Zend\Stratigility\RequestHandlerMiddleware`.
- If a requested service is not in the underlying PSR-11 container, but the
  class exists, it will attempt to instantiate it directly.
- Any service retrieved that is not a `MiddlewareInterface` instance will result
  in an exception, ensuring that nothing invalid is piped or routed.

### MiddlewareFactoryFactory

- **Provides**: a `Zend\Expressive\MiddlewareFactory`
- **Suggested Name**: `Zend\Expressive\MiddlewareFactory`
- **Requires**:
    - `Zend\Expressive\MiddlewareContainer`
- **Optional**: no optional services are used.

The `MiddlewareFactory` is used by `Zend\Expressive\Application` to prepare
`$middleware` arguments to `pipe()`, `route()`, et al, ensuring they are
`MiddlewareInterface` implementations. It handles the following types:

- `MiddlewareInterface` types are considered valid always.
- `RequestHandlerInterface` types are decorated using `Zend\Stratigility\Middleware\RequestHandlerMiddleware`.
- `callable` types are decorated using `Zend\Stratigility\middleware()`.
- `string` types are decorated using a `Zend\Expressive\Middleware\LazyLoadingMiddleware`
  instance (which will also receive the `MiddlewareContainer.`)
- Or an `array` of any of the above types.

### NotFoundHandlerFactory

- **Provides**: `Zend\Expressive\Handler\NotFoundHandler`
- **Suggested Name**: `Zend\Expressive\Handler\NotFoundHandler`
- **Requires**:
    - `Psr\Http\Message\ResponseInterface`, which should resolve to a _callable_
      capable of producing a `ResponseInterface` instance (and not directly to an
      instance itself)
- **Optional**:
    - `config`, an array or `ArrayAccess` instance. This will be used to seed the
      `NotFoundHandler` instance with a template name to use.
    - `Zend\Expressive\Template\TemplateRendererInterface`. If not provided, the
      handler will provide a plain text response instead of a templated one.

When the `config` service is present, the factory can utilize two values:

- `zend-expressive.error_handler.template_404`, a name of an alternate
  template to use (instead of the default represented in the
  `Zend\Expressive\Delegate\NotFoundDelegate::TEMPLATE_DEFAULT` constant).

As an example:

```php
'zend-expressive' => [
    'error_handler' => [
        'template_404' => 'name of 404 template',
    ],
],
```

### RequestHandlerRunnerFactory

- **Provides**: `Zend\HttpHandler\RequestHandlerRunner`
- **Suggested Name**: `Zend\HttpHandler\RequestHandlerRunner`
- **Requires**:
    - `Zend\Expressive\ApplicationPipeline`, which should resolve to the
      `Zend\Stratigility\MiddlewarePipe` instance the `Application` will use.
    - `Zend\HttpHandlerRunner\Emitter\EmitterInterface`
    - `Psr\Http\Message\ServerRequestInterface`,  which should resolve to a
      _callable_ capable of producing a `ServerRequestInterface` instance (and
      not directly to an instance itself)
    - `Zend\Expressive\Response\ServerRequestErrorResponseGenerator`
- **Optional**: no optional services are used.

This factory generates the `RequestHandlerRunner` instance used by the
`Application` instance to "run" the application. It marshals a request instance,
passes it to the application pipeline to handle, and emits the returned
response. If an error occurs during request generation, it uses the
`ServerRequestErrorResponseGenerator` to generate the response to emit.

### ResponseFactoryFactory

- **Provides**: a PHP callable capable of producing
  `Psr\Http\Message\ResponseInterface` instances.
- **Suggested Name**: `Psr\Http\Message\ResponseInterface`
- **Requires**: no additional services are required.
- **Optional**: no optional services are used.

By default, this uses zend-diactoros to produce a response, and will raise an
exception if that package is not installed. You can provide an alternate factory
if you want to use an alternate PSR-7 implementation.

### ServerRequestErrorResponseGeneratorFactory

- **Provides**: `Zend\Expressive\Response\ServerRequestErrorResponseGenerator`
- **Suggested Name**: `Zend\Expressive\Response\ServerRequestErrorResponseGenerator`
- **Requires**:
    - `Psr\Http\Message\ResponseInterface`, which should resolve to a _callable_
      capable of producing a `ResponseInterface` instance (and not directly to an
      instance itself)
- **Optional**:
    - `config`, an array or `ArrayAccess` instance. This will be used to seed the
      `ErrorResponseGenerator` instance with a template name to use for errors (see
      more below), and/or a "debug" flag value.
    - `Zend\Expressive\Template\TemplateRendererInterface`. If not provided, the
      error response generator will provide a plain text response instead of a
      templated one.

When the `config` service is present, the factory can utilize two values:

- `debug`, a flag indicating whether or not to provide debug information when
  creating an error response.
- `zend-expressive.error_handler.template_error`, a name of an alternate
  template to use (instead of the default represented in the
  `Zend\Expressive\Middleware\ErrorResponseGenerator::TEMPLATE_DEFAULT`
  constant).

As an example:

```php
'debug' => true,
'zend-expressive' => [
    'error_handler' => [
        'template_error' => 'name of error template',
    ],
],
```

### ServerRequestFactoryFactory

- **Provides**: a PHP callable capable of producing
  `Psr\Http\Message\ServerRequestInterface` instances.
- **Suggested Name**: `Psr\Http\Message\ServerRequestInterface`
- **Requires**: no additional services are required.
- **Optional**: no optional services are used.

By default, this uses zend-diactoros to produce a request, and will raise an
exception if that package is not installed. You can provide an alternate factory
if you want to use an alternate PSR-7 implementation.

### StreamFactoryFactory

- **Provides**: a PHP callable capable of producing
  `Psr\Http\Message\StreamInterface` instances.
- **Suggested Name**: `Psr\Http\Message\StreamInterface`
- **Requires**: no additional services are required.
- **Optional**: no optional services are used.

By default, this uses zend-diactoros to produce a stream, and will raise an
exception if that package is not installed. You can provide an alternate factory
if you want to use an alternate PSR-7 implementation.

### WhoopsErrorResponseGeneratorFactory

- **Provides**: `Zend\Expressive\Middleware\WhoopsErrorResponseGenerator`
- **Suggested Name**: `Zend\Expressive\Middleware\ErrorResponseGenerator`
- **Requires**: `Zend\Expressive\Whoops` (see [WhoopsFactory](#whoopsfactory),
below)

### WhoopsFactory

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

### WhoopsPageHandlerFactory

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

## zend-expressive-router

The zend-expressive-router package ships `Zend\Expressive\Router\ConfigProvider`,
which defines configuration that references each of these factories, using the
suggested names; this provider is registered by default when using the skeleton
application.

Individual router implementation packages are expected to provide the
`Zend\Expressive\Router\RouterInterface` service.

All factories listed below are under the `Zend\Expressive\Router\Middleware`
namespace (unless otherwise specified), and define an `__invoke()` method that
accepts a `Psr\Container\ContainerInterface` instance as the sole argument.

### DispatchMiddlewareFactory

- **Provides**: `Zend\Expressive\Router\Middleware\DispatchMiddleware`
- **Suggested Name**: `Zend\Expressive\Router\Middleware\DispatchMiddleware`
- **Requires**: no additional services are required.
- **Optional**: no optional services are used.

### ImplicitHeadMiddlewareFactory

- **Provides**: `Zend\Expressive\Router\Middleware\ImplicitHeadMiddleware`
- **Suggested Name**: `Zend\Expressive\Router\Middleware\ImplicitHeadMiddleware`
- **Requires**:
    - `Zend\Expressive\Router\RouterInterface`
    - `Psr\Http\Message\StreamInterface`, which should resolve to a _callable_
      capable of producing a `StreamInterface` instance (and not directly to an
      instance itself)
- **Optional**: no optional services are used.

### ImplicitOptionsMiddlewareFactory

- **Provides**: `Zend\Expressive\Router\Middleware\ImplicitOptionsMiddleware`
- **Suggested Name**: `Zend\Expressive\Router\Middleware\ImplicitOptionsMiddleware`
- **Requires**:
    - `Psr\Http\Message\ResponseInterface`, which should resolve to a _callable_
      capable of producing a `ResponseInterface` instance (and not directly to an
      instance itself)
- **Optional**: no optional services are used.

### MethodNotAllowedMiddlewareFactory

- **Provides**: `Zend\Expressive\Router\Middleware\MethodNotAllowedMiddleware`
- **Suggested Name**: `Zend\Expressive\Router\Middleware\MethodNotAllowedMiddleware`
- **Requires**:
    - `Psr\Http\Message\ResponseInterface`, which should resolve to a _callable_
      capable of producing a `ResponseInterface` instance (and not directly to an
      instance itself)
- **Optional**: no optional services are used.

### RouteCollectorFactory

- **Provides**: `Zend\Expressive\Router\RouteCollector`
- **Suggested Name**: `Zend\Expressive\Router\RouteCollector`
- **Requires**:
    - `Zend\Expressive\Router\RouterInterface`
- **Optional**: no optional services are used.

### RouteMiddlewareFactory

- **Provides**: `Zend\Expressive\Router\Middleware\RouteMiddleware`
- **Suggested Name**: `Zend\Expressive\Router\Middleware\RouteMiddleware`
- **Requires**:
    - `Zend\Expressive\Router\RouterInterface`
- **Optional**: no optional services are used.

## Factories provided by template engine packages

The following factories are provided by individual template engine packages.
Generally speaking, these will be provided to your container configuration
during installation.

### PlatesRendererFactory

- **Provides**: `Zend\Expressive\Template\PlatesRenderer`
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

### TwigRendererFactory

- **Provides**: `Zend\Expressive\Template\TwigRenderer`
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

### ZendViewRendererFactory

- **Provides**: `Zend\Expressive\Template\ZendViewRenderer`
- **FactoryName**: `Zend\Expressive\ZendView\ZendViewRendererFactory`
- **Suggested Name**: `Zend\Expressive\Template\TemplateRendererInterface`
- **Requires**: no additional services are required.
- **Optional**:
    - `config`, an array or `ArrayAccess` instance. This will be used to further
      configure the `ZendView` instance, specifically with the layout template
      name, entries for a `TemplateMapResolver`, and and template paths to
      inject.
    - `Zend\View\Renderer\PhpRenderer`, in order to allow providing custom
      extensions and/or re-using an existing configuration; otherwise, a default
      instance is created.
    - `Zend\View\HelperPluginManager`; if present, will be used to inject the
      `PhpRenderer` instance; otherwise, a default instance is created.
    - `Zend\Expressive\Helper\UrlHelper`, in order to provide a URL helper
      compatible with zend-expressive-router. If you will not be generating
      URLs, this can be omitted.
    - `Zend\Expressive\Helper\ServerUrlHelper`, in order to provide a server URL
      helper (which provides the scheme and authority for a generated URL)
      compatible with zend-expressive-router. If you will not be generating
      URLs, this can be omitted.

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
`Zend\Expressive\ZendView\ServerUrlHelper`, respectively.
