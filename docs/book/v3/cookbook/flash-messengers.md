# How Can I Implement Flash Messages?

*Flash messages* are used to display one-time messages to a user. A typical use
case is for setting and later displaying a successful submission via a
[Post/Redirect/Get (PRG)](https://en.wikipedia.org/wiki/Post/Redirect/Get)
workflow, where the flash message would be set during the POST request, but
displayed during the GET request. (PRG is used to prevent double-submission of
forms.) As such, flash messages usually are session-based; the message is set in
one request, and accessed and cleared in another.

Expressive does not provide native session facilities out-of-the-box, which
means you will need:

- Session functionality.
- Flash message functionality, for handling message expiry from the session
  after first access.

A number of flash message libraries already exist that can be integrated via
middleware, and these typically either use PHP's ext/session functionality or
have a dependency on a session library. Two such libraries are:

- zendframework/zend-expressive-flash
- slim/flash

## zendframework/zend-expressive-flash

[zend-expressive-flash](https://docs.zendframework.com/zend-expressive-flash/)
is a new offering from Zend Framework. Using it requires a session persistence
engine as well, and Zend Framework provides that as well. Install the component
using the following:

```bash
$ composer require zendframework/zend-expressive-flash zendframework/zend-expressive-session-ext
```

Once installed, you will need to pipe the middleware, along with the
zend-expressive-session middleware, in your pipeline. This can be done at the
application level:

```php
$app->pipe(\Zend\Expressive\Session\SessionMiddleware::class);
$app->pipe(\Zend\Expressive\Flash\FlashMessageMiddleware::class);
```

or within a routed middleware pipeline:

```php
$app->post('/user/login', [
    \Zend\Expressive\Session\SessionMiddleware::class,
    \Zend\Expressive\Flash\FlashMessageMiddleware::class,
    LoginHandler::class,
]);
```

Once this is in place, the flash message container will be registered as a
request attribute, which you can then pull and manipulate:

```php
$flashMessages = $request->getAttribute(FlashMessageMiddleware::FLASH_ATTRIBUTE);
// or $flashMessages = $request->getAttribute('flash');

// Create a flash message for the next request:
$flashMessages->flash($messageName, $messageValue);

// Or retrieve them:
$message = $flashMessages->getFlash($messageName);
```

The component has functionality for specifying the number of hops the message
will be valid for, as well as accessing messages created in the current request;
[read more in the documentation](https://docs.zendframework.com/zend-expressive-flash/intro/).

## slim/flash

Slim's [Flash messages service provider](https://github.com/slimphp/Slim-Flash) can be
used in Expressive. It uses PHP's native session support.

First, you'll need to add it to your application:

```bash
$ composer require slim/flash
```

Second, create middleware that will add the flash message provider to the request:

```php
<?php
namespace App;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Slim\Flash\Messages;

class SlimFlashMiddleware implements MiddlewareInterface
{
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler) : ResponseInterface
    {
        // Start the session whenever we use this!
        session_start();

        return $handler->handle(
            $request->withAttribute('flash', new Messages())
        );
    }
}
```

Third, we will register the new middleware with our container as an invokable.
Edit either the file `config/autoload/dependencies.global.php` or
`config/autoload/middleware-pipeline.global.php` to add the following:

```php
return [
    'dependencies' => [
        'invokables' => [
            App\SlimFlashMiddleware::class => App\SlimFlashMiddleware::class,
            /* ... */
        ],
        /* ... */
    ],
];
```

Finally, let's register it with our middleware pipeline. For programmatic
pipelines, pipe the middleware somewhere, generally before the routing middleware:

```php
$app->pipe(App\SlimFlashMiddleware::class);
```

Or as part of a routed middleware pipeline:

```php
$app->post('/form/handler', [
    App\SlimFlashMiddleware::class,
    FormHandlerMiddleware::class,
]);
```

If using configuration-driven pipelines, edit
`config/autoload/middleware-pipeline.global.php` to make the following
additions:

```php
return [
    'middleware_pipeline' => [
        'always' => [
            'middleware' => [
                'App\SlimFlashMiddleware',
                /* ... */
            ],
            'priority' => 10000,
        ],
        /* ... */
    ],
];
```

> ### Where to register the flash middleware
>
> Sessions can sometimes be expensive. As such, you may not want the flash
> middleware enabled for every request. If this is the case, add the flash
> middleware as part of a route-specific pipeline instead, as demonstrated
> in the programmatic pipelines above.

From here, you can add and read messages by accessing the request's flash
attribute. As an example, middleware generating messages might read as follows:

```php
use Psr\Http\Server\RequestHandlerInterface;
use Zend\Diactoros\Response\RedirectResponse;

function($request, RequestHandlerInterface $handler)
{
    $flash = $request->getAttribute('flash');
    $flash->addMessage('message', 'Hello World!');

    return new RedirectResponse('/other-middleware');
}
```

And middleware consuming the message might read:

```php
use Psr\Http\Server\RequestHandlerInterface;

function($request, RequestHandlerInterface $handler)
{
    $flash = $request->getAttribute('flash');
    $messages = $flash->getMessages();
    // ...
}
```

From there, it's a matter of providing the flash messages to your template.
