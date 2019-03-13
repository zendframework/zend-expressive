# Template Variable Container

> - Since zend-expressive-helpers 5.3.0

[zend-expressive-template](../template/intro.md) provides the method
[Zend\Expressive\Template\TemplateRendererInterface::addDefaultParam()](../template/interface.md#default-params)
for providing template variables that should be available to any template.

One common use case for this is to set things such as the current user, current
section of the website, currently matched route, etc. Unfortunately, because the
method changes the internal state of the renderer, this can cause problems in an
async environment, such as [Swoole](https://docs.zendframework.com/zend-expressive-swoole), 
where those changes will persist for parallel and subsequent requests.

To provide a stateless alternative, you can create a `Zend\Expressive\Helper\Template\TemplateVariableContainer`
and persist it as a request attribute. This allows you to set template variables
that are pipeline-specific, and later extract and merge them with
handler-specific values when rendering.

To facilitate this further, we provide `Zend\Expressive\Helper\Template\TemplateVariableContainerMiddleware`,
which will populate the attribute for you if it has not yet been.

The container is **immutable**, and any changes will result in a new instance.
As such, any middleware that is providing additional values or removing values
**must** call `$request->withAttribute()` to replace the instance, per the
examples below.

> ### When to use the TemplateVariableContainer
>
> If you are calling `addDefaultParam()` only in your factory for creating your
> template renderer instance, or within delegator factories on the renderer,
> you do not need to make any changes.
>
> If you are using our [Swoole integrations](https://docs.zendframework.com/zend-expressive-swoole)
> or other async application runners, and either currently or plan to set
> template parameters withing pipeline middleware you definitely need to use the
> TemplateVariableContainer in order to prevent state problems.
>
> We actually recommend using this approach even if you are not using Swoole or
> other async application runners, as the approach is more explicit and easily
> tested, and, as noted, does not depend on state within the renderer itself.

## Usage

As an example, consider the following pipeline:

```php
// In config/pipeline.php

use Psr\Container\ContainerInterface;
use Zend\Expressive\Application;
use Zend\Expressive\Handler\NotFoundHandler;
use Zend\Expressive\Helper\ServerUrlMiddleware;
use Zend\Expressive\Helper\Template\TemplateVariableContainerMiddleware;
use Zend\Expressive\Helper\UrlHelperMiddleware;
use Zend\Expressive\MiddlewareFactory;
use Zend\Expressive\Router\Middleware\DispatchMiddleware;
use Zend\Expressive\Router\Middleware\ImplicitHeadMiddleware;
use Zend\Expressive\Router\Middleware\ImplicitOptionsMiddleware;
use Zend\Expressive\Router\Middleware\MethodNotAllowedMiddleware;
use Zend\Expressive\Router\Middleware\RouteMiddleware;
use Zend\Stratigility\Middleware\ErrorHandler;

use function Zend\Stratigility\path;

return function (Application $app, MiddlewareFactory $factory, ContainerInterface $container) : void {
    $app->pipe(ErrorHandler::class);
    $app->pipe(ServerUrlMiddleware::class);

		// The following entry is specific to this example:
    $app->pipe(path(
        '/api/doc',
        $factory->lazy(TemplateVariableContainerMiddleware::class)
    ));

    $app->pipe(RouteMiddleware::class);

    $app->pipe(ImplicitHeadMiddleware::class);
    $app->pipe(ImplicitOptionsMiddleware::class);
    $app->pipe(MethodNotAllowedMiddleware::class);
    $app->pipe(UrlHelperMiddleware::class);

    $app->pipe(DispatchMiddleware::class);

    $app->pipe(NotFoundHandler::class);
};
```

Any middleware or handler that responds to a path beginning with `/api/doc` will
now have a `Zend\Expressive\Helper\Template\TemplateVariableContainer` attribute
that contains an instance of that class.

Within middleware that responds on that path, you can then do the following:

```php
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Zend\Expressive\Helper\Template\TemplateVariableContainer;
use Zend\Expressive\Router\RouteResult;

class InjectUserAndRouteVariablesMiddleware implements MiddlewareInterface
{
		public function process(
				ServerRequestInterface $request,
				 RequestHandlerInterface $handler
			) : ResponseInterface {
					$container = $request->getAttribute(
							TemplateVariableContainer::class,
							new TemplateVariableContainer()
					);

					// Since containers are immutable, we re-populate the request:
					$request = $request->withAttribute(
							TemplateVariableContainer::class,
							$container->merge([
									'user'  => $user,
									'route' => $request->getAttribute(RouteResult::class),
							])
					);

					return $handler->handle($request);
			}
}
```

In a handler, you will call `mergeForTemplate()` with any local variables you
want to use, including those that might override the defaults:

```php
use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\StreamFactoryInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Zend\Expressive\Helper\Template\TemplateVariableContainer;
use Zend\Expressive\Template\TemplateRendererInterface;

class SomeHandler implements RequestHandlerInterface
{
		private $renderer;
		private $responseFactory;
		private $streamFactory;

		public function __construct(
				TemplateRendererInterface $renderer,
				ResponseFactoryInterface $responseFactory,
				StreamFactoryInterface $streamFactory
		) {
				$this->renderer        = $renderer;
				$this->responseFactory = $responseFactory;
				$this->streamFactory   = $streamFactory;
		}

		public function handle(ServerRequestInterface $request) : ResponseInterface
		{
				$value = $request->getParsedBody()['key'] ?? null;

				$content = $this->renderer->render(
						'some::template',
						$request
								->getAttribute(TemplateVariableContainer::class)
								->mergeForTemplate([
										'local' => $value,
								])
				);

				$body = $this->streamFactory()->createStream($content);

				return $this->responseFactory()->createResponse(200)->withBody($body);
		}
}
```

The `TemplateVariableContainer` contains the following methods:

- `count() : int`: return a count of variables in the container.
- `get(string $key) : mixed`: return the value associated with `$key`; if not
  present, a `null` is returned.
- `has(string $key) : bool`: does the container have an entry associated with
  `$key`?
- `with(string $key, mixed $value) : self`: return a new container instance
  containing the key/value pair provided.
- `without(string $key) : self`: return a new container instance that does not
  contain the given `$key`.
- `merge(array $values) : self`: return a new container that merge the `$values`
  provided with those in the original container. This is useful for setting
  many values at once.
- `mergeForTemplate(array $values) : array`: merge `$values` with any values in
  the container, and return the result. This method has no side effects, and
  should be used when preparing variables to pass to the renderer.

## Route template variable middleware

> - Since zend-expressive-helpers 5.3.0

`Zend\Expressive\Helper\Template\RouteTemplateVariableMiddleware` will inject
the currently matched route into the [template variable container](#template-variable-container).

This middleware relies on the `TemplateVariableContainerMiddleware` preceding
it in the middleware pipeline, or having the `TemplateVariableContainer`
request attribute present; if neither is present, it will generate a new
instance.

It then populates the container's `route` parameter using the results of
retrieving the `Zend\Expressive\Router\RouteResult` request attribute; the value
will be either an instance of that class, or `null`.

Templates rendered using the container can then access that value, and test for
routing success/failure status, pull the matched route name, route, and/or
parameters from it.

This middleware can replace the [UrlHelperMiddleware](url-helper.md) in your
pipeline.
