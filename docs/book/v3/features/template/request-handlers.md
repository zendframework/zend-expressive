# Templated request handlers

The primary use case for templating is within request handlers, to provide templated
responses. To do this, you will:

- Inject an instance of `Zend\Expressive\Template\TemplateRendererInterface` into your
  request handler.
- Potentially add paths to the templating instance.
- Render a template.
- Add the results of rendering to your response.

## Injecting a TemplateRendererInterface

We encourage the use of dependency injection. As such, we recommend writing your
request handler to accept the `TemplateRendererInterface` via either the constructor or a
setter. As an example:

```php
namespace Acme\Blog;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Zend\Expressive\Template\TemplateRendererInterface;

class EntryHandler implements RequestHandlerInterface
{
    private $templateRenderer;

    public function __construct(TemplateRendererInterface $renderer)
    {
        $this->templateRenderer = $renderer;
    }

    public function handler(ServerRequestInterface $request) : ResponseInterface
    {
        // ...
    }
}
```

This will necessitate having a factory for your request handler:

```php
namespace Acme\Blog\Container;

use Acme\Blog\EntryHandler;
use Psr\Container\ContainerInterface;
use Zend\Expressive\Template\TemplateRendererInterface;

class EntryHandlerFactory
{
    public function __invoke(ContainerInterface $container)
    {
        return new EntryHandler(
            $container->get(TemplateRendererInterface::class)
        );
    }
}
```

And, of course, you'll need to tell your container to use the factory; see the
[container documentation](../container/intro.md) for more information on how you
might accomplish that.

## Consuming templates

Now that we have the templating engine injected into our request handler, we can
consume it. Most often, we will want to render a template, optionally with
substitutions to pass to it. This will typically look like the following:

```php
namespace Acme\Blog;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Zend\Diactoros\Response\HtmlResponse;
use Zend\Expressive\Template\TemplateRendererInterface;

class EntryHandler implements RequestHandlerInterface
{
    private $templateRenderer;

    public function __construct(TemplateRendererInterface $renderer)
    {
        $this->templateRenderer = $renderer;
    }

    public function handle(ServerRequestInterface $request) : ResponseInterface
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
