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

$services = new ServiceManager();
$config = new Config(include __DIR__ . '/../config/services.php');
$config->configure($services);

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

`Zend\Expressive\Application` pipes `Zend\Expressive\Dispatcher` immediately on
instantiation, making it impossible to add middleware to execute on each request
out-of-the-box. Since `Application` is itself middleware, however, you can
compose it within another middleware pipeline.

As an example:

```php
use Zend\Diactoros\Server;
use Zend\Expressive\AppFactory;
use Zend\Stratigility\MiddlewarePipe;

$app = new MiddlewarePipe();
$app->pipe(function ($req, $res, $next) {
    // executes on every request
});
$app->pipe(AppFactory::create());

$server = Server::createServer($app);
$server->listen();
```

(Instead of using `Zend\DiactorosServer`, you could use an emitter; this is just
the simplest example for the scenario.)

With this workflow, you can even segregate your application instance to a
subpath if desired!
