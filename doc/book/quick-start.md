# Quick Start: Standalone Usage

Expressive allows you to get started at your own pace. You can start with
the simplest example, detailed below, or move on to a more structured,
configuration-driven approach as detailed in the [use case examples](usage-examples.md).

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
> currently choose from Aura.Router and the ZF2 MVC router.

> ### Containers
>
> We highly recommend using dependency injection containers with Expressive;
> they allow you to define dependencies for your middleware, as well as to lazy
> load your middleware only when it needs to be executed. We suggest
> zend-servicemanager in the quick start, but you can also use any container
> supporting [container-interop](https://github.com/container-interop/container-interop).

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
use Zend\Expressive\AppFactory;

chdir(dirname(__DIR__));
require 'vendor/autoload.php';

$app = AppFactory::create();

$app->get('/', function ($request, $response, $next) {
    $response->write('Hello, world!');
    return $response;
});

$app->run();
```

> ### Rewriting URLs
>
> Many web servers will not rewrite URLs to the bootstrap script by default. If
> you use Apache, for instance, you'll need to setup rewrite rules to ensure
> your bootstrap is invoked for unknown URLs. We'll cover that in a later
> chapter.

## 5. Start a web server

Since we're just testing out the basic functionality of our application, we'll
use PHP's [built-in web server](http://php.net/manual/en/features.commandline.webserver.php).

From the project root directory, execute the following:

```bash
$ php -S 0.0.0.0:8080 -t public/
```

This starts up a web server on localhost port 8080; browse to
http://localhost:8080/ to see if your application responds correctly!

## Next steps

At this point, you have a working zend-expressive application, that responds to
a single route. From here, you may want to read up on:

- [Applications](application.md)
- [Containers](container/intro.md)
- [Routing](router/intro.md)
- [Templating](template/intro.md)
- [Error Handling](error-handling.md)

Additionally, we have more [use case examples](usage-examples.md).
