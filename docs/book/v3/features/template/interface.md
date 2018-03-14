# The Template Renderer Interface

Expressive defines `Zend\Expressive\Template\TemplateRendererInterface`, which can be
injected into middleware in order to create templated response bodies. The
interface is defined as follows:

```php
namespace Zend\Expressive\Template;

interface TemplateRendererInterface
{
    public const TEMPLATE_ALL = '*';

    /**
     * Render a template, optionally with parameters.
     *
     * Implementations MUST support the `namespace::template` naming convention,
     * and allow omitting the filename extension.
     *
     * @param array|object $params
     */
    public function render(string $name, $params = []) : string;

    /**
     * Add a template path to the engine.
     *
     * Adds a template path, with optional namespace the templates in that path
     * provide.
     */
    public function addPath(string $path, string $namespace = null) : void;

    /**
     * Retrieve configured paths from the engine.
     *
     * @return TemplatePath[]
     */
    public function getPaths() : array;

    /**
     * Add a default parameter to use with a template.
     *
     * Use this method to provide a default parameter to use when a template is
     * rendered. The parameter may be overridden by providing it when calling
     * `render()`, or by calling this method again with a null value.
     *
     * The parameter will be specific to the template name provided. To make
     * the parameter available to any template, pass the TEMPLATE_ALL constant
     * for the template name.
     *
     * If the default parameter existed previously, subsequent invocations with
     * the same template name and parameter name will overwrite.
     *
     * @param string $templateName Name of template to which the param applies;
     *     use TEMPLATE_ALL to apply to all templates.
     * @param mixed $value
     */
    public function addDefaultParam(string $templateName, string $param, $value) : void;
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

### Default params

The `TemplateRendererInterface` defines the method `addDefaultParam()`. This
method can be used to specify default parameters to use when rendering a
template. The signature is:

```php
public function addDefaultParam($templateName, $param, $value)
```

If you want a parameter to be used for *every* template, you can specify the
constant `TemplateRendererInterface::TEMPLATE_ALL` for the `$templateName`
parameter.

When rendering, parameters are considered in the following order, with later
items having precedence over earlier ones:

- Default parameters specified for all templates.
- Default parameters specified for the template specified at rendering.
- Parameters specified when rendering.

As an example, if we did the following:

```php
$renderer->addDefaultParam($renderer::TEMPLATE_ALL, 'foo', 'bar');
$renderer->addDefaultParam($renderer::TEMPLATE_ALL, 'bar', 'baz');
$renderer->addDefaultParam($renderer::TEMPLATE_ALL, 'baz', 'bat');

$renderer->addDefaultParam('example', 'foo', 'template default foo');
$renderer->addDefaultParam('example', 'bar', 'template default bar');

$content = $renderer->render('example', [
    'foo' => 'override',
]);
```

Then we can expect the following substitutions will occur when rendering:

- References to the "foo" variable will contain "override".
- References to the "bar" variable will contain "template default bar".
- References to the "baz" variable will contain "bat".

> #### Support for default params
>
> The support for default params will often be renderer-specific. The reason is
> because the `render()` signature does not specify a type for `$params`, in
> order to allow passing alternative arguments such as view models. In such
> cases, the implementation will indicate its behavior when default parameters
> are specified, but a given `$params` argument does not support it.
>
> At the time of writing, each of the Plates, Twig, and zend-view
> implementations support the feature.
