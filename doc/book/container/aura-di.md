# Using Aura.Di

[Aura.Di](https://github.com/auraphp/Aura.Di/) provides a serializable dependency
injection container with the following features:

- constructor and setter injection

- inheritance of constructor parameter and setter method values from parent classes

- inheritance of setter method values from interfaces and traits

- lazy-loaded instances, services, includes/requires, and values

- instance factories

- optional auto-resolution of typehinted constructor parameter values


## Installing Aura.Di

Aura.Di v3 only implements [container-interop](https://github.com/container-interop/container-interop).

```bash
$ composer require "aura/di:3.0.*@beta"
```

## Configuration

Aura.Di can help you to reorganize your code better with
[ContainerConfig classes](http://auraphp.com/packages/Aura.Di/config.html) and
[two step configuration](http://auraphp.com/blog/2014/04/07/two-stage-config/)
in this example, we'll have that in `config/services.php`.

```php
<?php
use Aura\Di\ContainerBuilder;

$container_builder = new ContainerBuilder();

// use the builder to create and configure a container
// using an array of ContainerConfig classes
// make sure the classes can be autoloaded
return $container_builder->newConfiguredInstance([
    'Application\_Config\Common',
]);
```

The bare minimal `ContainerConfig ` code needed to make zend-expressive work is

```php
<?php
namespace Application\_Config;

use Aura\Di\Container;
use Aura\Di\ContainerConfig;

class Common extends ContainerConfig
{
    public function define(Container $di)
    {
        $di->params['Aura\Router\RouteCollection'] = array(
            'route_factory' => $di->lazyNew('Aura\Router\RouteFactory'),
        );
        $di->params['Aura\Router\Router'] = array(
            'routes' => $di->lazyNew('Aura\Router\RouteCollection'),
            'generator' => $di->lazyNew('Aura\Router\Generator'),
        );
        $di->params['Zend\Expressive\Router\Aura']['router'] = $di->lazyNew('Aura\Router\Router');
        $di->set('Zend\Expressive\Router\RouterInterface', $di->lazyNew('Zend\Expressive\Router\Aura'));
        $di->set('Zend\Expressive\Container\ApplicationFactory', $di->lazyNew('Zend\Expressive\Container\ApplicationFactory'));
        $di->set('Zend\Expressive\Application', $di->lazyGetCall('Zend\Expressive\Container\ApplicationFactory', '__invoke', $di));

        // Templating
        // In most cases, you can instantiate the template renderer you want to use
        // without using a factory:
        $di->set('Zend\Expressive\Template\TemplateInterface', $di->lazyNew('Zend\Expressive\Template\Plates'));

        // These next two can be added in any environment; they won't be used unless
        // you add the WhoopsErrorHandler as the FinalHandler implementation:
        $di->set('Zend\Expressive\Container\WhoopsFactory', $di->lazyNew('Zend\Expressive\Container\WhoopsFactory'));
        $di->set('Zend\Expressive\Whoops', $di->lazyGetCall('Zend\Expressive\Container\WhoopsFactory', '__invoke', $di));
        $di->set('Zend\Expressive\Container\WhoopsPageHandlerFactory', $di->lazyNew('Zend\Expressive\Container\WhoopsPageHandlerFactory'));
        $di->set('Zend\Expressive\WhoopsPageHandler', $di->lazyGetCall('Zend\Expressive\Container\WhoopsPageHandlerFactory', '__invoke', $di));

        // Error Handling

        // If in development:
        $di->set('Zend\Expressive\Container\WhoopsErrorHandlerFactory', $di->lazyNew('Zend\Expressive\Container\WhoopsErrorHandlerFactory'));
        $di->set('Zend\Expressive\FinalHandler', $di->lazyGetCall('Zend\Expressive\Container\WhoopsErrorHandlerFactory', '__invoke', $di));

        // If in production:
        // $di->set('Zend\Expressive\FinalHandler', $di->lazyGetCall('Zend\Expressive\Container\TemplatedErrorHandlerFactory', '__invoke', $di));
    }

    public function modify(Container $di)
    {
        /*
        $router = $di->get('Zend\Expressive\Router\RouterInterface');
        $router->addRoute(new \Zend\Expressive\Router\Route('/hello/{name}', function ($request, $response, $next) {
            $escaper = new \Zend\Escaper\Escaper();
            $name = $request->getAttribute('name', 'World');
            $response->write('Hello ' . $escaper->escapeHtml($name));
            return $response;
        }, \Zend\Expressive\Router\Route::HTTP_METHOD_ANY, 'hello'));
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
