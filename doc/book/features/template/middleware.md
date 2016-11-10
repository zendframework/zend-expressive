# Templated Middleware

The primary use case for templating is within middleware, to provide templated
responses. To do this, you will:

- Inject an instance of `Zend\Expressive\Template\TemplateRendererInterface` into your
  middleware.
- Potentially add paths to the templating instance.
- Render a template.
- Add the results of rendering to your response.

## Injecting a TemplateRendererInterface

We encourage the use of dependency injection. As such, we recommend writing your
middleware to accept the `TemplateRendererInterface` via either the constructor or a
setter. As an example:

```php
namespace Acme\Blog;

use Zend\Expressive\Template\TemplateRendererInterface;

class EntryMiddleware
{
    private $templateRenderer;

    public function __construct(TemplateRendererInterface $renderer)
    {
        $this->templateRenderer = $renderer;
    }

    public function __invoke($request, $response, $next)
    {
        // ...
    }
}
```

This will necessitate having a factory for your middleware:

```php
namespace Acme\Blog\Container;

use Acme\Blog\EntryMiddleware;
use Interop\Container\ContainerInterface;

class EntryMiddlewareFactory
{
    public function __invoke(ContainerInterface $container)
    {
        return new EntryMiddleware(
            $container->get('Zend\Expressive\Template\TemplateRendererInterface')
        );
    }
}
```

And, of course, you'll need to tell your container to use the factory; see the
[container documentation](../container/intro.md) for more information on how you
might accomplish that.

## Consuming templates

Now that we have the templating engine injected into our middleware, we can
consume it. Most often, we will want to render a template, optionally with
substitutions to pass to it. This will typically look like the following:

```php
<?php
namespace Acme\Blog;

use Zend\Diactoros\Response\HtmlResponse;
use Zend\Expressive\Template\TemplateRendererInterface;

class EntryMiddleware
{
    private $templateRenderer;

    public function __construct(TemplateRendererInterface $renderer)
    {
        $this->templateRenderer = $renderer;
    }

    public function __invoke($request, $response, $next)
    {
        // do some work...
        return new HtmlResponse(
            $this->templateRenderer->render('blog::entry', [
                'entry' => $entry,
            ])
        );
    }
}
```

Alternately, you can write to the composed response:

```php
$response->getBody()->write($this->templateRenderer->render('blog::entry', [
    'entry' => $entry,
]));
return $response;
```
