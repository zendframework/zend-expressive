# Using Pimple

[Pimple](http://pimple.sensiolabs.org/) is a widely used code-driven dependency
injection container provided as a standalone component by SensioLabs. It
features:

- combined parameter and service storage.
- ability to define factories for specific classes.
- lazy-loading via factories.

Pimple only supports programmatic creation at this time.

## Installing Pimple

Pimple does not currently (as of v3) implement
[PSR-11 Container](https://github.com/php-fig/container); as
such, you need to install the `xtreamwayz/pimple-container-interop` project,
which provides a [PSR-11 Container](https://github.com/php-fig/container)
wrapper around Pimple v3:

```bash
$ composer require xtreamwayz/pimple-container-interop
```

## Configuring Pimple

To configure Pimple, instantiate it, and then add the factories desired. We
recommend doing this in a dedicated script that returns the Pimple instance; in
this example, we'll have that in `config/container.php`.

```php
use Xtreamwayz\Pimple\Container as Pimple;
use Zend\Expressive\Container;
use Zend\Expressive\Plates\PlatesRenderer;
use Zend\Expressive\Router;
use Zend\Expressive\Template\TemplateRendererInterface;

$container = new Pimple();

// Application and configuration
$container['config'] = include 'config/config.php';
$container['Zend\Expressive\Application'] = new Container\ApplicationFactory;

// Routing
// In most cases, you can instantiate the router you want to use without using a
// factory:
$container['Zend\Expressive\Router\RouterInterface'] = function ($container) {
    return new Router\Aura();
};

// Expressive 2.X: We'll provide a default delegate:
$delegateFactory = new Container\NotFoundDelegateFactory();
$container['Zend\Expressive\Delegate\DefaultDelegate'] = $delegateFactory;
$container[Zend\Expressive\Delegate\NotFoundDelegate::class] = $delegateFactory;

// Expressive 2.X: We'll provide a not found handler:
$container[Zend\Expressive\Middleware\NotFoundHandler::class] = new Container\NotFoundHandlerFactory();

// Templating
// In most cases, you can instantiate the template renderer you want to use
// without using a factory:
$container[TemplateRendererInterface::class] = function ($container) {
    return new PlatesRenderer();
};

// These next two can be added in any environment; they won't be used unless:
// - (Expressive 1.X): you add the WhoopsErrorHandler as the FinalHandler
//   implementation:
// - (Expressive 2.X): you add the WhoopsErrorResponseGenerator as the
//   ErrorResponseGenerator implementation
$container['Zend\Expressive\Whoops'] = new Container\WhoopsFactory();
$container['Zend\Expressive\WhoopsPageHandler'] = new Container\WhoopsPageHandlerFactory();

// Error Handling

// - In Expressive 2.X, all environments:
$container['Zend\Expressive\Middleware\ErrorHandler'] = new Container\ErrorHandlerFactory();

// If in development:
// - Expressive 1.X:
$container['Zend\Expressive\FinalHandler'] = new Container\WhoopsErrorHandlerFactory();
// - Expressive 2.X:
$container[Zend\Expressive\Middleware\ErrorResponseGenerator::class] = new Container\WhoopsErrorResponseGeneratorFactory();

// If in production:
// - Expressive 1.X:
$container['Zend\Expressive\FinalHandler'] = new Container\TemplatedErrorHandlerFactory();
// - Expressive 2.X:
$container[Zend\Expressive\Middleware\ErrorResponseGenerator::class] = new Container\ErrorResponseGeneratorFactory();

return $container;
```

Your bootstrap (typically `public/index.php`) will then look like this:

```php
chdir(dirname(__DIR__));
$container = require 'config/container.php';
$app = $container->get(Zend\Expressive\Application::class);

// In Expressive 2.X:
require 'config/pipeline.php';
require 'config/routes.php';

// All versions:
$app->run();
```

> ### Environments
> 
> In the example above, we provide two alternate definitions for
> either the service `Zend\Expressive\FinalHandler` (Expressive 1.X) or the
> service `Zend\Expressive\Middleware\ErrorResponseGenerator` (Expressive 2.X),
> one for development and one for production. You will need to add logic to
> your file to determine which definition to provide; this could be accomplished
> via an environment variable.
