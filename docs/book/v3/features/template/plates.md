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
