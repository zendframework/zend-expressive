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
out-of-the-box. Expressive abstracts this fact away, providing two facilities
for doing so:

- You may pass a layout template name or `Zend\View\Model\ModelInterface`
  instance representing the layout as the second argument to the constructor.
- You may pass a "layout" parameter during rendering, with a value of either a
  layout template name or a `Zend\View\Model\ModelInterface`
  instance representing the layout. Passing a layout this way will override any
  layout provided to the constructor.

In each case, the zend-view implementation will do a depth-first, recursive
render in order to provide content within the selected layout.

### Layout name passed to constructor

```php
use Zend\Expressive\Template\ZendView;

// Create the engine instance with a layout name:
$zendView = new PhpRenderer(null, 'layout');
```

### Layout view model passed to constructor

```php
use Zend\Expressive\Template\ZendView;
use Zend\View\Model\ViewModel;

// Create the layout view model:
$layout = new ViewModel([
    'encoding' => 'utf-8',
    'cssPath'  => '/css/prod/',
]);
$layout->setTemplate('layout');

// Create the engine instance with the layout:
$zendView = new PhpRenderer(null, $layout);
```

### Provide a layout name when rendering

```php
$content = $templates->render('blog/entry', [
    'layout' => 'blog',
    'entry'  => $entry,
]);
```

### Provide a layout view model when rendering

```php
use Zend\View\Model\ViewModel;

// Create the layout view model:
$layout = new ViewModel([
    'encoding' => 'utf-8',
    'cssPath'  => '/css/blog/',
]);
$layout->setTemplate('layout');

$content = $templates->render('blog/entry', [
    'layout' => $layout,
    'entry'  => $entry,
]);
```

## Recommendations

We recommend the following practices when using the zend-view adapter:

- If using a layout, create a factory to return the layout view model as a
  service; this allows you to inject it into middleware and add variables to it.
- While we support passing the layout as a rendering parameter, be aware that if
  you change engines, this may not be supported.
- While you can use alternate resolvers, not all of them will work with the
  `addPath()` implementation. As such, we recommend setting up resolvers and
  paths only during creation of the template adapter.
