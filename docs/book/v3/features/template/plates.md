# Using Plates

[Plates](https://github.com/thephpleague/plates) is a native PHP template system
maintained by [The League of Extraordinary Packages](http://thephpleague.com).
it provides:

- Layout facilities.
- Template inheritance.
- Helpers for escaping, and the ability to provide custom helper extensions.

We provide a [TemplateRendererInterface](interface.md) wrapper for Plates via
`Zend\Expressive\Plates\PlatesRenderer`.

## Installing Plates

To use the Plates wrapper, you must install the Plates integration:

```bash
$ composer require zendframework/zend-expressive-platesrenderer
```

## Using the wrapper

If instantiated without arguments, `Zend\Expressive\Plates\PlatesRenderer` will create
an instance of the Plates engine, which it will then proxy to.

```php
use Zend\Expressive\Plates\PlatesRenderer;

$renderer = new PlatesRenderer();
```

Alternately, you can instantiate and configure the engine yourself, and pass it
to the `Zend\Expressive\Plates\PlatesRenderer` constructor:

```php
use League\Plates\Engine as PlatesEngine;
use Zend\Expressive\Plates\PlatesRenderer;

// Create the engine instance:
$plates = new PlatesEngine();

// Configure it:
$plates->addFolder('error', 'templates/error/');
$plates->loadExtension(new CustomExtension());

// Inject:
$renderer = new PlatesRenderer($plates);
```

## Configuration and Factory

zend-expressive-platesrenderer ships with the factory
`Zend\Expressive\Plates\PlatesRendererFactory`, which will both create the
Plates engine instance, and the `PlatesRenderer` instance. If you are using
[zend-component-installer](https://docs.zendframework.com/zend-component-installer/),
this will be automatically wired for you during installation.

The factory looks for the following configuration in the `config` service, using
any it finds:

```php
// In config/autoload/templates.global.php:

return [
    'plates' => [
        'extensions' => [
            // string service names or class names of Plates extensions
        ],
    ],
    'templates' => [
        'extension' => 'phtml', // change this if you use a different file
                                // extension for templates
        'paths' => [
            // namespace => [paths] pairs
        ],
    ],
];
```

The factory will also inject two extensions by default,
`Zend\Expressive\Plates\Extension\UrlExtension` and
`Zend\Expressive\Plates\Extension\EscaperExtension`, both listed in more detail
below.

## Shipped Extensions

zend-expressive-plates provides the following extensions.

### UrlExtension

`Zend\Expressive\Plates\Extension\UrlExtension` composes each of the
[UrlHelper](../helpers/url-helper.md) and [ServerUrlHelper](../helpers/server-url-helper),
and provides the following template methods:

```php
public function url(
   string $routeName = null,
   array $routeParams = [],
   array $queryParams = [],
   ?string $fragmentIdentifier = null,
   array $options = []
) : string;

public function serverurl(string $path = null) : string;

// Since zend-expressive-platesrender 2.1.0:
public function route() : ?Zend\Expressive\Router\RouteResult
```

As an example:

```php
<a href="<?= $this->url('blog', ['stub' => $this->stub]) ?>">A blog post on this</a>

<?php
$routing        = $this->route();
$routingIsValid = $routing && $routing->isSuccess();
$route       = $routingIsValid ? $routing->getMatchedRouteName() : 'blog';
$routeParams = $routingIsValid ? $routing->getMatchedParams() : [];
?>
<a href="<?= $this->url($route, $routeParams) ?>">For more information</a>
```

### EscaperExtension

`Zend\Expressive\Plates\Extension\EscaperExtension` proxies to functionality
provided in the [zend-escaper](https://docs.zendframework.com/zend-escaper/)
package. It looks for the following configuration in the `config` service:

```php
// In config/autoload/templates.global.php:

return [
    'plates' => [
        'encoding' => ?string, // character encoding of generated content
    ],
];
```

By default it assumes UTF-8 for the encoding.

The extension registers the following template methods:

```php
public function escapeHtml(string $html) : string;
public function escapeHtmlAttr(string $attribute) : string;
public function escapeJs(string $js) : string;
public function escapeCss(string $css) : string;
public function escapeUrl(string $url) : string;
```

As examples:

```php
<html>
  <head>
    <meta name="author" content="<?= $this->escapeHmtmlAttr($this->author) ?>">
    <link rel="alternative" href="<?= $this->escapeUrl($this->alternative) ?>">
    <style><?= $this->escapeCss($this->styles) ?></style>
    <script><?= $this->escapeJs($this->script) ?></script>
  </head>

  <body>
    <?= $this->escapeHtml($this->content) ?>
  </body>
</html>
```
