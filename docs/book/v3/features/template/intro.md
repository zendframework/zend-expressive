# Templating

By default, no middleware or handlers in Expressive are templated. We do not even
provide a default templating engine, as the choice of templating engine is often
very specific to the project and/or organization.

We do, however, provide abstraction for templating via the interface
`Zend\Expressive\Template\TemplateRendererInterface`, which allows you to write
middleware that is engine-agnostic. For Expressive, this means:

- All adapters MUST support template namespacing. Namespaces MUST be referenced
  using the notation `namespace::template` when rendering.
- Adapters MUST allow rendering templates that omit the extension; they will, of
  course, resolve to whatever default extension they require (or as configured).
- Adapters SHOULD allow passing an extension in the template name, but how that
  is handled is left up to the adapter.
- Adapters SHOULD abstract layout capabilities. Many templating systems provide
  this out of the box, or similar, compatible features such as template
  inheritance. This should be transparent to end-users; they should be able to
  simply render a template and assume it has the full content to return.

In this documentation, we'll detail the features of this interface, the various
implementations we provide, and how you can configure, inject, and consume
templating in your middleware.

We currently support:

- [Plates](plates.md)
- [Twig](twig.md)
- [zend-view](zend-view.md)

Each has an associated container factory; details are found in the
[factories documentation](../container/factories.md).
