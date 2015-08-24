# Templating

By default, no middleware in Expressive is templated. We do not even
provide a default templating engine, as the choice of templating engine is often
very specific to the project and/or organization.

We do, however, provide abstraction for templating via the interface
`Zend\Expressive\Template\TemplateInterface`, which allows you to write
middleware that is engine-agnostic. In this documentation, we'll detail the
features of this interface, the various implementations we provide, and how you
can configure, inject, and consume templating in your middleware.
