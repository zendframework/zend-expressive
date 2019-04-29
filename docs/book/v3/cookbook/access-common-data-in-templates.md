# How Can I Access Common Data In Templates?

Templates often need access to common request data, such as request attributes,
the current route name, the currently authenticated user, and more. Wrangling
all of that data in every single handler, however, often leads to code
duplication, and the possibility of accidently omitting some of that data. How
can you make such data available to all templates?

The approach detailed in this recipe involves creating a middleware that calls
on the template renderer's `addDefaultParam()` method.

Foolowing is an example that injects the current user, the matched route name,
and all flash messages via a single middleware.

```php
// In src/App/Middleware/TemplateDefaultsMiddleware.php (flat structure), or
// in src/App/src/Middleware/TemplateDefaultsMiddleware.php (modular structure):
<?php

declare(strict_types=1);

namespace App\Middleware;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Zend\Expressive\Router\RouteResult;
use Zend\Expressive\Session\Authentication\UserInterface;
use Zend\Expressive\Flash\FlashMessagesInterface;
use Zend\Expressive\Flash\FlashMessageMiddleware;
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
        // Inject the current user, or null if there isn't one.
        $this->templateRenderer->addDefaultParam(
            TemplateRendererInterface::TEMPLATE_ALL,
            'security', // This is named security so it will not interfere with your user admin pages
            $request->getAttribute(UserInterface::class)
        );

        // Inject the currently matched route name.
        $routeResult = $request->getAttribute(RouteResult::class);
        $this->templateRenderer->addDefaultParam(
            TemplateRendererInterface::TEMPLATE_ALL,
            'matchedRouteName',
            $routeResult ? $routeResult->getMatchedRouteName() : null
        );

        // Inject all flash messages
        /** @var FlashMessagesInterface $flashMessages */
        $flashMessages = $request->getAttribute(FlashMessageMiddleware::FLASH_ATTRIBUTE);
        $this->templateRenderer->addDefaultParam(
            TemplateRendererInterface::TEMPLATE_ALL,
            'notifications',
            $flashMessages ? $flashMessages->getFlashes() : []
        );

        // Inject any other data you always need in all your templates...

        return $handler->handle($request);
    }
}
```

Next you need to create a factory for this middleware and register it with the
DI container; [zend-expressive-tooling](../reference/cli-tooling.md) provides
functionality for doing so:

```bash
$ ./vendor/bin/expressive factory:create "App\Middleware\TemplateDefaultsMiddleware"
```

Once the factory is created, you can add this to any route that may generate a
template:

```php
// In config/routes.php:
$app->get('/some/resource/{id}', [
    App\Middleware\TemplateDefaultsMiddleware::class,
    SomeResourceHandler::class,
]);
```

Alternately, if you want it to apply to any handler, place it in your
application pipeline immediately before the `DispatchMiddleware`:

```php
// In config/pipeline.php:
$app->pipe(App\Middleware\TemplateDefaultsMiddleware::class);
$app->pipe(DispatchMiddleware::class);
```

> Be aware, however, that if authentication is performed in per-handler
> pipelines, you will need to use the first approach to ensure that the
> authenticated user has been discovered.
