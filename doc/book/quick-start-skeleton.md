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

- A router. We recommend using the default, FastRoute.
- A dependency injection container. We recommend using the default, Zend
  ServiceManager.
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
$ php -S 0.0.0.0:8080 -t public/
```

This starts up a web server on localhost port 8080; browse to
http://localhost:8080/ to see if your application responds correctly!

## Next Steps

The skeleton makes the assumption that you will be writing your middleware as
classes, and using configuration to map routes to middleware. It also provides a
default structure for templates, if you choose to use them. Let's see how you
can create first vanilla middleware, and then templated middleware.

### Creating middleware

The easiest way to create middleware is to create a class that defines an
`__invoke()` method accepting a request, response, and callable "next" argument
(for invoking the "next" middleware in the queue). The skeleton defines an `App`
namespace for you, and suggests placing middleware under the namespace
`App\Action`.

Let's create a "Hello" action. Place the following in
`src/Action/HelloAction.php`:

```php
<?php
namespace App\Action;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

class HelloAction
{
    public function __invoke(ServerRequestInterface $request, ResponseInterface $response, callable $next)
    {
        $query  = $request->getQueryParams();
        $target = isset($query['target']) ? $query['target'] : 'World';
        $target = htmlspecialchars($target, ENT_HTML5, UTF-8);

        $response->getBody()->write(sprintf(
            '<h1>Hello, %s!</h1>',
            $target
        ));
        return $response->withHeader('Content-Type', 'text/html');
    }
}
```

The above looks for a query string parameter "target", and uses its value to
provide a message, which is then returned in an HTML response.

Now we need to inform the application of this middleware, and indicate what
path will invoke it. Open the file `config/autoload/routes.global.php`. Inside
that file, you should have a structure similar to the following:

```php
return [
    'dependencies' => [
        /* ... */
    ],
    'routes' => [
        /* ... */
    ],
];
```

We're going to add an entry under `routes`:

```php
return [
    /* ... */
    'routes' => [
        /* ... */
        [
            'name' => 'hello',
            'path' => '/hello',
            'middleware' => App\Action\HelloAction::class,
            'allowed_methods' => ['GET'],
        ],
    ],
];
```

Once you've added the above entry, give it a try by going to each of the
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

Replace your `src/Action/HelloAction.php` file with the following contents:

```php
<?php
namespace App\Action;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Zend\Diactoros\Response\HtmlResponse;
use Zend\Expressive\Template\TemplateRendererInterface;

class HelloAction
{
    private $renderer;

    public function __construct(TemplateRendererInterface $renderer)
    {
        $this->renderer = $renderer;
    }

    public function __invoke(ServerRequestInterface $request, ResponseInterface $response, callable $next)
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
calls on it to render a template. A few things to note:

- We no longer need to escape our target; the template takes care of that for us.
- We're using a specific response type here, from
  [Diactoros](https://github.com/zendframework/zend-diactoros), which is the
  default PSR-7 implementation Expressive uses. This response type simplifies
  our response creation.

How does the template renderer get into the action, however? The answer is
dependency injection.

Let's create a factory. Create the file `src/Action/HelloActionFactory.php` with
the following contents:

```php
<?php
namespace App\Action;

use Interop\Container\ContainerInterface;
use Zend\Expressive\Template\TemplateRendererInterface;

class HelloActionFactory
{
    public function __invoke(ContainerInterface $container)
    {
        return new HelloAction(
            $container->get(TemplateRendererInterface::class)
        );
    }
}
```

With that in place, we'll now update our configuration. Open the file
`config/autoload/dependencies.global.php`; it should have a structure similar to
the following:

```php
return [
    'dependencies' => [
        'invokables' => [
            /* ... */
        ],
        'factories' => [
            /* ... */
        ],
    ],
];
```

We're going to tell our application that we have a _factory_ for our
`HelloAction` class:

```php
return [
    'dependencies' => [
        /* ... */
        'factories' => [
            /* ... */
            App\Action\HelloAction::class => App\Action\HelloActionFactory::class,
        ],
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

- [Containers](container/intro.md)
- [Routing](router/intro.md)
- [Templating](template/intro.md)
- [Error Handling](error-handling.md)
