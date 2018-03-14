# How can I specify a route-specific middleware pipeline?

Sometimes you may want to use a middleware pipeline only if a particular route
is matched. As an example, for an API resource, you might want to:

- check for authentication credentials
- check for authorization for the selected action
- parse the incoming body
- validate the parsed body parameters

*before* you actually execute the selected middleware. The above might each be
encapsulated as discrete middleware, but should be executed within the routed
middleware's context.

You can accomplish this in one of two ways:

- Have your middleware service resolve to a `MiddlewarePipe` instance that
  composes the various middlewares.
- Specify an array of middlewares (either as actual instances, or as container
  service names); this effectively creates and returns a `MiddlewarePipe`.

## Resolving to a MiddlewarePipe

You can do this programmatically within a container factory, assuming you are
using a container that supports factories.

```php
use Psr\Container\ContainerInterface;
use Zend\Expressive\MiddlewareFactory;
use Zend\Stratigility\MiddlewarePipe;

class ApiResourcePipelineFactory
{
    public function __invoke(ContainerInterface $container)
    {
        $factory = $container->get(MiddlewareFactory::class);
        $pipeline = new MiddlewarePipe();

        // These correspond to the bullet points above
        $pipeline->pipe($factory->prepare(AuthenticationMiddleware::class));
        $pipeline->pipe($factory->prepare(AuthorizationMiddleware::class));
        $pipeline->pipe($factory->prepare(BodyParsingMiddleware::class));
        $pipeline->pipe($factory->prepare(ValidationMiddleware::class));

        // This is the actual handler you're routing to:
        $pipeline->pipe($factory->prepare(ApiResource::class));

        return $pipeline;
    }
}
```

> `$factory->prepare()` is used here to allow lazy-loading each middleware and
> handler. If we instead pulled each class from the container directly, each would
> be created, even if it was not ultimately executed.

This gives you full control over the creation of the pipeline. You would,
however, need to ensure that you map the middleware to the pipeline factory when
setting up your container configuration.

One alternative when using zend-servicemanager is to use a [delegator factory](https://docs.zendframework.com/zend-servicemanager/delegators/).
Delegator factories allow you to decorate the primary factory used to create the
middleware in order to change the instance or return an alternate instance. In
this case, we'd do the latter. The following is an example:

```php
use Psr\Container\ContainerInterface;
use Zend\Expressive\MiddlewareFactory;
use Zend\ServiceManager\DelegatorFactoryInterface;
use Zend\ServiceManager\ServiceLocatorInterface;
use Zend\Stratigility\MiddlewarePipe;

class ApiResourcePipelineDelegatorFactory implements DelegatorFactoryInterface
{
    public function __invoke(
        ContainerInterface $container,
        $name,
        callable $callback,
        array $options = null
    ) : MiddlewarePipe {
        $factory = $container->get(MiddlewareFactory::class);
        $pipeline = new MiddlewarePipe();

        // These correspond to the bullet points above
        $pipeline->pipe($factory->prepare(AuthenticationMiddleware::class));
        $pipeline->pipe($factory->prepare(AuthorizationMiddleware::class));
        $pipeline->pipe($factory->prepare(BodyParsingMiddleware::class));
        $pipeline->pipe($factory->prepare(ValidationMiddleware::class));

        // This is the actual handler you're routing to.
        $pipeline->pipe($callback());

        return $pipeline;
    }
}
```

When configuring the container, you'd do something like the following:

```php
return [
    'dependencies' => [
        'factories' => [
            AuthenticationMiddleware::class => '...',
            AuthorizationMiddleware::class => '...',
            BodyParsingMiddleware::class => '...',
            ValidationMiddleware::class => '...',
            ApiResource::class => '...',
        ],
        'delegators' => [
            ApiResource::class => [
                ApiResourcePipelineDelegatorFactory::class,
            ],
        ],
    ],
];
```

This approach allows you to cleanly separate the factory for your middleware
from the pipeline you want to compose it in, and allows you to re-use the
pipeline creation across multiple middleware if desired.

## Middleware Arrays

If you'd rather not create a factory for each such middleware, the other option
is to use arrays of middlewares when routing.

```php
$app->route('/api/resource[/{id:[a-f0-9]{32}}]', [
    AuthenticationMiddleware::class,
    AuthorizationMiddleware::class,
    BodyParsingMiddleware::class,
    ValidationMiddleware::class,
    ApiResource::class,
], ['GET', 'POST', 'PATCH', 'DELETE'], 'api-resource');
```

When either of these approaches are used, the individual middleware listed
**MUST** be one of the following:

- an instance of `Psr\Http\Middleware\MiddlewareInterface`;
- a callable middleware (will be decorated using `Zend\Stratigility\middleware()`);
- a service name of middleware available in the container;
- a fully qualified class name of a directly instantiable (no constructor
  arguments) middleware class.

This approach is essentially equivalent to creating a factory that returns a
middleware pipeline.
