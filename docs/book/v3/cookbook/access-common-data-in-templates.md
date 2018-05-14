# How Can I Access Common Data In Templates?

How can I make frequently used data like request attributes, the current route 
name, etc. available in all template. All that is needed is a middleware and
the `addDefaultParam()` method from the template renderer.

Here is an example on how to inject the current user, matched route name and
all flash messages with one middleware.

```php
<?php

declare(strict_types=1);

namespace App\Middleware;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Zend\Expressive\Router\RouteResult;
use Zend\Expressive\Session\Authentication\UserInterface;
use Zend\Expressive\Session\Flash\FlashMessagesInterface;
use Zend\Expressive\Template\TemplateRendererInterface;

class TemplateDefaultsMiddleware implements MiddlewareInterface
{
    /** @var TemplateRendererInterface */
    private $templateRenderer;

    public function __construct(TemplateRendererInterface $templateRenderer)
    {
        $this->templateRenderer = $templateRenderer;
    }

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler) : ResponseInterface
    {
        // Inject the current user or null if there isn't one
        $this->templateRenderer->addDefaultParam(
            TemplateRendererInterface::TEMPLATE_ALL,
            'security', // This is named security so it will not interfere with your user admin pages
            $request->getAttribute(UserInterface::class)
        );

        // Inject the current matched route name
        $routeResult = $request->getAttribute(RouteResult::class);
        $this->templateRenderer->addDefaultParam(
            TemplateRendererInterface::TEMPLATE_ALL,
            'matchedRouteName',
            $routeResult ? $routeResult->getMatchedRouteName() : null
        );

        /** @var FlashMessagesInterface $flashMessages */
        $flashMessages = $request->getAttribute(FlashMessagesInterface::class);
        // Inject all flash messages
        $this->templateRenderer->addDefaultParam(
            TemplateRendererInterface::TEMPLATE_ALL,
            'notifications',
            $flashMessages ? $flashMessages->getFlashes() : []
        );

        // Inject any other data you always need in all your templates

        return $handler->handle($request);
    }
}
```

Next you need to create a factory and register it. You can generate a factory with
[zend-expressive-tooling](../reference/cli-tooling.md):

```bash
$ ./vendor/bin/expressive factory:create App\Middleware\TemplateDefaultsMiddleware
```
