# The Template Interface

Expressive defines `Zend\Expressive\Template\TemplateInterface`, which can be
injected into middleware in order to create templated response bodies. The
interface is defined as follows:

```php
namespace Zend\Expressive\Template;

interface TemplateInterface
{
    /**
     * @param string $name
     * @param array|object $params
     * @return string
     */
    public function render($name, $params = []);

    /**
     * @param string $path
     * @param string $namespace
     */
    public function addPath($path, $namespace = null);

    /**
     * @return TemplatePath[]
     */
    public function getPaths();
}
```

> ### Namespaces
>
> Unfortunately, namespace syntax varies between different template engine
> implementations. As an example:
>
> - Plates uses the syntax `namespace::template`
> - Twig uses the syntax `@namespace/template`
> - zend-view does not natively support namespaces; we mimic it using normal
>   directory syntax.
>
> As such, it's not entirely possible to have engine-agnostic templates if you
> use namespaces.


## Paths

Most template engines and implementations will require that you specify one or
more paths to templates; these are then used when resolving a template name to
the actual template. You may use the `addPath()` method to do so:

```php
$template->addPath('templates');
```

Many template engines further allow *namespacing* templates; when adding a path,
you specify the template *namespace* that it fulfills, and the engine will only
return a template from that path if the namespace provided matches the namespace
for the path.

```php
// Resolves to a path registered with the namespace "error";
// this example is specific to the Plates engine.
$content = $template->render('error::404');
```

You can provide a namespace when registering a path via an optional second
argument:

```php
// Registers the "error" namespace to the path "templates/error/"
$template->addPath('templates/error/', 'error');
```

## Rendering

To render a template, call the `render()` method. This method requires the name
of a template as the first argument:

```php
$content = $template->render('foo');
```

One key reason to use templates, however, is to dynamically provide data to
inject in the template. You may do so by passing either an associative array or
an object as the second argument to `render()`:

```php
$content = $template->render('message', [
    'greeting'  => 'Hello',
    'recipient' => 'World',
]);
```

It is up to the underlying template engine to determine how to perform the
injections.
