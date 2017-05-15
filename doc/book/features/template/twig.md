# Using Twig

[Twig](http://twig.sensiolabs.org/) is a template language and engine provided
as a standalone component by SensioLabs. It provides:

- Layout facilities.
- Template inheritance.
- Helpers for escaping, and the ability to provide custom helper extensions.

We provide a [TemplateRendererInterface](interface.md) wrapper for Twig via
`Zend\Expressive\Twig\TwigRenderer`.

## Installing Twig

To use the Twig wrapper, you must first install the Twig integration:

```bash
$ composer require zendframework/zend-expressive-twigrenderer
```

## Using the wrapper

If instantiated without arguments, `Zend\Expressive\Twig\TwigRenderer` will create
an instance of the Twig engine, which it will then proxy to.

```php
use Zend\Expressive\Twig\TwigRenderer;

$renderer = new TwigRenderer();
```

Alternately, you can instantiate and configure the engine yourself, and pass it
to the `Zend\Expressive\Twig\TwigRenderer` constructor:

```php
use Twig_Environment;
use Twig_Loader_Array;
use Zend\Expressive\Twig\TwigRenderer;

// Create the engine instance:
$loader = new Twig_Loader_Array(include 'config/templates.php');
$twig = new Twig_Environment($loader);

// Configure it:
$twig->addExtension(new CustomExtension());
$twig->loadExtension(new CustomExtension();

// Inject:
$renderer = new TwigRenderer($twig);
```

## Included extensions and functions

The included Twig extension adds support for url generation. The extension is
automatically activated if the [UrlHelper](../helpers/url-helper.md) and
[ServerUrlHelper](../helpers/server-url-helper.md) are registered with the
container.

The following template functions are exposed:

- ``path``: Render the relative path for a given route and parameters. If there
  is no route, it returns the current path.

  ```twig
  {{ path('article_show', {'id': '3'}) }}
  Generates: /article/3
  ```

- ``url``: Render the absolute url for a given route with its route parameters,
  query string arguments, and fragment. If there is no route, it returns the
  current url.

  ```twig
  {{ url('article_show', {'id': '3'}, {'foo': 'bar'}, 'fragment') }}
  Generates: http://example.com/article/3?foo=bar#fragment
  ```

- ``absolute_url``: Render the absolute url from a given path. If the path is
  empty, it returns the current url.

  ```twig
  {{ absolute_url('path/to/something') }}
  Generates: http://example.com/path/to/something
  ```

- ``asset`` Render an (optionally versioned) asset url.

  ```twig
  {{ asset('path/to/asset/name.ext', version=3) }}
  Generates: path/to/asset/name.ext?v=3
  ```

  To get the absolute url for an asset:

  ```twig
  {{ absolute_url(asset('path/to/asset/name.ext', version=3)) }}
  Generates: http://example.com/path/to/asset/name.ext?v=3
  ```

## Configuration

The following details configuration specific to Twig, as consumed by the
`TwigRendererFactory`:

```php
return [
    'templates' => [
        'extension' => 'file extension used by templates; defaults to html.twig',
        'paths' => [
            // namespace / path pairs
            //
            // Numeric namespaces imply the default/main namespace. Paths may be
            // strings or arrays of string paths to associate with the namespace.
        ],
    ],
    'twig' => [
        'cache_dir' => 'path to cached templates',
        'assets_url' => 'base URL for assets',
        'assets_version' => 'base version for assets',
        'extensions' => [
            // extension service names or instances
        ],
        'globals' => [
            // Global variables passed to twig templates
            'ga_tracking' => 'UA-XXXXX-X'
        ],
    ],
];
```

When specifying the `twig.extensions` values, always use fully qualified class
names or actual extension instances to ensure compatibility with any version of
Twig used. Version 2 of Twig _requires_ that a fully qualified class name is
used, and not a short-name alias.
