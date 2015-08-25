# Cookbook

Below are several common scenarios.

## How can I prepend a common path to all my routes?

You may have multiple middlewares providing their own functionality:

```php
$middleware1 = new UserMiddleware();
$middleware2 = new ProjectMiddleware();

$app = AppFactory::create();
$app->pipe($middleware1);
$app->pipe($middleware2);

$app->run();
```

Let's assume the above represents an API.

As your application progresses, you may have a mixture of different content, and now want to have
the above segregated under the path `/api`.

This is essentially the same problem as addressed in the
["Segregating your application to a subpath"](usage-examples.md#segregating-your-application-to-a-subpath) example.

To accomplish it:

- Create a new application.
- Pipe the previous application to the new one, under the path `/api`.

```php
$middleware1 = new UserMiddleware();
$middleware2 = new ProjectMiddleware();

$api = AppFactory::create();
$api->pipe($middleware1);
$api->pipe($middleware2);

$app = AppFactory::create();
$app->pipe('/api', $api);

$app->run();
```

The above works, because every `Application` instance is itself middleware, and, more specifically,
an instance of [Stratigility's `MiddlewarePipe`](https://github.com/zendframework/zend-stratigility/blob/master/doc/book/middleware.md),
which provides the ability to compose middleware.

## How can I specify a route-specific middleware pipeline?

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

### Resolving to a MiddlewarePipe

You can do this programmatically within a container factory, assuming you are
using a container that supports factories.

```php
use Interop\Container\ContainerInterface;
use Zend\Stratigility\MiddlewarePipe;

class ApiResourcePipelineFactory
{
    public function __invoke(ContainerInterface $container)
    {
        $pipeline = new MiddlewarePipe();

        // These correspond to the bullet points above
        $pipeline->pipe($container->get('AuthenticationMiddleware)');
        $pipeline->pipe($container->get('AuthorizationMiddleware)');
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

One alternative when using zend-servicemanager is to use a [delegator factory](http://framework.zend.com/manual/current/en/modules/zend.service-manager.delegator-factories.html).
Delegator factories allow you to decorate the primary factory used to create the
middleware in order to change the instance or return an alternate instance. In
this case, we'd do the latter. The following is an example:

```php
use Zend\ServiceManager\DelegatorFactoryInterface;
use Zend\ServiceManager\ServiceLocatorInterface;
use Zend\Stratigility\MiddlewarePipe;

class ApiResourcePipelineDelegatorFactory
{
    public function createDelegatorWithName(
        ServiceLocatorInterface $container,
        $name,
        $requestedName,
        $callback
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
        'delegator_factories' => [
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

### Middleware Arrays

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

- a callable middleware;
- a service name of middleware available in the container;
- a fully qualified class name of a directly instantiable (no constructor
  arguments) middleware class.

This approach is essentially equivalent to creating a factory that returns a
middleware pipeline.

### What about pre/post pipeline middleware?

What if you want to do this with pre- or post-pipeline middleware? The answer is
that the syntax is exactly the same!

```php
return [
    'middleware_pipeline' => [
        'pre_routing' => [
            [
                'path' => '/api',
                'middleware' => [
                    'AuthenticationMiddleware',
                    'AuthorizationMiddleware',
                    'BodyParsingMiddleware',
                    'ValidationMiddleware',
                ],
            ],
        ],
    ],
];
```

## How can I set 404 page?

We can set 404 page with create new Middleware that set 404 status from response object.

Let's create a `NotFound` middleware:

```php
namespace Application;

use Zend\Expressive\Template\TemplateInterface;

class NotFound
{
    public function __invoke($req, $res, $next)
    {
        return $next($req, $res->withStatus(404), 'Page Not Found');
    }
}
```

> We can register the `Application\NotFound` instance as a service in [service container](https://github.com/zendframework/zend-expressive/blob/master/doc/book/container/intro.md).

Now, we can configure the `middleware_pipeline` under `post_routing`:

```php
'middleware_pipeline' => [
    'pre_routing' => [
        [
            //...
        ],

    ],
    'post_routing' => [
        [
            'middleware' => 'Application\NotFound',
        ],
    ],
],
```

The other thing that we can do is programmatically pipe in `public/index.php`:

```php
$app->pipe($services->get('Application\NotFound'));
$app->run();
```
