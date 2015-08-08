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

- You have installed zend-expressive per the installation instructions.
- `public/` will be the document root of your application.
- Your own classes are under `src/` with the top-level namespace `Application`,
  and you have configured autoloading in your `composer.json` for those classes.

> ## Using the built-in web server
>
> You can use the built-in web server to run the examples. Run:
>
> ```bash
> $ php -S 0:8080 -t public/
> ```
>
> from the application root to start up a web server running on port 8080, and
> then browse to http://localhost:8080

## Hello World

In this example, we assume the defaults are fine, and simply grab an application
instance, add routes to it, and run it.

Put the code below in your `public/index.php`:

```php
use Zend\Diactoros\Response\JsonResponse;
use Zend\Expressive\AppFactory;

require __DIR__ . '/../vendor/autoload.php';

$app = AppFactory::create();

$app->get('/', function ($req, $res, $next) {
    $res->write('Hello, world!');
    return $res;
});

$app->get('/ping', function ($req, $res, $next) {
    return new JsonResponse(['ack' => time()]);
});

$app->run();
```

You should be able to access the site at the paths `/` and `/ping` at this
point. If you try any other path, you should receive a 404 response.

`$app` above will be an instance of `Zend\Expressive\Application`. That class
has a few dependencies it requires, however, which may or may not be of interest
to you at first. As such, `AppFactory` marshals some sane defaults for you to
get you on your way.

### Routing

