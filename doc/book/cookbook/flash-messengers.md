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
have a dependency on a session library. Two such libraries are slim/flash and
damess/expressive-session-middleware.

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

use Interop\Http\ServerMiddleware\DelegateInterface;
use Interop\Http\ServerMiddleware\MiddlewareInterface;
use Psr\Http\Message\ServerRequestInterface;
use Slim\Flash\Messages;

class SlimFlashMiddleware implements MiddlewareInterface
{
    public function process(ServerRequestInterface $request, DelegateInterface $delegate)
    {
        // Start the session whenever we use this!
        session_start();

        return $delegate->process(
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
use Interop\Http\ServerMiddleware\DelegateInterface;
use Zend\Diactoros\Response\RedirectResponse;

function($request, DelegateInterface $delegate)
{
    $flash = $request->getAttribute('flash');
    $flash->addMessage('message', 'Hello World!');

    return new RedirectResponse('/other-middleware');
}
```

And middleware consuming the message might read:

```php
use Interop\Http\ServerMiddleware\DelegateInterface;

function($request, DelegateInterface $delegate)
{
    $flash = $request->getAttribute('flash');
    $messages = $flash->getMessages();
    // ...
}
```

From there, it's a matter of providing the flash messages to your template.

## damess/expressive-session-middleware and Aura.Session

[damess/expressive-session-middleware](https://github.com/dannym87/expressive-session-middleware)
provides middleware for initializing an
[Aura.Session](https://github.com/auraphp/Aura.Session) instance; Aura.Session
provides flash messaging capabilities as part of its featureset.

Install it via Composer:

```bash
$ composer require damess/expressive-session-middleware
```

In `config/autoload/dependencies.global.php`, add an entry for Aura.Session:

```php
return [
    'dependencies' => [
        'factories' => [
            Aura\Session\Session::class => DaMess\Factory\AuraSessionFactory::class,
            /* ... */
        ],
        /* ... */
    ],
];
```

In either `config/autoload/dependencies.global.php` or
`config/autoload/middleware-pipeline.global.php`, add a factory entry for the
`damess/expressive-session-middleware`:

```php
return [
    'dependencies' => [
        'factories' => [
            DaMess\Http\SessionMiddleware::class => DaMess\Factory\SessionMiddlewareFactory::class,
            /* ... */
        ],
        /* ... */
    ],
];
```

Finally, add it to your middleware pipeline. For programmatic pipelines:

```php
use DaMess\Http\SessionMiddleware;

$app->pipe(SessionMiddleware::class);
/* ... */
```

If using configuration-driven pipelines, edit `config/autoload/middleware-pipeline.global.php`
and add an entry for the new middleware:

```php
return [
    'middleware_pipeline' => [
        'always' => [
            'middleware' => [
                DaMess\Http\SessionMiddleware::class,
                /* ... */
            ],
            'priority' => 10000,
        ],
        /* ... */
    ],
];
```

> ### Where to register the session middleware
>
> Sessions can sometimes be expensive. As such, you may not want the session
> middleware enabled for every request. If this is the case, add the session
> middleware as part of a route-specific pipeline instead.

Once enabled, the `SessionMiddleware` will inject the Aura.Session instance into
the request as the `session` attribute; you can thus retrieve it within
middleware using the following:

```php
$session = $request->getAttribute('session');
```

To create and consume flash messages, use Aura.Session's
[flash values](https://github.com/auraphp/Aura.Session#flash-values). As
an example, the middleware that is processing a POST request might set a flash
message:

```php
use Interop\Http\ServerMiddleware\DelegateInterface;
use Zend\Diactoros\Response\RedirectResponse;

function($request, DelegateInterface $delegate)
{
    $session = $request->getAttribute('session');
    $session->getSegment(__NAMESPACE__)
            ->setFlash('message', 'Hello World!');

    return new RedirectResponse('/other-middleware');
}
```

Another middleware, to which the original middleware redirects, might look like
this:

```php
use Interop\Http\ServerMiddleware\DelegateInterface;

function($request, DelegateInterface $delegate)
{
    $session = $request->getAttribute('session');
    $message = $session->getSegment(__NAMESPACE__)
                       ->getFlash('message');
    // ...
}
```  

From there, it's a matter of providing the flash messages to your template.
