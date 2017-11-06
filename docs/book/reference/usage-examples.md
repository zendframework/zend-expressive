# Usage Examples

Below are several usage examples, covering a variety of ways of creating and
managing an application.

In all examples, the assumption is the following directory structure:

```
.
├── config
├── data
├── composer.json
├── public
│   └── index.php
├── src
└── vendor
```

We assume also that:

- You have installed zend-expressive per the [installation instructions](../index.md#installation).
- `public/` will be the document root of your application.
- Your own classes are under `src/` with the top-level namespace `App`,
  and you have configured [autoloading](https://getcomposer.org/doc/01-basic-usage.md#autoloading)
  in your `composer.json` for those classes (this should be done for you during
  installation).

> ## Using the built-in web server
>
> You can use the built-in web server to run the examples. Run:
>
> ```bash
> $ php -S 0.0.0.0:8080 -t public
> ```
>
> from the application root to start up a web server running on port 8080, and
> then browse to http://localhost:8080/. If you used the Expressive installer,
> the following is equivalent:
>
> ```bash
> $ composer run --timeout=0 serve
> ```

> ## Setting up autoloading for the Application namespace
>
> In your `composer.json` file, place the following:
>
> ```json
> "autoload": {
>     "psr-4": {
>         "Application\\": "src/"
>     }
> },
> ```
>
> Once done, run:
>
> ```bash
> $ composer dump-autoload
> ```

### Routing

As noted in the [Application documentation](../features/application.md#adding-routable-middleware),
routing is abstracted and can be accomplished by calling any of the following
methods:

- `route($path, $middleware, array $methods = null, $name = null)` to route to a
  path and match any HTTP method, multiple HTTP methods, or custom HTTP methods.
- `get($path, $middleware, $name = null)` to route to a path that will only
  respond to the GET HTTP method.
- `post($path, $middleware, $name = null)` to route to a path that will only
  respond to the POST HTTP method.
- `put($path, $middleware, $name = null)` to route to a path that will only
  respond to the PUT HTTP method.
- `patch($path, $middleware, $name = null)` to route to a path that will only
  respond to the PATCH HTTP method.
- `delete($path, $middleware, $name = null)` to route to a path that will only
  respond to the DELETE HTTP method.

All methods return a `Zend\Expressive\Router\Route` method, which allows you to
specify additional options to associate with the route (e.g., for specifying
criteria, default values to match, etc.).

As examples:

```php
// GET
// This demonstrates passing a middleware instance (assuming $helloWorld is
// valid middleware)
$app->get('/', $helloWorld);

// POST
// This example specifies the middleware as a service name instead of as
// actual executable middleware.
$app->post('/trackback', 'TrackBack');

// PUT
// This example shows operating on the returned route. In this case, it's adding
// regex tokens to restrict what values for {id} will match. (The tokens feature
// is specific to Aura.Router.)
$app->put('/post/{id}', 'ReplacePost')
    ->setOptions([
        'tokens' => ['id' => '\d+'],
    ]);

// PATCH
// This example builds on the one above. Expressive allows you to specify
// the same path for a route matching on a different HTTP method, and
// corresponding to different middleware.
$app->patch('/post/{id}', 'UpdatePost')
    ->setOptions([
        'tokens' => ['id' => '\d+'],
    ]);

// DELETE
$app->delete('/post/{id}', 'DeletePost')
    ->setOptions([
        'tokens' => ['id' => '\d+'],
    ]);

// Matching ALL HTTP methods
// If the underlying router supports matching any HTTP method, the following
// will do so. Note: FastRoute *requires* you to specify the HTTP methods
// allowed explicitly, and does not support wildcard routes. As such, the
// following example maps to the combination of HEAD, OPTIONS, GET, POST, PATCH,
// PUT, TRACE, and DELETE.
// Just like the previous examples, it returns a Route instance that you can
// further manipulate.
$app->route('/post/{id}', 'HandlePost')
    ->setOptions([
        'tokens' => ['id' => '\d+'],
    ]);

// Matching multiple HTTP methods
// You can pass an array of HTTP methods as a third argument to route(); in such
// cases, routing will match if any of the specified HTTP methods are provided.
$app->route('/post', 'HandlePostCollection', ['GET', 'POST']);

// Matching NO HTTP methods
// Pass an empty array to the HTTP methods. HEAD and OPTIONS will still be
// honored. (In FastRoute, GET is also honored.)
$app->route('/post', 'WillThisHandlePost', []);
```

Finally, if desired, you can create a `Zend\Expressive\Router\Route` instance
manually and pass it to `route()` as the sole argument:

```php
$route = new Route('/post', 'HandlePost', ['GET', 'POST']);
$route->setOptions($options);

$app->route($route);
```

## Hello World using a Container

Expressive works with [PSR-11 Container](https://github.com/php-fig/container),
though it's an optional feature. By default, if you use the `AppFactory`, it
will use [zend-servicemanager](https://github.com/zendframework/zend-servicemanager)
so long as that package is installed.

In the following example, we'll populate the container with our middleware, and
the application will pull it from there when matched.

Edit your `public/index.php` to read as follows:

```php
use Interop\Http\ServerMiddleware\DelegateInterface;
use Zend\Diactoros\Response\JsonResponse;
use Zend\Diactoros\Response\TextResponse;
use Zend\Expressive\AppFactory;
use Zend\ServiceManager\ServiceManager;

require __DIR__ . '/../vendor/autoload.php';

$container = new ServiceManager();

$container->setFactory('HelloWorld', function ($container) {
    return function ($request, DelegateInterface $delegate) {
        return new TextResponse('Hello, world!');
    };
});

$container->setFactory('Ping', function ($container) {
    return function ($request, DelegateInterface $delegate) {
        return new JsonResponse(['ack' => time()]);
    };
});

$app = AppFactory::create($container);
$app->get('/', 'HelloWorld');
$app->get('/ping', 'Ping');

$app->pipeRoutingMiddleware();
$app->pipeDispatchMiddleware();

$app->run();
```

In the example above, we pass our container to `AppFactory`. We could have also
done this instead:

```php
$app = AppFactory::create();
$container = $app->getContainer();
```

and then added our service definitions. We recommend passing the container to
the factory instead; if we ever change which container we use by default, your
code might not work!

The following two lines are the ones of interest:

```php
$app->get('/', 'HelloWorld');
$app->get('/ping', 'Ping');
```

These map the two paths to *service names* instead of callables. When routing
matches a path, it does the following:

- If the middleware provided when defining the route is callable, it uses it
  directly.
- If the middleware is a valid service name in the container, it pulls it from
  the container. *This is what happens in this example.*
- Finally, if no container is available, or the service name is not found in the
  container, it checks to see if it's a valid class name; if so, it instantiates
  and returns the class instance.

If you fire up your web server, you'll find that the `/` and `/ping` paths
continue to work.

One other approach you could take would be to define the application itself in
the container, and then pull it from there:

```php
$container->setFactory('Zend\Expressive\Application', function ($container) {
    $app = AppFactory::create($container);
    $app->get('/', 'HelloWorld');
    $app->get('/ping', 'Ping');
    return $app;
});

$app = $container->get('Zend\Expressive\Application');
$app->run();
```

This is a nice way to encapsulate the application creation. You could then
potentially move all service configuration to another file! (We already
[document an ApplicationFactory for exactly this scenario.](../features/container/factories.md#applicationfactory))

## Hello World using a Configuration-Driven Container

In the above example, we configured our middleware as services, and then passed
our service container to the application. At the end, we hinted that you could
potentially define the application itself as a service.

Expressive already provides a service factory for the application instance
to provide fine-grained control over your application. In this example, we'll
leverage it, defining our routes via configuration.

First, we're going to leverage zend-config to merge configuration files. This is
important, as it allows us to define local, environment-specific configuration
in files that we then can exclude from our repository. This practice ensures
that things like credentials are not accidentally published in a public
repository, and also provides a mechanism for slip-streaming in
configuration based on our environment (you might use different settings in
development than in production, after all!).

First, install zend-config and zend-stdlib:

```bash
$ composer require zendframework/zend-config zendframework/zend-stdlib
```

Now we can start creating our configuration files and container factories.

In `config/config.php`, place the following:

```php
<?php

use Zend\Stdlib\ArrayUtils;
use Zend\Stdlib\Glob;

$config = [];
// Load configuration from autoload path
foreach (Glob::glob('config/autoload/{{,*.}global,{,*.}local}.php', Glob::GLOB_BRACE) as $file) {
    $config = ArrayUtils::merge($config, include $file);
}

// Return an ArrayObject so we can inject the config as a service in Aura.Di
// and still use array checks like ``is_array``.
return new ArrayObject($config, ArrayObject::ARRAY_AS_PROPS);
```

In `config/container.php`, place the following:

```php
<?php

use Zend\ServiceManager\Config;
use Zend\ServiceManager\ServiceManager;

// Load configuration
$config = require __DIR__.'/config.php';

// Build container
$container = new ServiceManager();
(new Config($config['dependencies']))->configureServiceManager($container);

// Inject config
$container->setService('config', $config);

return $container;
```

In `config/autoload/dependencies.global.php`, place the following:

```php
<?php

use Zend\ServiceManager\Factory\InvokableFactory;

return [
    'dependencies' => [
        'invokables' => [
            \Application\HelloWorldAction::class => InvokableFactory::class,
            \Application\PingAction::class => InvokableFactory::class,
        ],
        'factories' => [
            \Zend\Expressive\Application::class => \Zend\Expressive\Container\ApplicationFactory::class,
        ],
    ]
];
```

In `config/autoload/routes.global.php`, place the following:

```php
<?php

return [
    'routes' => [
        [
            'path' => '/',
            'middleware' => \Application\HelloWorldAction::class,
            'allowed_methods' => ['GET'],
        ],
        [
            'path' => '/ping',
            'middleware' => \Application\PingAction::class,
            'allowed_methods' => ['GET'],
        ],
    ],
];
```

In `src/Application/HelloWorld.php`, place the following:

```php
<?php
namespace Application;

class HelloWorld
{
    public function __invoke($req, $res, $next)
    {
        $res->getBody()->write('Hello, world!');
        return $res;
    }
}
```

In `src/Application/Ping.php`, place the following:

```php
<?php
namespace Application;

use Zend\Diactoros\Response\JsonResponse;

class Ping
{
    public function __invoke($req, $res, $next)
    {
        return new JsonResponse(['ack' => time()]);
    }
}
```

After that’s done run:

```
composer dump-autoload
```

Finally, in `public/index.php`, place the following:

```php
<?php
// Change to the project root, to simplify resolving paths
chdir(dirname(__DIR__));

// Setup autoloading
require 'vendor/autoload.php';

$container = include 'config/container.php';
$app       = $container->get(Zend\Expressive\Application::class);
$app->run();
```

Notice that our index file now doesn't have any code related to setting up the
application any longer! All it does is setup autoloading, retrieve our service
container, pull the application from it, and run it. Our choices for container,
router, etc. are all abstracted, and if we change our mind later, this code will
continue to work.

Firing up the web server, you'll see the same responses as the previous
examples.

## Hybrid Container and Programmatic Creation

The above example may look a little daunting at first. By making everything
configuration-driven, you sometimes lose a sense for how the code all fits
together.

Fortunately, you can mix the two. Building on the example above, we'll add a new
route and middleware. Between pulling the application from the container and
calling `$app->run()`, add the following in your `public/index.php`:

```php
$app->post('/post', function ($request, \Interop\Http\ServerMiddleware\DelegateInterface $delegate) {
    return new \Zend\Diactoros\Response\TextResponse('IN POST!');
});
```

Note that we're using `post()` here; that means you'll have to use cURL, HTTPie,
Postman, or some other tool to test making a POST request to the path:

```bash
$ curl -X POST http://localhost:8080/post
```

You should see `IN POST!` for the response!

Using this approach, you can build re-usable applications that are
container-driven, and add one-off routes and middleware as needed.

### Using the container to register middleware

If you use a container to fetch your application instance, you have an
additional option for specifying middleware for the pipeline: configuration:

```php
<?php
return [
    'routes' => [
        [
            'path' => '/path/to/match',
            'middleware' => 'Middleware Service Name or Callable',
            'allowed_methods' => ['GET', 'POST', 'PATCH'],
            'options' => [
                'stuff' => 'to',
                'pass'  => 'to',
                'the'   => 'underlying router',
            ],
        ],
        // etc.
    ],
    'middleware_pipeline' => [
        // See specification below
    ],
];
```

The key to note is `middleware_pipeline`, which is an array of middlewares to
register in the pipeline; each will each be `pipe()`'d to the Application in the
order specified.

Each middleware specified must be in the following form:

```php
[
    // required:
    'middleware' => 'Name of middleware service, or a callable',
    // optional:
    'path'  => '/path/to/match',
    'priority' => 1, // Integer
]
```

Priority should be an integer, and follows the semantics of
[SplPriorityQueue](http://php.net/SplPriorityQueue): higher numbers indicate
higher priority (top of the queue; executed earliest), while lower numbers
indicated lower priority (bottom of the queue, executed last); *negative values
are low priority*. Items of the same priority are executed in the order in which
they are attached.

The default priority is 1, and this priority is used by the routing and dispatch
middleware. To indicate that middleware should execute *before* these, use a
priority higher than 1.

The above specification can be used for all middleware, with one exception:
registration of the *routing* and/or *dispatch* middleware that Expressive
provides. In these cases, use the following constants, which will be caught by
the factory and expanded:

- `Zend\Expressive\Application::ROUTING_MIDDLEWARE` for the
  routing middleware; this should always come before the dispatch middleware.
- `Zend\Expressive\Application::DISPATCH_MIDDLEWARE` for the
  dispatch middleware.

As an example:

```php
return [
    'middleware_pipeline' => [
        [ /* ... */ ],
        Zend\Expressive\Application::ROUTING_MIDDLEWARE,
        Zend\Expressive\Application::DISPATCH_MIDDLEWARE,
        [ /* ... */ ],
    ],
];
```

> #### Place routing middleware correctly
>
> If you are defining routes *and* defining other middleware for the pipeline,
> you **must** add the routing middleware. When you do so, make sure you put
> it at the appropriate location in the pipeline.
>
> Typically, you will place any middleware you want to execute on all requests
> prior to the routing middleware. This includes utilities for bootstrapping
> the application (such as injection of the `ServerUrlHelper`),
> utilities for injecting common response headers (such as CORS support), etc.
> Make sure these middleware specifications include the `priority` key, and that
> the value of this key is greater than 1.
>
> Use priority to shape the specific workflow you want for your middleware.

Middleware items may be any [valid middleware](../features/middleware-types.md),
including _arrays_ of middleware, which indicate a nested middleware pipeline;
these may even contain the routing and dispatch middleware constants:

```php
return [
    'middleware_pipeline' => [
        [ /* ... */ ],
        'routing' => [
            'middleware' => [
                Zend\Expressive\Application::ROUTING_MIDDLEWARE,
                /* ... middleware that introspects routing results ... */
                Zend\Expressive\Application::DISPATCH_MIDDLEWARE,
            ],
            'priority' => 1,
        ],
        [ /* ... */ ],
    ],
];
```

> #### Pipeline keys are ignored
>
> Keys in a `middleware_pipeline` specification are ignored. However, they can
> be useful when merging several configurations; if multiple configuration files
> specify the same key, then those entries will be merged. Be aware, however,
> that the `middleware` entry for each, since it is an indexed array, will
> merge arrays by appending; in other words, order will not be guaranteed within
> that array after merging. If order is critical, define a middleware spec with
> `priority` keys.

The path, if specified, can only be a literal path to match, and is typically
used for segregating middleware applications or applying rules to subsets of an
application that match a common path root.

## Segregating your application to a subpath

One benefit of a middleware-based application is the ability to compose
middleware and segregate them by paths. `Zend\Expressive\Application` is itself
middleware, allowing you to do exactly that if desired.

In the following example, we'll assume that `$api` and `$blog` are
`Zend\Expressive\Application` instances, and compose them into a
`Zend\Stratigility\MiddlewarePipe`.

```php
use Zend\Diactoros\Server;
use Zend\Diactoros\ServerRequestFactory;
use Zend\Stratigility\MiddlewarePipe;

require __DIR__ . '/../vendor/autoload.php';

$app = new MiddlewarePipe();
$app->pipe('/blog', $blog);
$app->pipe('/api', $api);

$server = Server::createServerFromRequest(
    $app,
    ServerRequestFactory::fromGlobals()
);
$server->listen();
```

You could also compose them in an `Application` instance, and utilize `run()`:

```php
$app = AppFactory::create();
$app->pipe('/blog', $blog);
$app->pipe('/api', $api);

$app->run();
```

This approach allows you to develop discrete applications and compose them
together to create a website.