By default, zend-expressive uses [Aura.Router](https://github.com/auraphp/Aura.Router).
We also provide an implementation that consumes [FastRoute](https://github.com/nikic/FastRoute),
and have plans for others.

In order to abstract routing, we have created a `RouterInterface`; this defines
two methods:

```php
namespace Zend\Expressive\Router;

use Psr\Http\Message\ServerRequestInterface;

interface RouterInterface
{
    public function addRoute(Route $route);
    public function match(ServerRequestInterface $request);
}
```

The `Route` instance is an abstraction that encapsulates the various required
routing information for any given route, as well as any optional arguments you
wish to pass on to the underlying routing implementation. A `Route` instance
requires a path, middleware (as either a callable or a service name), and
optionally the HTTP methods it can respond to (defaulting to any). You can then
pass optional arguments via its `setOptions()` method; these should be an array
of key/value pairs.

Typically, however, you will add routes via the `Application` instance itself.
This will be done in one of two ways:

- Via a method named after the HTTP method. (We support `get()`, `post()`,
  `put()`, `patch()`, and `delete()` as method calls.) These methods require the
  path and middleware as arguments.
- Via the `route()` method. This method requires the path and middleware as
  arguments, and optionally allows you to specify a list of HTTP methods
  (defaulting to any HTTP method if none is provided).

Each of these will return a `Route` instance, allowing you to set options if
desired.

```php
// GET
// This demonstrates passing a callable middleware (assuming $helloWorld is
// callable).
$app->get('/', $helloWorld);

// POST
// This example specifies the middleware as a service name instead of as a
// callable.
$app->post('/trackback', 'TrackBack');

// PUT
// This example shows operating on the returned route. In this case, it's adding
// regex tokens to restrict what values for {id} will match. (The tokens feature
// is specific to Aura.Router.)
$app->put('/post/{id}', 'ReplacePost')
    ->setOptions([
        'tokens' => [ 'id' => '\d+' ],
    ]);

// PATCH
// This example builds on the one above. zend-expressive allows you to specify
// the same path for a route matching on a different HTTP method, and
// corresponding to different middleware.
$app->patch('/post/{id}', 'UpdatePost')
    ->setOptions([
        'tokens' => [ 'id' => '\d+' ],
    ]);

// DELETE
$app->delete('/post/{id}', 'DeletePost')
    ->setOptions([
        'tokens' => [ 'id' => '\d+' ],
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
        'tokens' => [ 'id' => '\d+' ],
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

zend-expressive works with
[container-interop](https://github.com/container-interop/container-interop),
though it's an optional feature. By default, if you use the `AppFactory`, it
will use
[zend-servicemanager](https://github.com/zendframework/zend-servicemanager).

In the following example, we'll populate the container with our middleware, and
the application will pull it from there when matched.

Edit your `public/index.php` to read as follows:

```php
use Zend\Diactoros\Response\JsonResponse;
use Zend\Expressive\AppFactory;
use Zend\ServiceManager\ServiceManager;

require __DIR__ . '/../vendor/autoload.php';

$services = new ServiceManager();

$services->setFactory('HelloWorld', function ($services) {
    return function ($req, $res, $next) {
        $res->write('Hello, world!');
        return $res;
    };
});

$services->setFactory('Ping', function ($services) {
    return function ($req, $res, $next) {
        return new JsonResponse(['ack' => time()]);
    };
});

$app = AppFactory::create($services);
$app->get('/', 'HelloWorld');
$app->get('/ping', 'Ping');

$app->run();
```

In the example above, we pass our container to `AppFactory`. We could have also
done this instead:

```php
$app = AppFactory::create();
$services = $app->getContainer();
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
  and return the class.

If you fire up your web server, you'll find that the `/` and `/ping` paths
continue to work.

One other approach you could take would be to define the application itself in
the container, and then pull it from there:

```php
$services->setFactory('Zend\Expressive\Application', function ($services) {
    $app = AppFactory::create($services);
    $app->get('/', 'HelloWorld');
    $app->get('/ping', 'Ping');
    return $app;
});

$app = $services->get('Zend\Expressive\Application');
$app->run();
```

This is a nice way to encapsulate the application creation. You could then
potentially move all service configuration to another file!

## Hello World using a Configuration-Driven Container

In the above example, we configured our middleware as services, and then passed
our service container to the application. At the end, we hinted that you could
potentially define the application itself as a service.

zend-expressive already provides a service factory for the application instance
to provide fine-grained control over your application. In this example, we'll
leverage it, defining our routes via configuration.

In `config/config.php`, place the following:

```php
<?php
return [
    'routes' => [
        [
            'path' => '/',
            'middleware' => 'Application\HelloWorld',
            'allowed_methods' => [ 'GET' ],
        ],
        [
            'path' => '/ping',
            'middleware' => 'Application\Ping',
            'allowed_methods' => [ 'GET' ],
        ],
    ],
];
```

In `config/services.php`, place the following:

```php
<?php
return [
    'services' => [
        'config' => include __DIR__ . '/config.php',
    ],
    'invokables' => [
        'Application\HelloWorld' => 'Application\HelloWorld',
        'Application\Ping' => 'Application\Ping',
    ],
    'factories' => [
        'Zend\Expressive\Application' => 'Zend\Expressive\Container\ApplicationFactory',
    ],
];
```

In `src/HelloWorld.php`, place the following:

```php
<?php
namespace Application;

class HelloWorld
{
    public function __invoke($req, $res, $next)
    {
        $res->write('Hello, world!');
        return $res;
    }
}
```

In `src/Ping.php`, place the following:

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

Finally, in `public/index.php`, place the following:

```php
<?php
use Zend\ServiceManager\Config;
use Zend\ServiceManager\ServiceManager;

require __DIR__ . '/../vendor/autoload.php';

$services = new ServiceManager(new Config(include __DIR__ . '/../config/services.php'));

$app = $services->get('Zend\Expressive\Application');
$app->run();
```

Notice that our index file now doesn't have any code related to setting up the
application any more!

Firing up the web server, you'll see the same responses as the previous
examples.

## Hybrid Container and Programmatic Creation

The above example may look a little daunting at first. By making
everything configuration-driven, you sometimes lose a sense for how the code all
fits together.

Fortunately, you can mix the two. Building on the example above, we'll add a new
route and middleware. Between pulling the application from the container and
calling `$app->run()`, add the following in your `public/index.php`:

```php
$app->post('/post', function ($req, $res, $next) {
    $res->write('IN POST!');
    return $res;
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

## Error Handling

Because zend-expressive is built on top of Stratigility, you can provide error
handling by providing error middleware to the application, using the `pipe()`
method.

Error middleware has the following signature:

```php
function ($error, $request, $response, $next)
```

Error middleware is executed in the order in which it is piped to the
application; each can either stop execution by returning a response, or pass
along to the next by calling `$next()`.

## Middleware that executes on every request

`Zend\Expressive\Application` extends `Zend\Stratigility\MiddlewarePipe`,
allowing composition of multiple middleware.

It also implements routing middleware (`Application::routeMiddleware()`), which it
pipes to itself the first time a route is added.

As such, if you want to execute middleware on every request, you have several
options:

- For middleware you want to match for every request (not just error
  middleware), pipe it to the application prior to registering any routes.
- Error middleware should typically be piped *after* registering any routes.

As an example:

```php
use Zend\Expressive\AppFactory;

$app = AppFactory::create();

$app->pipe($middlewareToExecuteOnEachRequest)
$app->route(/* ... */);
$app->pipe($anErrorHandler);

$app->run();
```

### Using the container to register middleware

If you use a container to fetch your application instance, you have an
additional option for specifying middleware for the pipeline: configuration:

```php
return [
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
    'middleware_pipeline' => [
        'pre_routing' => [
            // See specification below
        ],
        'post_routing' => [
            // See specification below
        ],
    ],
];
```

The key to note is `middleware_pipeline`, which can have two subkeys,
`pre_routing` and `post_routing`. Each accepts an array of middlewares to
register in the pipeline; they will each be `pipe()`'d to the Application in the
order specified. Those specified `pre_routing` will be registered before any
routes, and thus before the routing middleware, while those specified
`post_routing` will be `pipe()`'d afterwards (again, also in the order
specified).

Each middleware specified in either `pre_routing` or `post_routing` must be in
the following form:

```php
[
    // required:
    'middleware' => 'Name of middleware service, or a callable',
    // optional:
    'path' => '/path/to/match',
]
```

Middleware may be any callable, `Zend\Stratigility\MiddlewareInterface`
implementation, or a service name that resolves to one of the two.

The path, if specified, can only be a literal path to match, and is typically
used for segregating middleware applications or applying rules to subsets of an
application that match a common path root.

> #### Lazy-loaded Middleware
>
> One feature of the `middleware_pipeline` is that any middleware service pulled
> from the container is actually wrapped in a closure:
>
> ```php
> function ($request, $response, $next = null) use ($services, $middleware) {
>     $invokable = $services->get($middleware);
>     if (! is_callable($invokable)) {
>         throw new Exception\InvalidMiddlewareException(sprintf(
>             'Lazy-loaded middleware "%s" is not invokable',
>             $middleware
>         ));
>     }
>     return $invokable($request, $response, $next);
> };
> ```
>
> This implements *lazy-loading* for middleware pipeline services, delaying
> retrieval from the container until the middleware is actually invoked.
>
> This also means that if the service specified is not valid middleware, you
> will not find out until the application attempts to invoke it.

## Segregating your application to a subpath

One benefit of a middleware-based application is the ability to compose
middleware and segregate them by paths. `Zend\Expressive\Application` is itself
middleware, allowing you to do exactly that if desired.

In the following example, we'll assume that `$api` and `$blog` are
`Zend\Expressive\Application` instances, and compose them into a
`Zend\Stratigility\MiddlewarePipe`.

```php
use Zend\Diactoros\Server;
use Zend\Stratigility\MiddlewarePipe;

require __DIR__ . '/../vendor/autoload.php';

$app = new MiddlewarePipe();
$app->pipe('/blog', $blog);
$app->pipe('/api', $api);

$server = Server::fromGlobals($app);
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
