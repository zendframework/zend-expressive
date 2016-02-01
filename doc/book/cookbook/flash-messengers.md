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

Once you have, you'll need to create a factory to return middleware that will
add the flash message provider to the request:

```php
namespace App;

use Slim\Flash\Messages;

class SlimFlashMiddlewareFactory
{
    public function __invoke($container)
    {
        return function ($request, $response, $next) {
            // Start the session whenever we use this!
            session_start();

            return $next(
                $request->withAttribute('flash', new Messages()),
                $response
            );
        };
    }
}
```

Now, let's register it with our middleware pipeline. In
`config/autoload/middleware-pipeline.global.php`, make the following additions:

```php
return [
    'dependencies' => [
        'factories' => [
            'App\SlimFlashMiddleware' => App\SlimFlashMiddlewareFactory::class,
            /* ... */
        ],
        /* ... */
    ],
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
> Sessions can sometimes be expensive. As such, you may not want the falsh
> middleware enabled for every request. If this is the case, add the flash
> middleware as part of a route-specific pipeline instead.

From here, you can add and read messages by accessing the request's flash
attribute. As an example, middleware generating messages might read as follows:

```php
use Zend\Diactoros\Response\RedirectResponse;

public function __invoke($request, $response, $next)
{
    $flash = $request->getAttribute('flash');
    $flash->addMessage('message', 'Hello World!');

    return RedirectResponse('/other-middleware')
}
```

And middleware consuming the message might read:

```php
public function __invoke($request, $response, $next)
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

In `config/autoload/middleware-pipeline.global.php`, add a factory entry for the
`damess/expressive-session-middleware`, and add it to the middleware pipeline:

```php
return [
    'dependencies' => [
        'factories' => [
            DaMess\Http\SessionMiddleware::class => DaMess\Factory\SessionMiddlewareFactory::class,
            /* ... */
        ],
        /* ... */
    ],
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
use Zend\Diactoros\Response\RedirectResponse;

public function __invoke($request, $response, $next)
{
    $session = $request->getAttribute('session');
    $session->getSegment(__NAMESPACE__)
            ->setFlash('message', 'Hello World!');

    return RedirectResponse('/other-middleware')
}
```

Another middleware, to which the original middleware redirects, might look like
this:

```php
public function __invoke($request, $response, $next)
{
    $session = $request->getAttribute('session');
    $message = $session->getSegment(__NAMESPACE__)
                       ->getFlash('message');
    // ...
}
```  

From there, it's a matter of providing the flash messages to your template.
