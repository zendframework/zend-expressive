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
use Zend\Stratigility\MiddlewarePipe;

class ApiResourcePipelineFactory
{
    public function __invoke(ContainerInterface $container)
    {
        $pipeline = new MiddlewarePipe();

        // These correspond to the bullet points above
        $pipeline->pipe($container->get('AuthenticationMiddleware'));
        $pipeline->pipe($container->get('AuthorizationMiddleware'));
        $pipeline->pipe($container->get('BodyParsingMiddleware'));
        $pipeline->pipe($container->get('ValidationMiddleware'));

        // This is the actual middleware you're routing to.
        $pipeline->pipe($container->get('ApiResource'));

        return $pipeline;
    }
}
```

This gives you full control over the creation of the pipeline. You would,
however, need to ensure that you map the middleware to the pipeline factory when
setting up your container configuration.

One alternative when using zend-servicemanager is to use a [delegator factory](https://docs.zendframework.com/zend-servicemanager/delegators/).
Delegator factories allow you to decorate the primary factory used to create the
middleware in order to change the instance or return an alternate instance. In
this case, we'd do the latter. The following is an example:

```php
use Psr\Container\ContainerInterface;
use Zend\ServiceManager\DelegatorFactoryInterface;
use Zend\ServiceManager\ServiceLocatorInterface;
use Zend\Stratigility\MiddlewarePipe;

class ApiResourcePipelineDelegatorFactory implements DelegatorFactoryInterface
{
    /**
     * zend-servicemanager v3 support
     */
    public function __invoke(
        ContainerInterface $container,
        $name,
        callable $callback,
        array $options = null
    ) {
        $pipeline = new MiddlewarePipe();

        // These correspond to the bullet points above
        $pipeline->pipe($container->get('AuthenticationMiddleware'));
        $pipeline->pipe($container->get('AuthorizationMiddleware'));
        $pipeline->pipe($container->get('BodyParsingMiddleware'));
        $pipeline->pipe($container->get('ValidationMiddleware'));

        // This is the actual middleware you're routing to.
        $pipeline->pipe($callback());

        return $pipeline;
    }

    /**
     * zend-servicemanager v2 support
     */
    public function createDelegatorWithName(
        ServiceLocatorInterface $container,
        $name,
        $requestedName,
        $callback
    ) {
        return $this($container, $name, $callback);
    }
}
```

When configuring the container, you'd do something like the following:

```php
return [
    'dependencies' => [
        'factories' => [
            'AuthenticationMiddleware' => '...',
            'AuthorizationMiddleware' => '...',
            'BodyParsingMiddleware' => '...',
            'ValidationMiddleware' => '...',
            'ApiResourceMiddleware' => '...',
        ],
        'delegators' => [
            'ApiResourceMiddleware' => [
                'ApiResourcePipelineDelegatorFactory',
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
is to use arrays of middlewares in your configuration or when routing manually.

Via configuration looks like this:

```php
return [
    'routes' => [
        [
            'name' => 'api-resource',
            'path' => '/api/resource[/{id:[a-f0-9]{32}}]',
            'allowed_methods' => ['GET', 'POST', 'PATCH', 'DELETE'],
            'middleware' => [
                'AuthenticationMiddleware',
                'AuthorizationMiddleware',
                'BodyParsingMiddleware',
                'ValidationMiddleware',
                'ApiResourceMiddleware',
            ],
        ],
    ],
];
```

Manual routing looks like this:

```php
$app->route('/api/resource[/{id:[a-f0-9]{32}}]', [
    'AuthenticationMiddleware',
    'AuthorizationMiddleware',
    'BodyParsingMiddleware',
    'ValidationMiddleware',
    'ApiResourceMiddleware',
], ['GET', 'POST', 'PATCH', 'DELETE'], 'api-resource');
```

When either of these approaches are used, the individual middleware listed
**MUST** be one of the following:

- an instance of `Interop\Http\ServerMiddleware\MiddlewareInterface`;
- a callable middleware (will be decorated as interop middleware);
- a service name of middleware available in the container;
- a fully qualified class name of a directly instantiable (no constructor
  arguments) middleware class.

This approach is essentially equivalent to creating a factory that returns a
middleware pipeline.

## What about pipeline middleware configuration?

What if you want to do this with your pipeline middleware configuration? The
answer is that the syntax is exactly the same!

```php
return [
    'middleware_pipeline' => [
        'api' => [
            'path' => '/api',
            'middleware' => [
                'AuthenticationMiddleware',
                'AuthorizationMiddleware',
                'BodyParsingMiddleware',
                'ValidationMiddleware',
            ],
            'priority' => 100,
        ],
    ],
];
```
