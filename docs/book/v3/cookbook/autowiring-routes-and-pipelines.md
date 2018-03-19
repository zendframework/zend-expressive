# How can I autowire routes and pipelines?

Sometimes you may find you'd like to keep route definitions close to the
handlers and middleware they will invoke. This is particularly important if you
want to re-use a module or library in another project.

In this recipe, we'll demonstrate two mechanisms for doing so. One is a built-in
[delegator factory](../features/container/delegator-factories.md), and the other
is a custom delegator factory.

## ApplicationConfigInjectionDelegator

Expressive ships with the class `Zend\Expressive\Container\ApplicationConfigInjectionDelegator`,
which can be used as a delegator factory for the `Zend\Expressive\Application`
class in order to automate piping of pipeline middleware and routing to request
handlers and middleware.

The delegator factory looks for configuration that looks like the following:

```php
return [
    'pipeline_middleware' => [
        [
            // required:
            'middleware' => 'Middleware service or pipeline',
            // optional:
            'path'  => '/path/to/match', // for path-segregated middleware
            'priority' => 1,             // integer; to ensure specific order
        ]
    ],
    'routes' => [
        [
            'path' => '/path/to/match',
            'middleware' => 'Middleware service or pipeline',
            'allowed_methods' => ['GET', 'POST', 'PATCH'],
            'name' => 'route.name',
            'options' => [
                'stuff' => 'to',
                'pass'  => 'to',
                'the'   => 'underlying router',
            ],
        ],
        'another.route.name' => [
            'path' => '/another/path/to/match',
            'middleware' => 'Middleware service or pipeline',
            'allowed_methods' => ['GET', 'POST'],
            'options' => [
                'more'    => 'router',
                'options' => 'here',
            ],
        ],
    ],
];
```

This configuration may be placed at the application level, in a file under
`config/autoload/`, or within a module's `ConfigProvider` class. For details on
what values are accepted, see below.

In order to enable the delegator factory, you will need to define the following
service configuration somewhere, either at the application level in a
`config/autoload/` file, or within a module-specific `ConfigProvider` class:

```php
return [
    'dependencies' => [
        'delegators' => [
            \Zend\Expressive\Application::class => [
                \Zend\Expressive\Container\ApplicationConfigInjectionDelegator::class,
            ],
        ],
    ],
];
```

### Pipeline middleware

Pipeline middleware are each described as an associative array, with the
following keys:

- `middleware` (**required**, string or array): the value should be a middleware
  service name, or an array of service names (in which case a `MiddlewarePipe`
  will be created and piped).
- `path` (optional, string): if you wish to path-segregate the middleware, provide a
  literal path prefix that must be matched in order to dispatch the given
  middleware.
- `priority` (optional, integer): The elements in the `pipeline_middleware`
  section are piped to the application in the order in which they are discovered
  &mdash; which could have ramifications if multiple components and/or modules
  provide pipeline middleware. If you wish to force a certain order, you may use
  the `priority` to do so. Higher value integers are piped first, lower value
  (including _negative_ values), last. If two middleware use the same priority,
  they will be piped in the order discovered.

### Routed middleware

Routed middleware are also each described as an associative array, using the
following keys:

- `path` (**required**, string): the path specification to match; this will be
  dependent on the router implementation you use.
- `middleware` (**required**, string or array): the value should be a middleware
  service name, or an array of service names (in which case a `MiddlewarePipe`
  will be created and piped).
