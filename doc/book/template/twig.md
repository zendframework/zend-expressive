# Using Twig

[Twig](http://twig.sensiolabs.org/) is a template langauge and engine provided
as a standalone component by SensioLabs. It provides:

- Layout facilities.
- Template inheritance.
- Helpers for escaping, and the ability to provide custom helper extensions.

We provide a [TemplateInterface](interface.md) wrapper for Plates via
`Zend\Expressive\Template\Twig`.

## Installing Twig

To use the Twig wrapper, you must first install Twig

```bash
$ composer require twig/twig
```

## Using the wrapper

If instantiated without arguments, `Zend\Expressive\Template\Twig` will create
an instance of the Twig engine, which it will then proxy to.

```php
use Zend\Expressive\Template\Twig;

$templates = new Twig();
```

Alternately, you can instantiate and configure the engine yourself, and pass it
to the `Zend\Expressive\Template\Twig` constructor:

```php
use Twig_Environment;
use Twig_Loader_Array;
use TwigTemplate;
use Zend\Expressive\Template\Twig;

// Create the engine instance:
$loader = new Twig_Loader_Array(include 'config/templates.php');
$twig = new TwigTemplate(new Twig_Environment($loader));

// Configure it:
$twig->addExtension(new CustomExtension());
$twig->loadExtension(new CustomExtension();

// Inject:
$templates = new Twig($twig);
```
