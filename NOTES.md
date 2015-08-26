# Expressive API brainstorming/notes

> ## Out-of-date
>
> The various use case notes below were brainstorming ideas that were then used
> to create test cases and help guide implementation. In the end, direct
> instantiation of Application was undesirable in order to promote proper IoC;
> this was when AppFactory was introduced.
>
> Consider them an historical record, and not actual usage examples; those can
> be found in [doc/book/usage-examples.md](doc/book/usage-examples.md) at this time.
>
> The sections on "Templated Middleware", "Middleware for any method", and
> "Design concerns" remain relevant still, and detail decisions made or still in
> progress.

## Hello world

```php
<?php
use Zend\Expressive\Application;

require __DIR__ . '/../vendor/autoload.php';

$app = new Application();

$app->get('/', function ($req, $res, $next) {
    $res->write('Hello, world!');
    return $res;
});

$app->run();
```

## Hello world, container version, basic

- `public/index.php`:

```php
<?php
use Zend\ServiceManager\Config;
use Zend\ServiceManager\ServiceManager;

require __DIR__ . '/../vendor/autoload.php';

$services = new ServiceManager();
$config = new Config(require 'config/services.php');
$config->configureServiceManager($services);

$app = $services->get('Zend\Expressive\Application');

$app->run();
```

- `config/services.php`:

```php
<?php
return [
    'factories' => [
        'Zend\Expressive\Application' => 'Application\ApplicationFactory',
    ],
];
```

- `src/ApplicationFactory.php`:

```php
<?php
namespace Application;

use Zend\Expressive\Application;

class ApplicationFactory
{
    public function __invoke($services)
    {
        $app = new Application();

        // Setup the application programatically within the factory
        $app->get('/', function ($req, $res, $next) {
            $res->write('Hello, world!');
            return $res;
        });

        return $app;
    }
}
```

## Hello world, container version, all services defined

- `public/index.php`:

```php
<?php
use Zend\ServiceManager\Config;
use Zend\ServiceManager\ServiceManager;

require __DIR__ . '/../vendor/autoload.php';

$services = new ServiceManager();
$config = new Config(require 'config/services.php');
$config->configureServiceManager($services);

$app = $services->get('Zend\Expressive\Application');

$app->run();
```

- `config/services.php`:

```php
<?php
return [
    'services' => [
        'config' => require __DIR__ . '/config.php',
    ],
    'factories' => [
        'Application\Middleware\HelloWorld' => 'Application\Middleware\HelloWorldFactory',
        'Zend\Expressive\Application' => 'Application\ApplicationFactory',
        'Zend\Expressive\Router\RouterInterface' => 'Application\RouterFactory',
    ],
];
```

- `config/config.php`:

```php
<?php
return [
    'routes' => [
        'home' => [
            'url'        => '/',
            'middleware' => 'Application\Middleware\HelloWorld',
        ],
    ],
];
```

- `src/RouterFactory.php`:

```php
<?php
namespace Application;

use Zend\Expressive\Router\Aura as AuraRouter;

class RouterFactory
{
    public function __invoke($services)
    {
        $config = $services->has('config') ? $services->get('config') : [];
        $router = new AuraRouter();
        $router->setConfig($config);

        return $router;
    }
}
```

- `src/ApplicationFactory.php`:

```php
<?php
namespace Application;

use Zend\Expressive\Application;

class ApplicationFactory
{
    public function __invoke($services)
    {
        // Router injected at instantiation
        $router = $services->get('Zend\Expressive\Router\RouterInterface');
        return new Application($router);
    }
}
```

- `src\Middleware\HelloWorldFactory`:

```php
<?php
namespace Application\Middleware;

class HelloWorldFactory
{
    public function __invoke($services)
    {
        // Returning a class instance:
        return new HelloWorld();

        // or returning a closure:
        return function ($req, $res, $next) {
            $res->write('Hello, world!');
            return $res;
        };
    }
}
```

## Hello world, container version, hybrid

Same example as above, but we'll add more routes in the application factory.

