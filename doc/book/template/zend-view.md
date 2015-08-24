# Using zend-view

[zend-view](https://github.com/zendframework/zend-view) provides a native PHP
template system via its `PhpRenderer`, and is maintained by Zend Framework. It
provides:

- Layout facilities.
- Helpers for escaping, and the ability to provide custom helper extensions.

We provide a [TemplateInterface](interface.md) wrapper for zend-view's
`PhpRenderer` via `Zend\Expressive\Template\ZendView`.

## Installing zend-view

To use the zend-view wrapper, you must first install zend-view

```bash
$ composer require zendframework/zend-view
```

## Using the wrapper

If instantiated without arguments, `Zend\Expressive\Template\ZendView` will create
an instance of the `PhpRenderer`, which it will then proxy to.

```php
use Zend\Expressive\Template\ZendView;

$templates = new ZendView();
```

Alternately, you can instantiate and configure the engine yourself, and pass it
to the `Zend\Expressive\Template\ZendView` constructor:

```php
use Zend\Expressive\Template\ZendView;
use Zend\View\Renderer\PhpRenderer;
use Zend\View\Resolver;

// Create the engine instance:
$zendView = new PhpRenderer();

// Configure it:
$resolver = new Resolver\AggregateResolver();
$resolver->attach(
    new Resolver\TemplateMapResolver(include 'config/templates.php'),
    100
);
$resolver->attach(
    (new Resolver\TemplatePathStack())
    ->setPaths(include 'config/template_paths.php')
);
$zendView->setResolver($resolver);

// Inject:
$templates = new ZendView($zendView);
```

## Layouts

Unlike the other supported template engines, zend-view does not support layouts
out-of-the-box.

Layouts are accomplished in one of two ways:

- Multiple rendering passes:

  ```php
  $content = $templates->render('blog/entry', [ 'entry' => $entry ]);
  $layout  = $templates->render('layout/layout', [ 'content' => $content ]);
  ```

- View models.  To accomplish this, you will compose a view model for the
  content, and pass it as a value to the layout:

  ```php
  use Zend\View\Model\ViewModel;
  
  $viewModel = new ViewModel(['entry' => $entry]);
  $viewModel->setTemplate('blog/entry');
  $layout = $templates->render('layout/layout', [ 'content' => $viewModel ]);
  ```