- `allowed_methods` (optional, array or value of `Zend\Expressive\Route\HTTP_METHOD_ANY):
  the HTTP methods allowed for the route. If this is omitted, the assumption is
  any method is allowed.
- `name` (optional, string): the name of the route, if any; this can be used
  later to generate a URI based on the route, and must be unique. The name may
  also be set using a string key in the routes configuration array. If both are
  set the name assigned in the spec will be used.
- `options` (optional, array): any options to provide to the generated route.
  These might be default values or constraints, depending on the router
  implementation.

## Custom delegator factories

As outlined in the introduction to this recipe, we can also create our own
custom delegator factories in order to inject pipeline or routed middleware.
Unlike the above solution, the solution we will outline here will exercise the
`Zend\Expressive\Application` API in order to populate it.

First, we'll create the class `App\Factory\PipelineAndRoutesDelegator`, with
the following contents:

```php
<?php

namespace App\Factory;

use App\Handler;
use Psr\Container\ContainerInterface;
use Zend\Expressive\Application;
use Zend\Expressive\Handler\NotFoundHandler;
use Zend\Expressive\Helper\ServerUrlMiddleware;
use Zend\Expressive\Helper\UrlHelperMiddleware;
use Zend\Expressive\Router\Middleware\DispatchMiddleware;
use Zend\Expressive\Router\Middleware\ImplicitHeadMiddleware;
use Zend\Expressive\Router\Middleware\ImplicitOptionsMiddleware;
use Zend\Expressive\Router\Middleware\MethodNotAllowedMiddleware;
use Zend\Expressive\Router\Middleware\RouteMiddleware;
use Zend\Stratigility\Middleware\ErrorHandler;

class PipelineAndRoutesDelegator
{
    public function __invoke(
        ContainerInterface $container,
        string $serviceName,
        callable $callback
    ) : Application {
        /** @var $app Application */
        $app = $callback();

        // Setup pipeline:
        $app->pipe(ErrorHandler::class);
        $app->pipe(ServerUrlMiddleware::class);
        $app->pipe(RouteMiddleware::class);
        $app->pipe(ImplicitHeadMiddleware::class);
        $app->pipe(ImplicitOptionsMiddleware::class);
        $app->pipe(MethodNotAllowedMiddleware::class);
        $app->pipe(UrlHelperMiddleware::class);
        $app->pipe(DispatchMiddleware::class);
        $app->pipe(NotFoundHandler::class);

        // Setup routes:
        $app->get('/', Handler\HomePageHandler::class, 'home');
        $app->get('/api/ping', Handler\PingHandler::class, 'api.ping');

        return $app;
    }
}
```

> ### Where to put the factory
>
> You will place the factory class in one of the following locations:
>
> - `src/App/Factory/PipelineAndRoutesDelegator.php` if using the default, flat,
>   application structure.
> - `src/App/src/Factory/PipelineAndRoutesDelegator.php` if using the
>   recommended, modular, application structure.

Once you've created this, edit the class `App\ConfigProvider`; in it, we'll
update the `getDependencies()` method to add the delegator factory:

```php
public function getDependencies()
{
    return [
        /* . . . */
        'delegators' => [
            \Zend\Expressive\Application::class => [
                Factory\PipelineAndRoutesDelegator::class,
            ],
        ],
    ];
}
```

> ### Where is the ConfigProvider class?
>
> The `ConfigProvider` class is in one of the following locations:
>
> - `src/App/ConfigProvider.php` if using the default, flat, application
>   structure.
> - `src/App/src/ConfigProvider.php` using the recommended, modular, application
>   structure.

> ### Why is an array assigned?
>
> As noted above in the description of delegator factories, since each delegator
> factory returns an instance, you can nest multiple delegator factories in
> order to shape initialization of a service. As such, they are assigned as an
> _array_ to the service.

If you're paying careful attention to this example, it essentially replaces
both `config/pipeline.php` and `config/routes.php`! If you were to update those
files to remove the default pipeline and routes, you should find that reloading
your application returns the exact same results!

## Caution: pipelines

Using delegator factories is a nice way to keep your routing and pipeline
configuration close to the modules in which they are defined. However, there is
a caveat: you likely should **not** register pipeline middleware in a delegator
factory _other than within your root application module_.

The reason for this is simple: pipelines are linear, and specific to your
application. If one module pipes in middleware, there's no guarantee it will be
piped before or after your main pipeline, and no way to pipe the middleware at a
position in the middle of the pipeline!

As such:

- Use a `config/pipeline.php` file for your pipeline, **OR**
- Ensure you only define the pipeline in a **single** delegator factory on your
  `Application` instance.

## Caution: third-party, distributed modules

If you are developing a module to distribute as a package via
[Composer](https://getcomposer.org/), **you should not autowire any delegator
factories that inject pipeline middleware or routes in the `Application`**.

Why?

As noted in the above section, pipelines should be created exactly once, at
the application level. Registering pipeline middleware within a distributable
package will very likely not have the intended consequences.

If you ship with pipeline middleware, we suggest that you:

- Document the middleware, and where you anticipate it being used in the
  middleware pipeline.
- Document how to add the middleware service to dependency configuration, or
  provide the dependency configuration via your module's `ConfigProvider`.

With regards to routes, there are other considerations:

- Routes defined by the package might conflict with the application, or with
  other packages used by the application.

- Routing definitions are typically highly specific to the router implementation
  in use. As an example, each of the currently supported router implementations
  has a different syntax for placeholders:

    - `/user/:id` + "constraints" configuration to define constraints (zend-router)
    - `/user/{id}` + "tokens" configuration to define constraints (Aura.Router)
    - `/user/{id:\d+}` (FastRoute)

- Your application may have specific routing considerations or design.

You could, of course, detect what router is in use, and provide routing for each
known, supported router implementation within your delegator factory. We even
recommend doing exactly that. However, we note that such an approach does not
solve the other two points above.

However, we still recommend _shipping_ a delegator factory that would register
your routes, since routes *are* often a part of module design; just **do not
autowire** that delegator factory. This way, end-users who *can* use the
defaults do not need to cut-and-paste routing definitions from your
documentation into their own applications; they will instead opt-in to your
delegator factory by wiring it into their own configuration.

## Synopsis

- We recommend using delegator factories for the purpose of autowiring routes,
  and, with caveats, pipeline middleware:
    - The pipeline should be created exactly once, so calls to `pipe()` should
      occur in exactly _one_ delegator factory.
- Distributable packages should create a delegator factory for _routes only_,
  but _should not_ register the delegator factory by default.
