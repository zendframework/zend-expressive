# How can I use zend-form view helpers?

If you've selected zend-view as your preferred template renderer, you'll likely
want to use the various view helpers available in other components, such as:

- zend-form
- zend-i18n
- zend-navigation

By default, only the view helpers directly available in zend-view are available;
how can you add the others?

## ConfigProvider

When you install zend-form, Composer should prompt you if you want to inject one
or more `ConfigProvider` classes, including those from zend-hydrator,
zend-inputfilter, and several others. Always answer "yes" to these; when you do,
a Composer plugin will add entries for their `ConfigProvider` classes to your
`config/config.php` file.

If for some reason you are not prompted, or chose "no" when answering the
prompts, you can add them manually. Add the following entries in the array used
to create your `ConfigAggregator` instance within `config/config.php`:

```php
    \Zend\Form\ConfigProvider::class,
    \Zend\InputFilter\ConfigProvider::class,
    \Zend\Filter\ConfigProvider::class,
    \Zend\Validator\ConfigProvider::class,
    \Zend\Hydrator\ConfigProvider::class,
```

If you installed Expressive via the skeleton, the service
`Zend\View\HelperPluginManager` is registered for you, and represents the helper
plugin manager injected into the `PhpRenderer` instance. This instance gets its
helper configuration from the `view_helpers` top-level configuration key &mdash;
which the zend-form `ConfigProvider` helps to populate!

At this point, all view helpers provided by zend-form are registered and ready
to use.

Alternative options to configure HelperPluginManager:

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

use Psr\Container\ContainerInterface;
use Zend\ServiceManager\Config;
use Zend\View\HelperPluginManager;

class HelperPluginManagerFactory
{
    public function __invoke(ContainerInterface $container)
    {
        $manager = new HelperPluginManager($container);

        $config = $container->has('config') ? $container->get('config') : [];
        $config = isset($config['view_helpers']) ? $config['view_helpers'] : [];
        (new Config($config))->configureServiceManager($manager);

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

[Delegator factories](https://docs.zendframework.com/zend-servicemanager/delegators/)
and [service extension](https://github.com/silexphp/Pimple/tree/1.1#modifying-services-after-creation)
operate on the same principle: they intercept after the original factory was
called, and then operate on the generated instance, either modifying or
replacing it. We'll demonstrate this for zend-servicemanager and Pimple; at the
time of writing, we're unaware of a mechanism for doing so in Aura.Di.

### zend-servicemanager

You'll first need to create a delegator factory:

```php
namespace Your\Application;

use Psr\Container\ContainerInterface;
use Zend\ServiceManager\Config;
use Zend\ServiceManager\DelegatorFactoryInterface;
use Zend\ServiceManager\ServiceLocatorInterface;

class FormHelpersDelegatorFactory
{
    /**
     * zend-servicemanager v3 support
     */
    public function __invoke(
        ContainerInterface $container,
        $name,
        callable $callback,
        array $options = null
    ) {
        $helpers = $callback();

        $config = $container->has('config') ? $container->get('config') : [];
        $config = new Config($config['view_helpers']);
        $config->configureServiceManager($helpers);
        return $helpers;
    }

    /**
     * zend-servicemanager v2 support
     */
    public function createDelegatorWithName(
        ServiceLocatorInterface $container,
        $name,
        $requestedName,
        $callback
    ) {
        return $this($container, $name, $callback);
    }
}
```

The above creates an instance of `Zend\ServiceManager\Config`, uses it to
configure the already created `Zend\View\HelperPluginManager` instance, and then
returns the plugin manager instance.

From here, you'll add a `delegators` configuration key in your
`config/autoload/templates.global.php` file:

```php
return [
    'dependencies' => [
        'delegators' => [
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
// use Zend\ServiceManager\Config as ServiceConfig;
// use Zend\View\HelperPluginManager;
$container[HelperPluginManager::class] = $container->extend(
    HelperPluginManager::class,
    function ($helpers, $container) {
        $config = isset($container['config']) ? $container['config'] : [];
        $config = new ServiceConfig($config['view_helpers']);
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

use Interop\Http\ServerMiddleware\DelegateInterface;
use Interop\Http\ServerMiddleware\MiddlewareInterface;
use Psr\Http\Message\ServerRequestInterface;
use Zend\Form\View\HelperConfig as FormHelperConfig;
use Zend\View\HelperPluginManager;

class FormHelpersMiddleware implements MiddlewareInterface
{
    private $helpers;

    public function __construct(HelperPluginManager $helpers)
    {
        $this->helpers = $helpers;
    }

    public function process(ServerRequestInterface $request, DelegateInterface $delegate)
    {
        $config = new FormHelperConfig();
        $config->configureServiceManager($this->helpers);
        return $delegate->process($request);
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

Next, register the middleware with its factory in one of
`config/autoload/middleware-pipeline.global.php` or
`config/autoload/dependencies.global.php`:

```php
return [
    'dependencies' => [
        'factories' => [
            Your\Application\FormHelpersMiddleware::class => Your\Application\FormHelpersMiddlewareFactory::class
            /* ... */
        ],
        /* ... */
    ],
];
```

If using programmatic pipelines, pipe the middleware in an appropriate location
in your pipeline:

```php
$app->pipe(FormHelpersMiddleware::class);

// or, perhaps, in a route-specific middleware pipeline:
$app->post('/register', [
    FormHelpersMiddleware::class,
    RegisterMiddleware::class,
], 'register');
```

If using configuration-driven pipelines or routing:

```
// Via the middleware pipeline:
'middleware_pipeline' => [
    ['middleware' => Your\Application\FormHelpersMiddleware::class, 'priority' => 1000],
],

// Or via routes:
'routes' => [
    [
        'name'            => 'register',
        'path'            => '/register',
        'middleware'      => [
            FormHelpersMiddleware::class,
            RegisterMiddleware::class,
        ],
        'allowed_methods' => ['POST'],
    ]
]
```

At that point, you're all set!

## Registering more helpers

What if you need to register helpers from multiple components?

You can do so using the same technique above. Better yet, do them all at once!

- If you chose to use delegator factories/service extension, do all helper
  configuration registrations for all components in the same factory.
- If you chose to use middleware, do all helper configuration registrations for
  all components in the same middleware.
