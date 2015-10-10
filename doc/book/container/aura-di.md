# Using Aura.Di

[Aura.Di](https://github.com/auraphp/Aura.Di/) provides a serializable dependency
injection container with the following features:

- constructor and setter injection.
- inheritance of constructor parameter and setter method values from parent
  classes.
- inheritance of setter method values from interfaces and traits.
- lazy-loaded instances, services, includes/requires, and values.
- instance factories.
- optional auto-resolution of typehinted constructor parameter values.

## Installing Aura.Di

Aura.Di only implements [container-interop](https://github.com/container-interop/container-interop)
as of version 3 (in beta at the time of writing).

```bash
$ composer require "aura/di:3.0.*@beta"
```

## Configuration

Aura.Di can help you to organize your code better with
[ContainerConfig classes](http://auraphp.com/packages/Aura.Di/config.html) and
[two step configuration](http://auraphp.com/blog/2014/04/07/two-stage-config/).
In this example, we'll put that in `config/services.php`:

```php
<?php
use Aura\Di\ContainerBuilder;

$containerBuilder = new ContainerBuilder();

// Use the builder to create and configure a container using an array of
// ContainerConfig classes. Make sure the classes can be autoloaded!
return $containerBuilder->newConfiguredInstance([
    'Application\Config\Common',
]);
```

The bare minimum `ContainerConfig` code needed to make zend-expressive work is:

```php
<?php
// In src/Config/Common.php:
namespace Application\Config;

use Aura\Di\Container;
use Aura\Di\ContainerConfig;
use Aura\Router\Generator;
use Aura\Router\RouteCollection;
use Aura\Router\RouteFactory;
use Aura\Router\Router;
use Zend\Escaper\Escaper;
use Zend\Expressive\Application;
use Zend\Expressive\Container;
use Zend\Expressive\Router\AuraRouter;
use Zend\Expressive\Router\Route;
use Zend\Expressive\Router\RouterInterface;
use Zend\Expressive\Template;

class Common extends ContainerConfig
{
    public function define(Container $di)
    {
        $di->params[RouteCollection::class] = array(
            'route_factory' => $di->lazyNew(RouteFactory::class),
        );
        $di->params[Router::class] = array(
            'routes' => $di->lazyNew(RouteCollection::class),
            'generator' => $di->lazyNew(Generator::class),
        );
        $di->params[AuraRouter::class]['router'] = $di->lazyNew(Router::class);
        $di->set(RouterInterface::class, $di->lazyNew(AuraRouter::class));
        $di->set(Container\ApplicationFactory::class, $di->lazyNew(Container\ApplicationFactory::class));
        $di->set(Application::class, $di->lazyGetCall(Container\ApplicationFactory::class, '__invoke', $di));

        // Templating
        // In most cases, you can instantiate the template renderer you want to use
        // without using a factory:
        $di->set(Template\TemplateRendererInterface::class, $di->lazyNew(Template\Plates::class));

        // These next two can be added in any environment; they won't be used unless
        // you add the WhoopsErrorHandler as the FinalHandler implementation:
        $di->set(Container\WhoopsFactory::class, $di->lazyNew(Container\WhoopsFactory::class));
        $di->set('Zend\Expressive\Whoops', $di->lazyGetCall(Container\WhoopsFactory::class, '__invoke', $di));
        $di->set(Container\WhoopsPageHandlerFactory::class, $di->lazyNew(Container\WhoopsPageHandlerFactory::class));
        $di->set('Zend\Expressive\WhoopsPageHandler', $di->lazyGetCall(Container\WhoopsPageHandlerFactory::class, '__invoke', $di));

        // Error Handling

        // If in development:
        $di->set(Container\WhoopsErrorHandlerFactory::class, $di->lazyNew(Container\WhoopsErrorHandlerFactory::class));
        $di->set('Zend\Expressive\FinalHandler', $di->lazyGetCall(Container\WhoopsErrorHandlerFactory::class, '__invoke', $di));

        // If in production:
        // $di->set('Zend\Expressive\FinalHandler', $di->lazyGetCall(Container\TemplatedErrorHandlerFactory::class, '__invoke', $di));
    }

    public function modify(Container $di)
    {
        /*
        $router = $di->get(RouterInterface::class);
        $router->addRoute(new Route('/hello/{name}', function ($request, $response, $next) {
            $escaper = new Escaper();
            $name = $request->getAttribute('name', 'World');
            $response->write('Hello ' . $escaper->escapeHtml($name));
            return $response;
        }, Route::HTTP_METHOD_ANY, 'hello'));
        */
    }
}
```

Your bootstrap (typically `public/index.php`) will then look like this:

```php
chdir(dirname(__DIR__));
require 'vendor/autoload.php';
$container = require 'config/services.php';
$app = $container->get('Zend\Expressive\Application');
$app->run();
```
