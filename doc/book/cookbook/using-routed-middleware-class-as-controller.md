# Using Routed Middleware Class as Controller

If you are familiar with frameworks with provide controller with multi actions functionality, like in Zend Framework 1 and 2, you may want to apply it when you use Expressive as well. Usually, we need to define 1 routed middleware, 1 __invoke() with 3 parameters ( request, response, next ). If we need another specifics usage, we can create another routed middleware classes, for example on `album` crud, we need following middleware classes:

- AlbumPageIndex
- AlbumPageEdit
- AlbumPageAdd

What if we want to use only one middleware class which facilitate 3 pages above? We can with make request attribute with 'action' key via route config, and validate it in `__invoke()` method with ReflectionMethod.

Let say, we have the following route config:

```php
// ...
    'routes' => [
        [
            'name' => 'album',
            'path' => '/album[/:action][/:id]',
            'middleware' => Album\Action\AlbumPage::class,
            'allowed_methods' => ['GET'],
        ],
    ],
// ...
```

To avoid repetitive code for modifying `__invoke()` method, we can create an AbstractPage, like the following:

```php
namespace App\Action;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use ReflectionMethod;

abstract class AbstractPage
{
    public function __invoke($request, $response, callable $next = null)
    {
        $action = $request->getAttribute('action', 'index') . 'Action';

        if (method_exists($this, $action)) {
            $r = new ReflectionMethod($this, $action);
            $args = $r->getParameters();

            if (count($args) === 3
                && $args[0]->getType() == ServerRequestInterface::class
                && $args[1]->getType() == ResponseInterface::class
                && $args[2]->isCallable()
                && $args[2]->allowsNull()
            ) {
                return $this->$action($request, $response, $next);
            }
        }

        return $next($request, $response->withStatus(404), 'Page Not Found');
    }
}
```

> ### Note: For ReflectionMethod::getType() in PHP < 7
>
> You may need to use Zend\Code\Reflection\MethodReflection as the method getType() is not defined yet, by requiring via composer:
> ```
> composer require zendframework/zend-code:~2.5
```
>

In above abstract class with modified `__invoke()` method, we check if the action attribute, which default is 'index' if not provided, have 'Action' suffix, and the the method is exists within the middleware class with 3 parameters with parameters with parameter 1 as ServerRequestInterface, parameter 2 as ResponseInterface, and parameter 3 is a callable and allows null, otherwise, it will response 404 page.

So, what we need to do in out routed middleware class is extends the AbstractPage we created:

```php
namespace Album\Action;

use App\Action\AbstractPage;
use Zend\Diactoros\Response\HtmlResponse;
use Zend\Expressive\Template;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

class AlbumPage extends AbstractPage
{
    protected $template;    
    // you need to inject via factory
    public function __construct(Template\TemplateRendererInterface $template)
    { $this->template = $template; }

    public function indexAction(
        ServerRequestInterface $request,
        ResponseInterface $response,
        callable $next = null
    ) {
        return new HtmlResponse($this->template->render('album::album-page'));
    }

    public function addAction(
        ServerRequestInterface $request,
        ResponseInterface $response,
        callable $next = null
    ) {
        return new HtmlResponse($this->template->render('album::album-page-add'));
    }

    public function editAction(
        ServerRequestInterface $request,
        ResponseInterface $response,
        callable $next = null
    ) {
        $id = $request->getAttribute('id');
        if ($id === null) {
            throw new \InvalidArgumentException('id parameter must be provided');
        }

        return new HtmlResponse(
            $this->template->render('album::album-page-edit', ['id' => $id])
        );
    }
}
```

The rest is just create the view.
