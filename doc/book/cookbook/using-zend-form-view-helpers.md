# How can I use zend-form view helpers?

If you've selected zend-view as your preferred template renderer, you'll likely
want to use the various view helpers available in other components, such as:

- zend-form
- zend-i18n
- zend-navigation

By default, only the view helpers directly available in zend-view are available;
how can you add the others?

If you installed Expressive via the skeleton, the service
`Zend\View\HelperPluginManager` is registered for you, and represents the helper
plugin manager injected into the `PhpRenderer` instance. As such, you only need
to configure this. The question is: where?

You have three options:

- Replace the `HelperPluginManager` factory with your own; or
- Add a delegator factory to or extend the `HelperPluginManager` service to
  inject the additional helper configuration; or
- Add pipeline middleware that composes the `HelperPluginManager` and configures
  it.

## Replacing the HelperPluginManager factory

The zend-view integration provides `Zend\Expressive\ZendView\HelperPluginManagerFactory`,
and the Expressive skeleton registers it be default. The simplest solution for
adding other helpers is to replace it with your own. In your own factory, you
will *also* configure the plugin manager with the configuration from the
zend-form component (or whichever other components you wish to use).

```php
namespace Your\Application;

use Interop\Container\ContainerInterface;
use Zend\Form\View\HelperConfig as FormHelperConfig;
use Zend\ServiceManager\Config;
use Zend\View\HelperPluginManager;

class HelperPluginManagerFactory
{
    public function __invoke(ContainerInterface $container)
    {   
        $config = $container->has('config') ? $container->get('config') : []; 
        $config = isset($config['view_helpers']) ? $config['view_helpers'] : []; 
        $manager = new HelperPluginManager(new Config($config));
        $manager->setServiceLocator($container);

        // Add zend-form view helper configuration:
        $formConfig = new FormHelperConfig();
        $formConfig->configureServiceManager($manager);

        return $manager;                                                                                                                                                                                                                                                        
    }   
}
```

In your `config/autoload/templates.global.php` file, change the line that reads:

```php
Zend\View\HelperPluginManager::class => Zend\Expressive\ZendView\HelperPluginManagerFactory::class,
```

to instead read as:

```php
Zend\View\HelperPluginManager::class => Your\Application\HelperPluginManagerFactory::class,
```

This approach will work for any of the various containers supported.

## Delegator factories/service extension

[Delegator factories](http://framework.zend.com/manual/current/en/modules/zend.service-manager.delegator-factories.html)
and [service extension](https://github.com/silexphp/Pimple/tree/1.1#modifying-services-after-creation)
operate on the same principle: they intercept after the original factory was
called, and then operate on the generated instance, either modifying or
replacing it. We'll demonstrate this for zend-servicemanager and Pimple; at the
time of writing, we're unaware of a mechanism for doing so in Aura.Di.

### zend-servicemanager

You'll first need to create a delegator factory:

```php
namespace Your\Application;

use Zend\Form\View\HelperConfig;
use Zend\ServiceManager\DelegatorFactoryInterface;
use Zend\ServiceManager\ServiceLocatorInterface;

class FormHelpersDelegatorFactory
{
    public function createDelegatorWithName(
        ServiceLocatorInterface $container,
        $name,
        $requestedName,
        $callback
    ) {
        $helpers = $callback();
        $config = new HelperConfig();
        $config->configureServiceManager($helpers);
        return $helpers;
    }
}
```

The above creates an instance of the `Zend\Form\View\HelperConfig` class,
uses it to configure the already created `Zend\View\HelperPluginManager`
instance, and then returns the plugin manager instance.

From here, you'll add a `delegator_factories` configuration key in your
`config/autoload/templates.global.php` file:

```php
return [
    'dependencies' => [
        'delegator_factories' => [
            Zend\View\HelperPluginManager::class => [
                Your\Application\FormHelpersDelegatorFactory::class,
            ],
        ],
        /* ... */
    ],
    'templates' => [
        /* ... */
    ],
    'view_helpers' => [
        /* ... */
    ],
];
```

Note: delegator factories are keyed by the service they modify, and the value is
an *array* of delegator factories, to allow multiple such factories to be in
use.

### Pimple

For Pimple, we don't currently support configuration of service extensions, so
you'll need to edit the main container configuration file,
`config/container.php`. Place the following anywhere after the factories and
invokables are defined:

```php
// The following assumes you've added the following import statements to
// the start of the file:
// use Zend\Form\View\HelperConfig as FormHelperConfig;
// use Zend\View\HelperPluginManager;
$container[HelperPluginManager::class] = $container->extend(
    HelperPluginManager::class,
    function ($helpers, $container) {
        $config = new FormHelperConfig();
        $config->configureServiceManager($helpers);
        return $helpers;
    }
);
```

## Pipeline middleware

Another option is to use pipeline middleware. This approach will
require that the middleware execute on every request, which introduces (very
slight) performance overhead. However, it's a portable method that works
regardless of the container implementation you choose.

First, define the middleware:

```php
namespace Your\Application

use Zend\Form\View\HelperConfig as FormHelperConfig;
use Zend\View\HelperPluginManager;

class FormHelpersMiddleware
{
    private $helpers;

    public function __construct(HelperPluginManager $helpers)
    {
        $this->helpers = $helpers;
    }

    public function __invoke($request, $response, callable $next)
    {
        $config = new FormHelperConfig();
        $config->configureServiceManager($this->helpers);
        return $next($request, $response);
    }
}
```

You'll also need a factory for the middleware, to ensure it receives the
`HelperPluginManager`:

```php
namespace Your\Application

use Zend\View\HelperPluginManager;

class FormHelpersMiddlewareFactory
{
    public function __invoke($container)
    {
        return new FormHelpersMiddleware(
            $container->get(HelperPluginManager::class)
        );
    }
}
```

Now, register these in the file
`config/autoload/middleware-pipeline.global.php`:

```php
return [
    'dependencies' => [
        'factories' => [
            Your\Application\FormHelpersMiddleware::class => Your\Application\FormHelpersMiddlewareFactory::class
            /* ... */
        ],
        /* ... */
    ],
    'middleware_pipeline' => [
        ['middleware' => Your\Application\FormHelpersMiddleware::class, 'priority' => 1000],
        /* ... */
        Zend\Expressive\Container\ApplicationFactory::ROUTING_MIDDLEWARE,
        /* ... */
    ],
];
```

At that point, you're all set!

## Registering more helpers

What if you need to register helpers from multiple components?

You can do so using the same technique above. Better yet, do them all at once!

- If you chose to use delegator factories/service extension, do all helper
  configuration registrations for all components in the same factory.
- If you chose to use middleware, do all helper configuration registrations for
  all components in the same middleware.