```php
<?php
namespace Application;

use Zend\Diactoros\Response\JsonResponse;
use Zend\Expressive\Application;

class ApplicationFactory
{
    public function __invoke($services)
    {
        // Router injected at instantiation
        $router = $services->get('Zend\Expressive\RouterInterface');
        $app = new Application($router);

        $app->get('/ping', function ($req, $res, $next) {
            return new JsonResponse(['ack' => time()]);
        });

        return $app;
    }
}
```

## Templated middleware

I'd originally thought we could return a view model, but that breaks the
middleware contract. Instead, my thought is one of the following:

- "Templated" response that has no renderer. A "Templated response emitter"
  would take the response metadata, pass it to a template renderer, and write to
  the response to return it.

```php
<?php
// middleware would do this:
$middleware = function ($req, $res, $next) {
    return new TemplatedResponse($template, $variables);
};

// Emitter might do this:
class TemplatedResponseEmitter
{
    /**
     * We'd have to typehint on the PSR-7 interface, but this is just a simple
     * illustration of the workflow.
     */
    public function emit(TemplatedResponse $response)
    {
        $content = $this->renderer->render($response->getTemplate, $response->getVariables());

        // This is operating under the assumption of a two-pass render such as
        // ZF2's PhpRenderer. Systems such as phly/mustache, league/plates, and
        // twig allow inheritance, which would obviate the need for this.
        if ($this->hasLayout()) {
            $content = $this->renderer->render($this->getLayout(), [
                $this->getContentKey() => $content,
            ]);
        }

        $response->getBody()->write($content);

        $this->parent->emit($response);
    }
}
```

- Or, similarly, the templated response emitter would inject the stream with the
  renderer prior to attempting to emit the response; the act of injection would
  render the template and populate the stream.

```php
<?php
// middleware would do this:
$middleware = function ($req, $res, $next) {
    return new TemplatedResponse($template, $variables);
};

// Emitter might do this:
class TemplatedResponseEmitter
{
    /**
     * We'd have to typehint on the PSR-7 interface, but this is just a simple
     * illustration of the workflow.
     */
    public function emit(TemplatedResponse $response)
    {
        $response->setRenderer($this->renderer);
        $this->parent->emit($response);
    }
}
```

- Alternately, and more simply, the middleware can be injected with the template
  renderer, and the onus is on the user to render the template into the response
  and return the response.

```php
<?php
class CustomMiddleware
{
    private $renderer;

    public function __construct(RendererInterface $renderer)
    {
        $this->renderer = $renderer;
    }

    public function __invoke($req, $res, $next)
    {
        $res->write($this->renderer->render('some/template', ['some' => 'vars']));
        return $res;

        // or:
        return new HtmlResponse($this->renderer->render(
            'some/template',
            ['some' => 'vars']
        ));
    }
}
```

My feeling is that the last is simplest from each of an implementation and
usability standpoint. However, if we go this route, we will need to provide:

- An abstract class that accepts the template renderer via the constructor or a
  setter, and/or an "Aware" interface.
- A reusable factory that templated middleware can use that will inject the
  template renderer, and/or a delegator factory, so that users will not be
  required to write such a factory.

My inclination is to use interface injection here.

## Middleware for any method

Middleware for any method is already possible, using `pipe()`. However, we would
want to overload this in the `Application` class such that it creates a route
definition. In order to keep the same semantics, I suggest:

- `route($routeOrPath, $middleware = null, array $methods = null)`. Given a
  `Route` instance, it just attaches that route. Given the path and
  middleware, it creates a `Route` instance that can listen on any HTTP method;
  providing an array of `$methods` will limit to those methods.
- The various HTTP-method Application methods would delegate to `route()`.

This means that a route will minimally contain:

- URL (what needs to match in the URI to execute the middleware)
- Middleware (a callable or service name for the middleware to execute on match)
- HTTP methods the middleware can handle.

Additionally, it MAY need:

- Options (any other options/metadata regarding the route to pass on to the
  router)

Finally, by having `route()` return the `Route` instance, the user can further
customize it. I would argue that *only* options be mutable, however, as the
combination of path + HTTP method is what determines whether or not routes have
conflicts.

## Design Concerns

