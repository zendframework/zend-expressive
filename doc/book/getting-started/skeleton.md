# Quick Start: Using the Skeleton + Installer

The easiest way to get started with Expressive is to use the [skeleton
application and installer](https://github.com/zendframework/zend-expressive-skeleton).
The skeleton provides a generic structure for creating your applications, and
prompts you to choose a router, dependency injection container, template
renderer, and error handler from the outset.

## Create a new project

First, we'll create a new project, using Composer's `create-project` command:

```bash
$ composer create-project zendframework/zend-expressive-skeleton expressive
```

This will prompt you to choose:

- Whether to install a minimal skeleton (no default middleware), a flat
  application structure (all code under `src/`), or a modular structure
  (directories under `src/` are modules, each with source code and potentially
  templates, configuration, assets, etc.).

- A dependency injection container. We recommend using the default, Zend
  ServiceManager.

- A router. We recommend using the default, FastRoute.

- A template renderer. You can ignore this when creating an API project, but if
  you will be creating any HTML pages, we recommend installing one. We prefer
  Plates.

- An error handler. Whoops is a very nice option for development, as it gives
  you extensive, browseable information for exceptions and errors raised.

## Start a web server

The Skeleton + Installer creates a full application structure that's ready-to-go
when complete. You can test it out using [built-in web
server](http://php.net/manual/en/features.commandline.webserver.php).

From the project root directory, execute the following:

```bash
$ composer run --timeout=0 serve
```

This starts up a web server on localhost port 8080; browse to
http://localhost:8080/ to see if your application responds correctly!

> ### Setting a timeout
>
> Composer commands time out after 300 seconds (5 minutes). On Linux-based
> systems, the `php -S` command that `composer serve` spawns continues running
> as a background process, but on other systems halts when the timeout occurs.
>
> As such, we recommend running the `serve` script using a timeout. This can
> be done by using `composer run` to execute the `serve` script, with a
> `--timeout` option. When set to `0`, as in the previous example, no timeout
> will be used, and it will run until you cancel the process (usually via
> `Ctrl-C`). Alternately, you can specify a finite timeout; as an example,
> the following will extend the timeout to a full day:
>
> ```bash
> $ composer run --timeout=86400 serve
> ```

## Development Tools

Starting in version 2 of the skeleton, we ship tools to make development easier.

### Development Mode

[zf-development-mode](https://github.com/zfcampus/zf-development-mode) allows 
you to enable and disable development mode from your cli.

```bash
$ composer development-enable  # enable development mode
$ composer development-disable # disable development mode
$ composer development-status  # show development status
```

The development configuration is set in `config/autoload/development.local.php.dist`.
It also allows you to specify configuration and modules that should only be enabled 
when in development, and not when in production.

### Clear config cache

Production settings are the default, which means enabling the configuration cache. 
However, it must be easy for developers to clear the configuration cache. That's
what this command does.

```bash
$ composer clear-config-cache
```

### Testing Your Code

[PHPUnit](https://github.com/sebastianbergmann/phpunit) and 
[PHP_CodeSniffer](https://github.com/squizlabs/PHP_CodeSniffer) are now
installed by default. To execute tests and detect coding standards violations,
run the following command: 

```bash
$ composer check
```

### Security Advisories

We have included the [security-advisories](https://github.com/Roave/SecurityAdvisories)
package to notify you about installed dependencies with known security
vulnerabilities. Each time you run `composer update`, `composer install`, or
`composer require`, it prevents installation of software with known and
documented security issues.

## Modules

Composer will prompt you during installation to ask if you want a
minimal application (no structure or default middleware provided), flat
application (all source code under the same tree, and the default selection), or
modular application. This latter option is new in the version 2 series, and
allows you to segregate discrete areas of application functionality into
_modules_, which can contain source code, templates, assets, and more; these can
later be repackaged for re-use if desired.

Support for modules is available via the
[zend-component-installer](https://github.com/zendframework/zend-component-installer)
and [zend-config-aggregator](https://github.com/zendframework/zend-config-aggregator)
packages; the [zend-expressive-tooling](https://github.com/zendframework/zend-expressive-tooling).
package provides tools for creating and manipulating modules in your
application.

### Component Installer

Whenever you add a component or module that exposes itself as such, the 
[zend-component-installer](https://zendframework.github.io/zend-component-installer/) 
composer plugin will prompt you, asking if and where you want to inject its
configuration. This ensures that components are wired automatically for you.

In most cases, you will choose to inject in the `config/config.php` file; for
tools intended only for usage during development, choose
`config/development.config.php.dist`.

### Config Aggregator

The [zend-config-aggregator](https://github.com/zendframework/zend-config-aggregator)
library collects and merges configuration from different sources. It also supports 
configuration caching.

As an example, your `config/config.php` file might read as follows in order to
aggregate configuration from development mode settings, application
configuration, and theoretical `User`, `Blog`, and `App` modules:

```php
<?php // config/config.php

$aggregator = new ConfigAggregator([
    // Module configuration
    App\ConfigProvider::class,
    BlogModule\ConfigProvider::class,
    UserModule\ConfigProvider::class,

    // Load application config in a pre-defined order in such a way that local settings
    // overwrite global settings. (Loaded as first to last):
    //   - `global.php`
    //   - `*.global.php`
    //   - `local.php`
    //   - `*.local.php`
    new PhpFileProvider('config/autoload/{{,*.}global,{,*.}local}.php'),

    // Load development config if it exists
    new PhpFileProvider('config/development.config.php'),
], 'data/config-cache.php');

return $aggregator->getMergedConfig();
```

The configuration is merged in the same order as it is passed, with later entries 
having precedence.

### Config Providers

`ConfigAggregator` works by aggregating "Config Providers" passed to its
constructor. Each provider should be a callable class that requires no
constructor parameters, where invocation returns a configuration array (or a PHP
generator) to be merged.

Libraries or modules can have configuration providers that provide default values 
for a library or module. For the `UserModule\ConfigProvider` class loaded in the
`ConfigAggregator` above, the `ConfigProvider` might look like this:

```php
<?php

namespace UserModule;

class ConfigProvider
{
    /**
     * Returns the configuration array
     *
     * To add some sort of a structure, each section is defined in a separate
     * method which returns an array with its configuration.
     *
     * @return array
     */
    public function __invoke()
    {
        return [
            'dependencies' => $this->getDependencies(),
            'users'        => $this->getConfig(),
        ];
    }

    /**
     * Returns the container dependencies
     *
     * @return array
     */
    public function getDependencies()
    {
        return [
            'factories'  => [
                Action\LoginAction::class =>
                    Factory\Action\LoginActionFactory::class,

                Middleware\AuthenticationMiddleware::class =>
                    Factory\Middleware\AuthenticationMiddlewareFactory::class,
            ],
        ];
    }

    /**
     * Returns the default module configuration
     *
     * @return array
     */
    public function getConfig()
    {
        return [
            'paths' => [
                'enable_registration' => true,
                'enable_username'     => false,
                'enable_display_name' => true,
            ],
        ];
    }
}
```

### expressive-module command

To aid in the creation, registration, and deregistration of modules in your
application, the installer will add the [zendframework/zend-expressive-tooling](https://github.com/zendframework/zend-expressive-tooling)
as a development requirement when you choose the modular application layout.

The tool is available from your application root directory via
`./vendor/bin/expressive-module`. For brevity, we will only reference the tool's
name, `expressive-module`, when describing its capabilities.

This tool provides the following functionality:

- `expressive-module create <modulename>` will create the default directory
  structure for the named module, create a `ConfigProvider` for the module, add
  an autoloading rule to `composer.json`, and register the `ConfigProvider` with
  the application configuration.
- `expressive-module register <modulename>` will add an autoloading rule to
  `composer.json` for the module, and register its `ConfigProvider`, if found,
  with the application configuration.
- `expressive-module deregister <modulename>` will remove any autoloading rules
  for the module from `composer.json`, and deregister its `ConfigProvider`, if
  found, from the application configuration.

You can find out more about its features in the [command line tooling
documentation](../reference/cli-tooling.md#modules).

## Adding Middleware

The skeleton makes the assumption that you will be writing your middleware as
classes, and uses [piping and routing](../features/router/piping.md) to add 
your middleware.

### Piping

[Piping](../features/router/piping.md#piping) is a foundation feature of the 
underlying [zend-stratigility](https://docs.zendframework.com/zend-stratigility/)
implementation. You can setup the middleware pipeline in `config/pipeline.php`.
In this section, we'll demonstrate setting up a basic pipeline that includes
error handling, segregated applications, routing, middleware dispatch, and more.

The error handler should be the first (most outer) middleware to catch all 
exceptions.

```php
$app->pipe(ErrorHandler::class);
$app->pipe(ServerUrlMiddleware::class);  
```

After the `ErrorHandler` you can pipe more middleware that you want to execute 
on every request, such as bootstrapping, pre-conditions, and modifications to 
outgoing responses:

```php
$app->pipe(ServerUrlMiddleware::class);  
```

Piped middleware may be either callables or service names. Middleware may also 
be passed as an array; each item in the array must resolve to middleware 
eventually (i.e., callable or service name); underneath, Expressive creates
`Zend\Stratigility\MiddlewarePipe` instances with each of the middleware listed
piped to it.

Middleware can be attached to specific paths, allowing you to mix and match
applications under a common domain. The handlers in each middleware attached 
this way will see a URI with the **MATCHED PATH SEGMENT REMOVED!!!**

```php
$app->pipe('/api', $apiMiddleware);
$app->pipe('/docs', $apiDocMiddleware);
$app->pipe('/files', $filesMiddleware);    
```

Next, you should register the routing middleware in the middleware pipeline:

```php
$app->pipeRoutingMiddleware();
```

Add more middleware that needs to introspect the routing results; this might 
include:

- handling for HTTP `HEAD` requests
- handling for HTTP `OPTIONS` requests
- middleware for handling URI generation
- route-based authentication
- route-based validation
- etc.

```php
$app->pipe(ImplicitHeadMiddleware::class);
$app->pipe(ImplicitOptionsMiddleware::class);
$app->pipe(UrlHelperMiddleware::class);
```

Next, register the dispatch middleware in the middleware pipeline:

```php
$app->pipeDispatchMiddleware();
```

At this point, if no response is return by any middleware, we need to provide a
way of notifying the user of this; by default, we use the `NotFoundHandler`, but
you can provide any other fallback middleware you wish:

```php
$app->pipe(NotFoundHandler::class);
```

The full example then looks something like this:

```php
// In config/pipeline.php:

use Zend\Expressive\Helper\ServerUrlMiddleware;
use Zend\Expressive\Helper\UrlHelperMiddleware;
use Zend\Expressive\Middleware\ImplicitHeadMiddleware;
use Zend\Expressive\Middleware\ImplicitOptionsMiddleware;
use Zend\Expressive\Middleware\NotFoundHandler;
use Zend\Stratigility\Middleware\ErrorHandler;

$app->pipe(ErrorHandler::class);
$app->pipe(ServerUrlMiddleware::class);

// These assume that the variables listed are defined in this scope:
$app->pipe('/api', $apiMiddleware);
$app->pipe('/docs', $apiDocMiddleware);
$app->pipe('/files', $filesMiddleware);

$app->pipeRoutingMiddleware();
$app->pipe(ImplicitHeadMiddleware::class);
$app->pipe(ImplicitOptionsMiddleware::class);
$app->pipe(UrlHelperMiddleware::class);
$app->pipeDispatchMiddleware();

$app->pipe(NotFoundHandler::class);
```

### Routing
  
[Routing](../features/router/piping.md#routing) is an additional feature
provided by Expressive. Routing is setup in `config/routes.php`.

You can setup routes with a single request method:

```php
$app->get('/', App\Action\HomePageAction::class, 'home');
$app->post('/album', App\Action\AlbumCreateAction::class, 'album.create');
$app->put('/album/:id', App\Action\AlbumUpdateAction::class, 'album.put');
$app->patch('/album/:id', App\Action\AlbumUpdateAction::class, 'album.patch');
$app->delete('/album/:id', App\Action\AlbumDeleteAction::class, 'album.delete');
```

Or with multiple request methods:

```php
$app->route('/contact', App\Action\ContactAction::class, ['GET', 'POST', ...], 'contact');
```

Or handling all request methods:

```php
$app->route('/contact', App\Action\ContactAction::class)->setName('contact');
```

Alternately, to be explicit, the above could be written as:

```php
$app->route(
  '/contact',
  App\Action\ContactAction::class,
  Zend\Expressive\Router\Route::HTTP_METHOD_ANY,
  'contact'
);  
```

We recommend a single middleware class per combination of route and request
method.

## Next Steps

The skeleton provides a default structure for templates, if you choose to use them. 
Let's see how you can create your first vanilla middleware, and templated middleware.

### Creating middleware

To create middleware, create a class implementing
`Interop\Http\ServerMiddleware\MiddlewareInterface`. This interface defines a
single method, `process()`, which accepts a
`Psr\Http\Message\ServerRequestInterface` instance and an
`Interop\Http\ServerMiddleware\DelegateInterface` instance.

> ### Legacy double-pass middleware
>
> Prior to Expressive 2.0, the default middleware style was what is termed
> "double-pass", for the fact that it passes both the request and response between
> layers. This middleware did not require an interface, and relied on a
> conventional definition of:
> 
> ```php
> use Psr\Http\Message;
> 
> function (
>   Message\ServerRequestInterface $request,
>   Message\ResponseInterface $response,
>   callable $next
> ) : Message\ResponseInterface
> ```
> 
> While this style of middleware is still quite wide-spread and used in a number
> of projects, it has some flaws. Chief among them is the fact that middleware
> should not rely on the `$response` instance provided to them (as it may have
> modifications unacceptable for the current context), and that a response
> returned from inner layers may not be based off the `$response` provided to them
> (as inner layers may create and return a completely different response).
> 
> Starting in Expressive 2.0, we add support for
> [http-interop/http-middleware](https://github.com/http-interop/http-middleware),
> which is a working group of [PHP-FIG](http://www.php-fig.org/) dedicated to
> creating a common middleware standard. This middleware uses what is termed
> a "single-pass" or "lambda" architecture, whereby only the request instance is
> passed between layers. We now recommend writing middleware using the
> http-middleware interfaces for all new middleware.
> 
> Middleware using the double-pass style is still accepted by Expressive, but
> support for it will be discontinued once http-middleware is formally approved
> by PHP-FIG.

The skeleton defines an `App` namespace for you, and suggests placing middleware
under the namespace `App\Action`.

Let's create a "Hello" action. Place the following in
`src/App/Action/HelloAction.php`:

```php
<?php
namespace App\Action;

use Interop\Http\ServerMiddleware\DelegateInterface;
use Interop\Http\ServerMiddleware\MiddlewareInterface;
use Psr\Http\Message\ServerRequestInterface;
use Zend\Diactoros\Response\HtmlResponse;

class HelloAction implements MiddlewareInterface
{
    public function process(ServerRequestInterface $request, DelegateInterface $delegate)
    {
        // On all PHP versions:
        $query  = $request->getQueryParams();
        $target = isset($query['target']) ? $query['target'] : 'World';

        // Or, on PHP 7+:
        $target = $request->getQueryParams()['target'] ?? 'World';

        $target = htmlspecialchars($target, ENT_HTML5, 'UTF-8');

        return new HtmlResponse(sprintf(
            '<h1>Hello, %s!</h1>',
            $target
        ));
    }
}
```

The above looks for a query string parameter "target", and uses its value to
provide a message, which is then returned in an HTML response.

Now we need to inform the application of this middleware, and indicate what
path will invoke it. Open the file `config/autoload/dependencies.global.php`.
Edit that file to add an _invokable_ entry for the new middleware:

```php
return [
    'dependencies' => [
        /* ... */
        'invokables' => [
            App\Action\HelloAction::class => App\Action\HelloAction::class,
            /* ... */
        ],
        /* ... */
    ],
];
```

Now open the file `config/routes.php`, and add the following at the bottom of
the file:

```php
$app->get('/hello', App\Action\HelloAction::class, 'hello');
```

Once you've completed the above, give it a try by going to each of the
following URIs:

- http://localhost:8080/hello
- http://localhost:8080/hello?target=ME

You should see the message change as you go between the two URIs!

### Using templates

You likely don't want to hardcode HTML into your middleware; so, let's use
templates. This particular exercise assumes you chose to use the
[Plates](http://platesphp.com) integration.

Templates are installed under the `templates/` subdirectory. By default, we also
register the template namespace `app` to correspond with the `templates/app`
subdirectory. Create the file `templates/app/hello-world.phtml` with the
following contents:

```php
<?php $this->layout('layout::default', ['title' => 'Greetings']) ?>

<h2>Hello, <?= $this->e($target) ?></h2>
```

Now that we have a template, we need to:

- Inject a renderer into our action class.
- Use the renderer to render the contents.

Replace your `src/App/Action/HelloAction.php` file with the following contents:

```php
<?php
namespace App\Action;

use Interop\Http\ServerMiddleware\DelegateInterface;
use Interop\Http\ServerMiddleware\MiddlewareInterface;
use Psr\Http\Message\ServerRequestInterface;
use Zend\Diactoros\Response\HtmlResponse;
use Zend\Expressive\Template\TemplateRendererInterface;

class HelloAction implements MiddlewareInterface
{
    private $renderer;

    public function __construct(TemplateRendererInterface $renderer)
    {
        $this->renderer = $renderer;
    }

    public function process(ServerRequestInterface $request, DelegateInterface $delegate)
    {
        // On all PHP versions:
        $query  = $request->getQueryParams();
        $target = isset($query['target']) ? $query['target'] : 'World';

        // Or, on PHP 7+:
        $target = $request->getQueryParams()['target'] ?? 'World';

        return new HtmlResponse(
            $this->renderer->render('app::hello-world', ['target' => $target])
        );
    }
}
```

The above modifies the class to accept a renderer to the constructor, and then
calls on it to render a template. Note that we no longer need to escape our
target; the template takes care of that for us.

How does the template renderer get into the action? The answer is dependency 
injection.

For the next part of the example, we'll be creating and wiring a factory for
creating the `HelloAction` instance; the example assumes you used the default
selection for a dependency injection container, zend-servicemanager.

zend-servicemanager provides a tool for generating factories based on
reflecting a class; we'll use that to generate our factory:

```bash
$ ./vendor/bin/generate-factory-for-class "App\\Action\\HelloAction" > src/App/Action/HelloActionFactory.php
```

With that in place, we'll now update our configuration. Open the file
`config/autoload/dependencies.global.php`; we'll remove the `invokables` entry
we created previously, and add a `factories` entry:

```php
return [
    'dependencies' => [
        /* ... */
        'invokables' => [
            // Remove this entry:
            App\Action\HelloAction::class => App\Action\HelloAction::class,
        ],
        'factories' => [
            /* ... */
            // Add this:
            App\Action\HelloAction::class => App\Action\HelloActionFactory::class,
        ],
        /* ... */
    ],
];
```

Save that file, and now re-visit the URIs:

- http://localhost:8080/hello
- http://localhost:8080/hello?target=ME

Your page should now have the same layout as the landing page of the skeleton
application!

## Congratulations!

Congratulations! You've now created your application, and started writing
middleware! It's time to start learning about the rest of the features of
Expressive:

- [Containers](../features/container/intro.md)
- [Routing](../features/router/intro.md)
- [Templating](../features/template/intro.md)
- [Error Handling](../features/error-handling.md)
