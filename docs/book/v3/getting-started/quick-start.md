# Quick Start

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
  templates, configuration, assets, etc.). The default is a "flat" structure;
  you can always add modules to it later.

- A dependency injection container. We recommend using the default,
  zend-servicemanager.

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

We ship tools in our skeleton application to make development easier.

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

[PHPUnit](https://phpunit.de) and
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

### Tooling integration

The skeleton ships with [zend-expressive-tooling](https://github.com/zendframework/zend-expressive-tooling)
by default, and integrates with it by exposing it via composer:

```bash
$ composer expressive
```

The tooling provides a number of commands; see the [CLI tooling
chapter](../reference/cli-tooling.md) for more details.

## Modules

Composer will prompt you during installation to ask if you want a minimal
application (no structure or default middleware provided), flat application (all
source code under the same tree, and the default selection), or modular
application. This latter option allows you to segregate discrete areas of
application functionality into _modules_, which can contain source code,
templates, assets, and more; these can later be repackaged for re-use if
desired.

Support for modules is available via the
[zend-component-installer](https://docs.zendframework.com/zend-component-installer/)
and [zend-config-aggregator](https://docs.zendframework.com/zend-config-aggregator/)
packages; the [zend-expressive-tooling](https://github.com/zendframework/zend-expressive-tooling).
package provides tools for creating and manipulating modules in your
application.

### Component Installer

Whenever you add a component or module that exposes itself as such, the
[zend-component-installer](https://docs.zendframework.com/zend-component-installer/)
composer plugin will prompt you, asking if and where you want to inject its
configuration. This ensures that components are wired automatically for you.

In most cases, you will choose to inject in the `config/config.php` file; for
tools intended only for usage during development, choose
`config/development.config.php.dist`.

### Config Aggregator

The [zend-config-aggregator](https://docs.zendframework.com/zend-config-aggregator/)
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
    public function getDependencies() : array
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
    public function getConfig() : array
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

### expressive module commands

To aid in the creation, registration, and deregistration of modules in your
application, you can use the CLI tooling provided by default. All commands are
exposed via `composer expressive`, and include the following:

- `composer expressive module:create <modulename>` will create the default
  directory structure for the named module, create a `ConfigProvider` for the
  module, add an autoloading rule to `composer.json`, and register the
  `ConfigProvider` with the application configuration.
- `composer expressive module:register <modulename>` will add an autoloading rule to
  `composer.json` for the module, and register its `ConfigProvider`, if found,
  with the application configuration.
- `expressive module:deregister <modulename>` will remove any autoloading rules
  for the module from `composer.json`, and deregister its `ConfigProvider`, if
  found, from the application configuration.

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

Piped middleware may be callables, middleware instances, or service names.
Middleware may also be passed as an array; each item in the array must resolve
to middleware eventually (i.e., callable or service name); underneath,
Expressive creates `Zend\Stratigility\MiddlewarePipe` instances with each of the
middleware listed piped to it.

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
$app->pipe(RouteMiddleware::class);
```

Add more middleware that needs to introspect the routing results; this might
include:

- handling for HTTP `HEAD` requests
- handling for HTTP `OPTIONS` requests
- handling for matched paths where the HTTP method is not allowed
- middleware for handling URI generation
- route-based authentication
- route-based validation
- etc.

```php
$app->pipe(ImplicitHeadMiddleware::class);
$app->pipe(ImplicitOptionsMiddleware::class);
$app->pipe(MethodNotAllowedMiddleware::class);
$app->pipe(UrlHelperMiddleware::class);
```

Next, register the dispatch middleware in the middleware pipeline:

```php
$app->pipe(DispatchMiddleware::class);
```

At this point, if no response is return by any middleware, we need to provide a
way of notifying the user of this; by default, we use the `NotFoundHandler`, but
you can provide any other fallback middleware you wish:

```php
$app->pipe(NotFoundHandler::class);
```

The `public/index.php` file will `require` the `config/pipeline.php` file, and
_invoke_ the returned result. When it invokes it, it passes the application
instance, a `Zend\Expressive\MiddlewareFactory` instance, and the PSR-11
container you are using.

The full example then looks something like this:

```php
// In config/pipeline.php:

use Psr\Container\ContainerInterface;
use Zend\Expressive\Application;
use Zend\Expressive\MiddlewareFactory;
use Zend\Expressive\Helper\ServerUrlMiddleware;
use Zend\Expressive\Helper\UrlHelperMiddleware;
use Zend\Expressive\Middleware\NotFoundHandler;
use Zend\Expressive\Router\Middleware\DispatchMiddleware;
use Zend\Expressive\Router\Middleware\ImplicitHeadMiddleware;
use Zend\Expressive\Router\Middleware\ImplicitOptionsMiddleware;
use Zend\Expressive\Router\Middleware\MethodNotAllowedMiddleware;
use Zend\Expressive\Router\Middleware\RouteMiddleware;
use Zend\Stratigility\Middleware\ErrorHandler;

return function (Application $app, MiddlewareFactory $factory, ContainerInterface $container) : void {
    $app->pipe(ErrorHandler::class);
    $app->pipe(ServerUrlMiddleware::class);

    // These assume that the variables listed are defined in this scope:
    $app->pipe('/api', $apiMiddleware);
    $app->pipe('/docs', $apiDocMiddleware);
    $app->pipe('/files', $filesMiddleware);

    $app->pipe(RouteMiddleware::class);
    $app->pipe(ImplicitHeadMiddleware::class);
    $app->pipe(ImplicitOptionsMiddleware::class);
    $app->pipe(MethodNotAllowedMiddleware::class);
    $app->pipe(UrlHelperMiddleware::class);
    $app->pipe(DispatchMiddleware::class);

    $app->pipe(NotFoundHandler::class);
};
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
$app->any('/contact', App\Action\ContactAction::class)->setName('contact');
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

Similar to the `config/pipeline.php` file, the `config/routes.php` file is
expected to return a callable:

```php
// In config/routes.php:

use Psr\Container\ContainerInterface;
use Zend\Expressive\Application;
use Zend\Expressive\MiddlewareFactory;

return function (Application $app, MiddlewareFactory $factory, ContainerInterface $container) : void {
    $app->get('/books', \App\Handler\ListBooksHandler::class, 'books');
};
```

## Next Steps

The skeleton provides a default structure for templates, if you choose to use them.
Let's see how you can create your first vanilla middleware, and templated middleware.

### Creating middleware

Middleware must implement `Psr\Http\Server\MiddlewareInterface`; this interface
defines a single method, `process()`, which accepts a
`Psr\Http\Message\ServerRequestInterface` instance and a
`Psr\Http\Server\RequestHandlerInterface` instance, and returns a
`Psr\Http\Message\ResponseInterface` instance. Write middleware when you may
want to delegate to another layer of the application in order to create a
response; do this by calling the `handle()` method of the handler passed to it.
_Generally speaking, you will write middleware when you want to conditionally
return a response based on the request, and/or alter the response returned by
another layer of the application_.

The skeleton defines an `App` namespace for you; you can place middleware
anywhere within it.

We'll create a simple middleware here that will run on every request, and alter
the response to add a header.

We can use our tooling to create the middleware file:

```bash
$ composer expressive middleware:create "App\XClacksOverheadMiddleware"
```

This command will create a PSR-15 middleware implementation, a factory for it,
and register the two in the application's container configuration. It tells you
the location of both files.

Now let's edit the middleware class file. Replace the contents of the
`process()` method with:

```php
$response = $handler->handle($request);
return $response->withHeader('X-Clacks-Overhead', 'GNU Terry Pratchett');
```

Now that we've created our middleware, we still have to tell the pipeline about
it. Open the file `config/pipeline.php` file, and find the line that read:

```php
$app->pipe(ErrorHandler::class);
```

Add the following line after it:

```php
$app->pipe(App\XClacksOverheadMiddleware::class);
```

If you browse to the home page (or any other page, for that matter) and
introspect the headers returned with the response using your browser's
development tools, you'll now see the following entry:

```http
X-Clacks-Overhead: GNU Terry Pratchett
```

You've created your first middleware!

### Creating request handlers

You may route to either middleware or request handlers. In this section, we'll
define a request handler and route to it.

Request handlers must implement `Psr\Http\Server\RequestHandlerInterface`; this
interface defines a single method, `handle()`, which accepts a
`Psr\Http\Message\ServerRequestInterface` instance and returns a
`Psr\Http\Message\ResponseInterface` instance. Write request handlers when you
will **not** be delegating to another layer of the application, and will be
creating and returning a response directly. _Generally speaking, you will route
to request handlers_.

The skeleton defines an `App` namespace for you, and suggests placing request
handlers under the namespace `App\Handler`.

Let's create a "Hello" request handler. We can use our tooling to create the
file:

```bash
$ composer expressive handler:create "App\Handler\HelloHandler"
```

The command will tell you the location in the filesystem in which it created the
new class; it will also create a factory for you, and register that factory with
the container! Additionally, if you have a template renderer in place, it will
create a template file for you. make a note of the locations of both the class
file and template file.

Open the class file, and now let's edit the `handle()` contents to read as
follows:

```php
$target = $request->getQueryParams()['target'] ?? 'World';
$target = htmlspecialchars($target, ENT_HTML5, 'UTF-8');
return new HtmlResponse($this->renderer->render(
    'app::hello',
    ['target' => $target]
));
```

> #### Templateless handler
>
> If you did not select a template engine when creating your application, the
> contents of your `handle()` method will be empty to begin.
>
> In that case, alter the above example as follows:
>
> - Add the statement `use Zend\Diactoros\Response\HtmlResponse;` to the `use`
>   statements at the top of the file.
> - Alter the response creation to read:
>   ```php
>   return new HtmlResponse(sprintf(
>       '<h1>Hello %s</h1>',
>       $target
>   ));
>   ```
>
> You can also skip the next step below where we edit the template file.

The above looks for a query string parameter "target", and uses its value to
provide to the template, which is then rendered and returned in an HTML
response.

Now, let's edit the template file to have the one of the following header lines (use the one for your chosen template renderer):

```html
<!-- plates -->
<h1>Hello <?= $this->e($target) ?></h1>

<!-- zend-view -->
<h1>Hello <?= $this->target ?></h1>

<!-- twig -->
<h1>Hello {{ target }}</h1>
```

While the handler is registered with the container, the application does not yet
know how to get to it. Let's fix that.

Open the file `config/routes.php`, and add the following at the bottom of
the function it exposes:

```php
$app->get('/hello', App\Handler\HelloHandler::class, 'hello');
```

Once you've completed the above, give it a try by going to each of the
following URIs:

- http://localhost:8080/hello
- http://localhost:8080/hello?target=ME

You should see the message change as you go between the two URIs!

## Congratulations!

Congratulations! You've now created your application, and started writing
middleware! It's time to start learning about the rest of the features of
Expressive:

- [Containers](../features/container/intro.md)
- [Routing](../features/router/intro.md)
- [Templating](../features/template/intro.md)
- [Error Handling](../features/error-handling.md)
