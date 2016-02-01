# How can I use Flash Messenger?

If you want to apply Flash Messenger, as example, you can use [damess/expressive-session-middleware](https://github.com/dannym87/expressive-session-middleware) that provide Session middleware for Zend Expressive using Aura Session.

To install and configure, you can follow [its README](https://github.com/dannym87/expressive-session-middleware/blob/master/README.md). When everything ok, you can do following in your routed middleware.

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

In other routed middleware, you can call it as follows:

```php
public function __invoke($request, $response, $next)
{
    $session = $request->getAttribute('session');
    $message = $session->getSegment(__NAMESPACE__)
                       ->getFlash('message');
    // ...
}
```  
