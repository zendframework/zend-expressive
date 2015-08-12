# Error Handling

zend-expressive provides error handling out of the box, via zend-stratigility's [FinalHandler
implementation](https://github.com/zendframework/zend-stratigility/blob/master/doc/book/api.md#finalhandler).
This pseudo-middleware is executed in the following conditions:

- If the middleware stack is exhausted, and no middleware has returned a response.
- If an error has been passed via `$next()`, but not handled by any error middleware.

The `FinalHandler` essentially tries to recover gracefully. In the case that no error was passed, it
does the following:

- If the response passed to it differs from the response provided at initialization, it will return
  the response directly; the assumption is that some middleware along the way called `$next()`
  with a new response.
- If the response instances are identical, it checks to see if the body size has changed; if it has,
  the assumption is that a middleware at some point has written to the response body.
- At this point, it assumes no middleware was able to handle the request, and creates a 404
  response, indicating "Not Found."

In the event that an error *was* passed, it does the following:

- If `$error` is not an exception, it will use the response status if it already indicates an error
  (ie., &gt;= 400 status), or will use a 500 status, and return the response directly with the
  reason phrase.
- If `$error` *is* an exception, it will use the exception status if it already indicates an error
  (ie., &gt;= 400 status), or will use a 500 status, and return the response directly with the
  reason phrase. If the `FinalHandler` was initialized with an option indicating that it is in
  development mode, it writes the exception stack trace to the response body.

This workflow stays the same throughout zend-expressive. But sometimes, it's just not enough.

## Templated Errors

You'll typically want to provide error messages in your site template. To do so, we provide
`Zend\Expressive\TemplatedErrorHandler`. This class is similar to the `FinalHandler`, but accepts,
optionally, a `Zend\Expressive\Template\TemplateInterface` instance, and template names to use for
404 and general error conditions. This makes it a good choice for use in production.

```php
use Zend\Expressive\Application;
use Zend\Expressive\Template\Plates;
use Zend\Expressive\TemplatedErrorHandler;

$plates = new Plates();
$plates->addPath(__DIR__ . '/templates/error', 'error');
$finalHandler = new TemplatedErrorHandler($plates, 'error::404', 'error::500');

$app = new Application($router, $container, $finalHandler);
```

The above will use the templates `error::404` and `error::500` for 404 and general errors,
respectively, rendering them using our Plates template adapter.

You can also use the `TemplatedErrorHandler` as a substitute for the `FinalHandler`, without using
templated capabilities, by omitting the `TemplateInterface` instance when instantiating it. In this
case, the response message bodies will be empty, though the response status will reflect the error.

### Whoops

[whoops](http://filp.github.io/whoops/) is a library for providing a more usable UI around
exceptions and PHP errors. We provide integration with this library through
`Zend\Express\WhoopsErrorHandler`. This error handler derives from the `TemplatedErrorHandler`, and
uses its features for 404 status and non-exception errors. For exceptions, however, it will return
the whoops output. As such, it is a good choice for use in development.

To use it, you will need to provide it a whoops runtime instance, as well as a
`Whoops\Handler\PrettyPageHandler` instance. You can also optionally provide a `TemplateInterface`
instance and template names, just as you would for a `TemplatedErrorHandler`.

```php
use Whoops\Handler\PrettyPageHandler;
use Whoops\Run as Whoops;
use Zend\Expressive\Application;
use Zend\Expressive\Template\Plates;
use Zend\Expressive\WhoopsErrorHandler;

$handler = new PrettyPageHandler();

$whoops = new Whoops;
$whoops->writeToOutput(false);
$whoops->allowQuit(false);
$whoops->pushHandler($handler);
$whoops->register();

$plates = new Plates();
$plates->addPath(__DIR__ . '/templates/error', 'error');
$finalHandler = new WhoopsErrorHandler(
    $whoops,
    $handler,
    $plates,
    'error::404',
    'error::500'
);

$app = new Application($router, $container, $finalHandler);
```

The calls to `writeToOutput(false)`, `allowQuite(false)`, and `register()` must be made to guarantee
whoops will interoperate well with zend-expressive.

You can add more handlers if desired.

Internally, when an exception is discovered, zend-expressive adds some data to the whoops output,
primarily around the request information (URI, HTTP request method, route match attributes, etc.).

## Container Factories and Configuration

The above may feel like a bit much when creating your application. As such, we provide several
factories that work with [container-interop](https://github.com/container-interop/container-interop)-compatible
container implementations to simplify setup.

### WhoopsPageHandlerFactory

> - Register this factory as `Zend\Expressive\WhoopsPageHandler`.
> - This service optionally consumes the service `Config`

`Zend\Expressive\Container\WhoopsPageHandlerFactory` will create and return a
`Whoops\Handler\PrettyPageHandler` instance. If the `Config` service is also defined, and returns an
array with the following structure:

```php
'whoops' => [
    'editor' => 'editor name, editor service name, or callable',
]
```

then the factory will also inject the handler with an editor (this will provide a clickable link in
the output that will open the editor with the file).

### WhoopsFactory

> - Register this factory as `Zend\Expressive\Whoops`.
> - This factory requires and consumes a service named `Zend\Expressive\WhoopsPageHandler`, and
>   optionally the service `Config`.

`Zend\Expressive\Container\WhoopsFactory` will create and return a `Whoops\Run` instance. It will
inject the `Zend\Expressive\WhoopsPageHandler` as a handler. Additionally, it calls the following
methods with the specified arguments:

- `writeToOutput(false)`
- `allowQuit(false)`
- `register()`

If the `Config` service is defined, and has the following structure:

```php
'whoops' => [
    'json_exceptions' => [
        'display'    => true, // required to enable the JsonResponseHandler
        'show_trace' => true, // optional
        'ajax_only'  => true, // optional
    ]
]
```

and the `display` flag is true, it will also inject a `Whoops\Handler\JsonResponseHandler`,
configured per the settings provided.

### WhoopsErrorHandlerFactory

> - Register this factory as `Zend\Expressive\FinalHandler`.
> - This factory requires and consumes the services `Zend\Expressive\Whoops` and
>   `Zend\Expressive\WhoopsPageHandler`, and optionally the services
>   `Zend\Expressive\Template\TemplateInterface` and `Config`.

`Zend\Expressive\Container\WhoopsErrorHandlerFactory` creates and returns an instance of
`Zend\Expressive\WhoopsErrorHandler`, injecting it with the services `Zend\Expressive\Whoops` and
`Zend\Expressive\WhoopsPageHandler`.

If the `Zend\Expressive\Template\TemplateInterface` service is available, that, too, will be
injected.

If the `Config` service is available, and contains the following structure:

```php
'zend-expressive' => [
    'error_handler' => [
        'template_404'   => 'name of 404 template',
        'template_error' => 'name of error template',
    ],
]
```

then the factory will inject the values for the given templates.

### TemplatedErrorHandlerFactory

> - Register this factory as `Zend\Expressive\FinalHandler`.
> - This factory optionally consumes the services `Zend\Expressive\Template\TemplateInterface` and
>   `Config`.

`Zend\Expressive\Container\TemplatedErrorHandlerFactory` creates and returns an instance of
`Zend\Expressive\TemplatedErrorHandler`.

If the `Zend\Expressive\Template\TemplateInterface` service is available, it will be injected.

If the `Config` service is available, and contains the following structure:

```php
'zend-expressive' => [
    'error_handler' => [
        'template_404'   => 'name of 404 template',
        'template_error' => 'name of error template',
    ],
]
```

then the factory will inject the values for the given templates.

### Configuring zend-servicemanager

To use the above with zend-servicemanager, you can either programatically add the factories, or do
so via configuration.

#### Programmatically

```php
use Zend\Expressive\Template\Plates;

// For all examples:
$services->setService('Config', $config);
$services->setFactory('Zend\Expressive\Template\TemplateInterface', function ($container) {
    $plates = new Plates();
    $plates->addPath($container->get('Config')['templates']['error'], 'error');
});
$services->setFactory('Zend\Expressive\Application', 'Zend\Expressive\Container\ApplicationFactory');

// For the WhoopsErrorHandler:
$services->setFactory('Zend\Expressive\WhoopsPageHandler', 'Zend\Expressive\Container\WhoopsPageHandlerFactory');
$services->setFactory('Zend\Expressive\Whoops', 'Zend\Expressive\Container\WhoopsFactory');
$services->setFactory('Zend\Expressive\FinalHandler', 'Zend\Expressive\Container\WhoopsErrorHandlerFactory');

// For the TemplatedErrorHandler:
$services->setFactory('Zend\Expressive\FinalHandler', 'Zend\Expressive\Container\TemplatedErrorHandlerFactory');
```

From there:

```php
$app = $services->get('Zend\Expressive\Application');
$app->run();
```

#### Via Configuration

First, the configuration:

```php
use Zend\Expressive\Template\Plates;

return [
    // Note: you may also be defining your middleware service configuration here.
    'service_manager' => [
        'factories' => [
            'Zend\Expressive\Application' => 'Zend\Expressive\Container\ApplicationFactory',
            'Zend\Expressive\Template\TemplateInterface' => function ($container) {
                $plates = new Plates();
                $plates->addPath($container->get('Config')['templates']['error'], 'error');
            },
            'Zend\Expressive\Whoops' => 'Zend\Expressive\Container\WhoopsFactory',
            'Zend\Expressive\WhoopsPageHandler' => 'Zend\Expressive\Container\WhoopsPageHandlerFactory',
            'Zend\Expressive\FinalHandler' => 'Zend\Expressive\Container\WhoopsErrorHandlerFactory',
            /* or:
            'Zend\Expressive\FinalHandler' => 'Zend\Expressive\Container\TemplatedErrorHandlerFactory',
            */
        ],
    ],
    'whoops' => [
        'json_exceptions' => [
            'display'    => true, // required to enable the JsonResponseHandler
            'show_trace' => true, // optional
            'ajax_only'  => true, // optional
        ],
    ],
    'zend-expressive' => [
        'error_handler' => [
            'template_404'   => 'name of 404 template',
            'template_error' => 'name of error template',
        ],
    ],
    // you might also have routes, middleware_pipeline, etc.
];
```

Next, the bootstrap:

```php
use Zend\ServiceManager\Config;
use Zend\ServiceManager\ServiceManager;

$config    = include 'config/config.php';
$container = new ServiceManager(new Config($config));
$container->setService('Config', $config);

$app = $container->get('Zend\Expressive\Application');
$app->run();
```

At this point, you're completely configured, with your final handler and any other services you
might define.

#### Varying services by environment

As noted in the section on each error handler, some error handlers are better suited for production,
and others for development. How can you manage that?

One trick is to use configuration globbing in order to specify alternate services. In this scenario,
you might define a global configuration with the production values, and then override that with
local configuration. zend-config and zend-stdlib provide tools for doing such configuration merging.

> Note: In progrress
>
> This section is still in progress, and will be addressed with more information later.

### Using Pimple

Using Pimple is similar to programmatic usage of zend-servicemanager.

```php
use Interop\Container\Pimple\PimpleInterop;
use Zend\Expressive\Container;
use Zend\Expressive\Template\Plates;

$pimple = new Pimple()

$pimple['Config'] = $config;
$pimple['Zend\Expressive\Application'] = new Container\ApplicationFactory();
$pimple['Zend\Expressive\Template\TemplateInterface'] = function ($container) {
    $plates = new Plates();
    $plates->addPath($container->get('Config')['templates']['error'], 'error');
};
$pimple['Zend\Expressive\Whoops'] = new Container\WhoopsFactory();
$pimple['Zend\Expressive\WhoopsPageHandler'] = new Container\WhoopsPageHandlerFactory();
$pimple['Zend\Expressive\FinalHandler'] = new Container\WhoopsErrorHandlerFactory();
// or:
// $pimple['Zend\Expressive\FinalHandler'] = new Container\FinalErrorHandlerFactory();
```

Once configured:

```php
$app = $pimple['Zend\Expressive\Application'];
$app->run();
```
