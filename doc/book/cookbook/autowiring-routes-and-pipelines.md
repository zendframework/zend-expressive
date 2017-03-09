# How can I autowire routes and pipelines?

Expressive 2.0 switches to _programmatic_ piplines and routes, versus
_configuration-driven_ pipelines and routing. One drawback is that with
configuration-driven approaches, users could provide configuration via a module
`ConfigProvider`, and automatically expose new pipeline middleware or routes;
with a programmatic approach, this is no longer possible.

Or is it?

## Delegator Factories

One possibility, available since the Expressive 2.X skeleton application, is to
use _delegator factories_ on the `Zend\Expressive\Application` instance in order
to inject these items.

A _delegator factory_ is a factory that _delegates_ creation of an instance to a
callback, and then operates on that instance for the purpose of altering the
instance or providing a replacement (e.g., a decorator or proxy). The delegate
callback usually wraps a service factory, or, because delegator factories
_also_ return an instance, additional delegator factories. As such, you assign
delegator _factories_, plural, to instances, allowing multiple delegator
factories to intercept processing of the service initialization.

For the purposes of this particular example, we will use delegator factories to
both _pipe_ middleware as well as _route_ middleware.

To demonstrate, we'll take the default pipeline and routing from the skeleton
application, and provide it via a delegator factory instead.

First, we'll create the class `App\Factory\PipelineAndRoutesDelegator`, with
the following contents:

```php
<?php

namespace App\Factory;

use App\Action;
use Psr\Container\ContainerInterface;
use Zend\Expressive\Application;
use Zend\Expressive\Helper\ServerUrlMiddleware;
use Zend\Expressive\Helper\UrlHelperMiddleware;
use Zend\Expressive\Middleware\ImplicitHeadMiddleware;
use Zend\Expressive\Middleware\ImplicitOptionsMiddleware;
use Zend\Expressive\Middleware\NotFoundHandler;
use Zend\Stratigility\Middleware\ErrorHandler;

class PipelineAndRoutesDelegator
{
    /**
     * @param ContainerInterface $container
     * @param string $serviceName Name of the service being created.
     * @param callable $callback Creates and returns the service.
     * @return Application
     */
    public function __invoke(ContainerInterface $container, $serviceName, callable $callback)
    {
        /** @var $app Application */
        $app = $callback();

        // Setup pipeline:
        $app->pipe(ErrorHandler::class);
        $app->pipe(ServerUrlMiddleware::class);
        $app->pipeRoutingMiddleware();
        $app->pipe(ImplicitHeadMiddleware::class);
        $app->pipe(ImplicitOptionsMiddleware::class);
        $app->pipe(UrlHelperMiddleware::class);
        $app->pipeDispatchMiddleware();
        $app->pipe(NotFoundHandler::class);

        // Setup routes:
        $app->get('/', Action\HomePageAction::class, 'home');
        $app->get('/api/ping', Action\PingAction::class, 'api.ping');

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

Once you've done this, you can remove:

- `config/pipeline.php`
- `config/routes.php`
- The following lines from `public/index.php`:

  ```php
  // Import programmatic/declarative middleware pipeline and routing
  // configuration statements
  require 'config/pipeline.php';
  require 'config/routes.php';
  ```

If you reload your application at this point, you should see that everything
continues to work as expected!

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
