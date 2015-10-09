# The Template Interface

Expressive defines `Zend\Expressive\Template\TemplateRendererInterface`, which can be
injected into middleware in order to create templated response bodies. The
interface is defined as follows:

```php
namespace Zend\Expressive\Template;

interface TemplateRendererInterface
{
    /**
     * Render a template, optionally with parameters.
     *
     * Implementations MUST support the `namespace::template` naming convention,
     * and allow omitting the filename extension.
     *
     * @param string $name
     * @param array|object $params
     * @return string
     */
    public function render($name, $params = []);

    /**
     * Add a template path to the engine.
     *
     * Adds a template path, with optional namespace the templates in that path
     * provide.
     *
     * @param string $path
     * @param string $namespace
     */
    public function addPath($path, $namespace = null);

    /**
     * Add a template path to the engine.
     *
     * Adds a template path, with optional namespace the templates in that path
     * provide.
     *
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
> - Plates uses the syntax `namespace::template`.
> - Twig uses the syntax `@namespace/template`.
> - zend-view does not natively support namespaces, though custom resolvers
>   can provide the functionality.
>
> To make different engines compatible, we require implementations to support
> the syntax `namespace::template` (where `namespace::` is optional) when
> rendering. Additionally, we require that engines allow omitting the filename
> suffix.
>
> When using a `TemplateRendererInterface` implementation, feel free to use namespaced
> templates, and to omit the filename suffix; this will make your code portable
> and allow it to use alternate template engines.


## Paths

Most template engines and implementations will require that you specify one or
more paths to templates; these are then used when resolving a template name to
the actual template. You may use the `addPath()` method to do so:

```php
$renderer->addPath('templates');
```

Template engines adapted for zend-expressive are also required to allow
*namespacing* templates; when adding a path, you specify the template
*namespace* that it fulfills, and the engine will only return a template from
that path if the namespace provided matches the namespace for the path.

```php
// Resolves to a path registered with the namespace "error";
// this example is specific to the Plates engine.
$content = $renderer->render('error::404');
```

You can provide a namespace when registering a path via an optional second
argument:

```php
// Registers the "error" namespace to the path "templates/error/"
$renderer->addPath('templates/error/', 'error');
```

## Rendering

To render a template, call the `render()` method. This method requires the name
of a template as the first argument:

```php
$content = $renderer->render('foo');
```

You can specify a namespaced template using the syntax `namespace::template`;
the `template` segment of the template name may use additional directory
separators when necessary.

One key reason to use templates is to dynamically provide data to inject in the
template. You may do so by passing either an associative array or an object as
the second argument to `render()`:

```php
$content = $renderer->render('message', [
    'greeting'  => 'Hello',
    'recipient' => 'World',
]);
```

It is up to the underlying template engine to determine how to perform the
injections.
