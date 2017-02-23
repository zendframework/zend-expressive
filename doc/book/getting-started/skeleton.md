# Quick Start: Using the Skeleton + Installer

The easiest way to get started with Expressive is to use the [skeleton
application and installer](https://github.com/zendframework/zend-expressive-skeleton).
The skeleton provides a generic structure for creating your applications, and
prompts you to choose a router, dependency injection container, template
renderer, and error handler from the outset.

## 1. Create a new project

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

## 2. Start a web server

The Skeleton + Installer creates a full application structure that's ready-to-go
when complete. You can test it out using [built-in web
server](http://php.net/manual/en/features.commandline.webserver.php).

From the project root directory, execute the following:

```bash
$ composer serve
```

This starts up a web server on localhost port 8080; browse to
http://localhost:8080/ to see if your application responds correctly!

> ### Setting a timeout
>
> Composer commands time out after 300 seconds (5 minutes). On Linux-based
> systems, the `php -S` command that `composer serve` spawns continues running
> as a background process, but on other systems halts when the timeout occurs.
>
> If you want the server to live longer, you can use the
> `COMPOSER_PROCESS_TIMEOUT` environment variable when executing `composer
> serve` to extend the timeout. As an example, the following will extend it
> to a full day:
>
> ```bash
> $ COMPOSER_PROCESS_TIMEOUT=86400 composer serve
> ```

## Development Tools

With skeleton version 2 we are shipping some tools to make development easier.

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

### Testing Your Code

[PHPUnit](https://github.com/sebastianbergmann/phpunit) and 
[PHP_CodeSniffer](https://github.com/squizlabs/PHP_CodeSniffer) are installed. To
execute tests and detect coding standards violations run this composer command: 

```bash
$ composer check
```

### Security Advisories

We have included [security-advisories](https://github.com/Roave/SecurityAdvisories)
to notify you about installed dependencies with known security vulnerabilities.
Each time you run `composer update` or `composer install` it prevents installation of
software with known and documented security issues.

## Next Steps

The skeleton makes the assumption that you will be writing your middleware as
classes, and using configuration to map routes to middleware. It also provides a
default structure for templates, if you choose to use them. Let's see how you
can create your first vanilla middleware, and templated middleware.

### Creating middleware

The easiest way to create middleware is to create a class that defines an
`__invoke()` method accepting a request, response, and callable "next" argument
(for invoking the "next" middleware in the queue). The skeleton defines an `App`
namespace for you, and suggests placing middleware under the namespace
`App\Action`.

Let's create a "Hello" action. Place the following in
`src/App/Action/HelloAction.php`:

```php
<?php
namespace App\Action;

use Interop\Http\ServerMiddleware\DelegateInterface;
use Interop\Http\ServerMiddleware\MiddlewareInterface;
use Psr\Http\Message\ServerRequestInterface;
use Zend\Expressive\Response\HtmlResponse;

class HelloAction implements MiddlewareInterface
{
    public function process(ServerRequestInterface $request, DelegateInterface $delegate)
    {
        $query  = $request->getQueryParams();
        $target = isset($query['target']) ? $query['target'] : 'World';
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
        $query  = $request->getQueryParams();
        $target = isset($query['target']) ? $query['target'] : 'World';

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