- How do we allow attaching middleware to execute on *every* request?

  The simplest solution is to *not* handle it in the `Application`. The reason
  is simple: otherwise we have to worry about when the dispatcher is registered
  with the pipeline. If we do it at instantiation, we cannot have middleware
  intercept prior to the dispatcher; if we do it at invocation, we cannot have
  middleware or error middleware that executes after.  Since `pipe()` has no
  concept of priority, and is simply a queue, the ony solution that will give
  consistent results is:
  
  - Register the dispatcher middleware at instantiation
  - Require that users compose an `Application` in another `MiddlewarePipe` if
    they want pre/post middleware:

    ```php
<?php
use Zend\Diactoros\Server;
use Zend\Expressive\Application;
use Zend\Stratigility\MiddlewarePipe;

$app = new Application();
$app->get('/foo', function ($req, $res, $next) {
    // ...
});

$middleware = new MiddlewarePipe();

$middleware->pipe(function ($req, $res, $next) {
    // This will execute first!
});
$middleware->pipe($app); // middle!
$middleware->pipe(function ($req, $res, $next) {
    // This will execute if the middleware in $app calls $next()!
});
$middleware->pipe(function ($err, $req, $res, $next) {
    // Error middleware!
});

$server = Server::createServer($middleware);
$server->listen();
    ```

  This approach requires more setup and documentation, but ensures consistency
  and predictability.

- How do we handle errors? What if the application is wrapped in other
  middleware?

  My suggestion is we require developers to inject error middleware into the
  Application, via `pipe()` and/or via an `injectFinalHandler()` method. We can
  then use it for `$out` if none is passed, and otherwise delegate handling to
  the parent middleware. We should provide a _default_ error handler
  implementation that will be used if `$out` is `null` and no final handler is
  injected.
  
- How do we define middleware that should match a specific URI, but have it
  wrapped in other middleware? As an example, in Apigility, we might want the
  actual handler to be nested inside of listeners for authentication,
  authorization, content negotiation, validation, etc. Likely we can do this via
  delegator factories, but what if we could specify decorators during route
  creation?

- Should Route instances allow manipulating HTTP methods *after the fact*? This
  will likely lead to wierd edge-cases where HTTP methods were added that
  overlap methods on other routes with the same path. I think they MUST be
  required at instantiation and remain immutable. (Refactored to incorporate
  this in 2ccd3381)

- How do we handle the default, simplest use case, where no DI is required?

  ```php
  <?php
  use Zend\Expressive\Application;
  $app = new Application();
  $app->get(/* ... */);
  $app->run();
  ```

  In such a situation, several assumptions are made:

  - The dispatcher is present.
  - The dispatcher has a router injected already.
  - An emitter is present and/or the application is passing itself to
    `Zend\Diactoros\Server` and calling `listen()`.

  I'd argue that we should have a factory for this instead:

  ```php
  <?php
  use Zend\Expressive\Application;

  $app = Application::create();
  $app->get(/* ... */);
  $app->run();
  ```

  The method could even allow passing a container for pulling the dispatcher
  (and router, and emitter).

- How should we handle emitting the response? The simplest solution would be to
  delegate to `Zend\Diactoros\Server::listen`, as that will handle the most
  common use cases. However, one idea I've discussed before with Enrico is
  having a strategy-based approach:

  ```php
  <?php
  use Zend\Diactoros\Response\SapiEmitter;

  $emitter = new EmitterMap();
  // The following would likely be present by default, and be the fallback
  // if a response type is not known.
  $emitter->map('Psr\Http\Message\ResponseInterface', new SapiEmitter());

  // But we could then add maps for our specific response types:
  $emitter->map('GeneratorResponse', new GeneratorEmitter());
  $emitter->map('TemplatedResponse', new TemplatedEmitter());

  // The following would type-hint on Zend\Diactoros\Response\EmitterInterface:
  $app->setEmitter($emitter);
  ```

  Alternately, a stack could be used instead; the first to return a known
  response (e.g., `EmitterStack::IS_COMPLETE`) would short-circuit so that the
  next emitters in the stack do not trigger. This would allow for emitters that
  test on the composed stream, for instance. By implementing as a stack, it
  could register the SapiEmitter as the default. Stack exhaustion assumes the
  response was emitted.

  Both approaches allow more flexibility than using `Server::listen()`.

  Because an `Application` is simply middleware, the emitter is _not_ required
  for all paths, only when using `run()`. `Application::create()` _should_
  create and inject an implementation, however.
