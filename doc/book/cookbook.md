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

## How can I set custom 404 page handling?

In some cases, you may want to handle 404 errors separately from the
[final handler](../error-handling.md). This can be done by registering
middleware that operates late &mdash; specifically, after the routing
middleware. Such middleware will be executed if no other middleware has
executed, and/or when all other middleware calls `return $next()`
without returning a response. Such situations typically mean that no middleware
was able to complete the request.

Your 404 handler can take one of two approaches:

- It can set the response status and call `$next()` with an error condition. In
  such a case, the final handler *will* likely be executed, but will have an
  explicit 404 status to work with.
- It can create and return a 404 response itself.

### Calling next with an error condition

In the first approach, the `NotFound` middleware can be as simple as this:

```php
namespace Application;

class NotFound
{
    public function __invoke($req, $res, $next)
    {
        // Other things can be done here; e.g., logging
        return $next($req, $res->withStatus(404), 'Page Not Found');
    }
}
```

This example uses the third, optional argument to `$next()`, which is an error
condition. Internally, the final handler will typically see this, and return an
error page of some sort. Since we set the response status, and it's an error
status code, that status code will be used in the generated response.

The `TemplatedErrorHandler` will use the error template in this particular case,
so you will likely need to make some accommodations for 404 responses in that
template if you choose this approach.

### 404 Middleware

In the second approach, the `NotFound` middleware will return a full response.
In our example here, we will render a specific template, and use this to seed
and return a response.

```php
namespace Application;

use Zend\Expressive\Template\TemplateRendererInterface;

class NotFound
{
    private $renderer;

    public function __construct(TemplateRendererInterface $renderer)
    {
        $this->renderer = $renderer;
    }

    public function __invoke($req, $res, $next)
    {
        // other things can be done here; e.g., logging
        // Now set the response status and write to the body
        $response = $res->withStatus(404);
        $response->getBody()->write($this->renderer->render('error::not-found'));
        return $response;
    }
}
```

This approach allows you to have an application-specific workflow for 404 errors
that does not rely on the final handler.

### Registering custom 404 handlers

We can register either `Application\NotFound` class above as service in the
[service container](../container/intro.md). In the case of the second approach,
you would also need to provide a factory for creating the middleware (to ensure
you inject the template renderer).

From there, you still need to register the middleware. This middleware is not
routed, and thus needs to be piped to the application instance. You can do this
via either configuration, or manually.

To do this via configuration, add an entry under the `post_routing` key of the
`middleware_pipeline` configuration:

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

The above example assumes you are using the `ApplicationFactory` and/or the
Expressive skeleton to manage your application instantiation and configuration.

To manually add the middleware, you will need to pipe it to the application
instance:

```php
$app->pipe($container->get('Application\NotFound'));
```

This must be done *after*:

- calling `$app->pipeRoutingMiddleware()`, **OR**
- calling any method that injects routed middleware (`get()`, `post()`,
  `route()`, etc.), **OR**
- pulling the `Application` instance from the service container (assuming you
  used the `ApplicationFactory`).

This is to ensure that the `NotFound` middleware executes *after* any routed
middleware, as you only want it to execute if no routed middleware was selected.
