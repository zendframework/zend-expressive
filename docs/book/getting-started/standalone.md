# Quick Start: Standalone Usage

Expressive allows you to get started at your own pace. You can start with
the simplest example, detailed below, or move on to a more structured,
configuration-driven approach as detailed in the [use case examples](../reference/usage-examples.md).

## 1. Create a new project directory

First, let's create a new project directory and enter it:

```bash
$ mkdir expressive
$ cd expressive
```

## 2. Install Expressive

If you haven't already, [install Composer](https://getcomposer.org). Once you
have, we can install Expressive, along with a router and a container:

```bash
$ composer require zendframework/zend-expressive zendframework/zend-expressive-fastroute zendframework/zend-servicemanager
```

> ### Routers
>
> Expressive needs a routing implementation in order to create routed
> middleware. We suggest FastRoute in the quick start, but you can also
> currently choose from Aura.Router and zend-router.

> ### Containers
>
> We highly recommend using dependency injection containers with Expressive;
> they allow you to define dependencies for your middleware, as well as to lazy
> load your middleware only when it needs to be executed. We suggest
> zend-servicemanager in the quick start, but you can also use any container
> supporting [PSR-11 Container](https://github.com/php-fig/container).

## 3. Create a web root directory

You'll need a directory from which to serve your application, and for security
reasons, it's a good idea to keep it separate from your source code. We'll
create a `public/` directory for this:

```bash
$ mkdir public
```

## 4. Create your bootstrap script

Next, we'll create a bootstrap script. Such scripts typically setup the
environment, setup the application, and invoke it. This needs to be in our web
root, and we want it to intercept any incoming request; as such, we'll use
`public/index.php`:

```php
<?php
use Interop\Http\ServerMiddleware\DelegateInterface;
use Zend\Diactoros\Response\TextResponse;
use Zend\Expressive\AppFactory;

chdir(dirname(__DIR__));
require 'vendor/autoload.php';

$app = AppFactory::create();

$app->get('/', function ($request, DelegateInterface $delegate) {
    return new TextResponse('Hello, world!');
});

$app->pipeRoutingMiddleware();
$app->pipeDispatchMiddleware();
$app->run();
```

> ### Rewriting URLs
>
> Many web servers will not rewrite URLs to the bootstrap script by default. If
> you use Apache, for instance, you'll need to setup rewrite rules to ensure
> your bootstrap is invoked for unknown URLs. We'll cover that in a later
> chapter.

> ### Routing and dispatching
>
> Note the lines from the above:
>
> ```php
> $app->pipeRoutingMiddleware();
> $app->pipeDispatchMiddleware();
> ```
>
> Expressive's `Application` class provides two separate middlewares, one for
> routing, and one for dispatching middleware matched by routing. This allows
> you to slip in validations between the two activities if desired. They are
> not automatically piped to the application, however, to allow exactly that
> situation, which means they must be piped manually.

## 5. Start a web server

Since we're just testing out the basic functionality of our application, we'll
use PHP's [built-in web server](http://php.net/manual/en/features.commandline.webserver.php).

From the project root directory, execute the following:

```bash
$ php -S 0.0.0.0:8080 -t public/
```

This starts up a web server on localhost port 8080; browse to
http://localhost:8080/ to see if your application responds correctly!

> ### Tip: Serve via Composer
>
> To simplify starting up a local web server, try adding the following to your
> `composer.json`:
>
> ```json
> "scripts": {
>     "serve": "php -S 0.0.0.0:8080 -t public/"
> }
> ```
>
> Once you've added that, you can fire up the web server using:
>
> ```bash
> $ composer serve
> ```

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

## Next steps

At this point, you have a working zend-expressive application, that responds to
a single route. From here, you may want to read up on:

- [Applications](../features/application.md)
- [Containers](../features/container/intro.md)
- [Routing](../features/router/intro.md)
- [Templating](../features/template/intro.md)
- [Error Handling](../features/error-handling.md)

Additionally, we have more [use case examples](../reference/usage-examples.md).
