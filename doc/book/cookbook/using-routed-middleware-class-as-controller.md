# Handling multiple routes in a single class

Typically, in Expressive, we would define one middleware class per route. For a
standard CRUD-style application, however, this leads to multiple related
classes:

- AlbumPageIndex
- AlbumPageEdit
- AlbumPageAdd

If you are familiar with frameworks that provide controllers capable of handling
multiple "actions", such as those found in Zend Framework's MVC layer, Symfony,
CodeIgniter, CakePHP, and other popular frameworks, you may want to apply a
similar pattern when using Expressive.

In other words, what if we want to use only one middleware class to facilitate
all three of the above?

In the following example, we'll use an `action` routing parameter which our
middleware class will use in order to determine which internal method to invoke.

Consider the following route configuration:

```php
use Album\Action\AlbumPage;

// Programmatic:
$app->get('/album[/{action:add|edit}[/{id}]]', AlbumPage::class, 'album');

// Config-driven:
return [
    /* ... */
    'routes' => [
        /* ... */
        [
            'name'            => 'album',
            'path'            => '/album[/{action:add|edit}[/{id}]]',
            'middleware'      => AlbumPage::class,
            'allowed_methods' => ['GET'],
        ],
        /* ... */
    ],
];
```
The above each define a route that will match any of the following:

- `/album`
- `/album/add`
- `/album/edit/3`

The `action` attribute can thus be one of `add` or `edit`, and we can optionally
also receive an `id` attribute (in the latter example, it would be `3`).

> ## Routing definitions may vary
>
> Depending on the router you chose when starting your project, your routing
> definition may differ. The above example uses the default `FastRoute`
> implementation.

We might then implement `Album\Action\AlbumPage` as follows:

```php
<?php
namespace Album\Action;

use Fig\Http\Message\StatusCodeInterface as StatusCode;
use Interop\Http\ServerMiddleware\DelegateInterface;
use Interop\Http\ServerMiddleware\MiddlewareInterface;
use Psr\Http\Message\ServerRequestInterface;
use Zend\Diactoros\Response\EmptyResponse;
use Zend\Diactoros\Response\HtmlResponse;
use Zend\Expressive\Template\TemplateRendererInterface;

class AlbumPage implements MiddlewareInterface
{
    private $template;    

    public function __construct(TemplateRendererInterface $template)
    {
        $this->template = $template;
    }

    public function process(ServerRequestInterface $request, DelegateInterface $delegate)
    {
        switch ($request->getAttribute('action', 'index')) {
            case 'index':
                return $this->indexAction($request, $delegate);
            case 'add':
                return $this->addAction($request, $delegate);
            case 'edit':
                return $this->editAction($request, $delegate);
            default:
                // Invalid; thus, a 404!
                return new EmptyResponse(StatusCode::STATUS_NOT_FOUND);
        }
    }

    public function indexAction(ServerRequestInterface $request, DelegateInterface $delegate)
    {
        return new HtmlResponse($this->template->render('album::album-page'));
    }

    public function addAction(ServerRequestInterface $request, DelegateInterface $delegate)
    {
        return new HtmlResponse($this->template->render('album::album-page-add'));
    }

    public function editAction(ServerRequestInterface $request, DelegateInterface $delegate)
    {
        $id = $request->getAttribute('id', false);
        if (! $id) {
            throw new \InvalidArgumentException('id parameter must be provided');
        }

        return new HtmlResponse(
            $this->template->render('album::album-page-edit', ['id' => $id])
        );
    }
}
```

This allows us to have the same dependencies for a set of related actions, and,
if desired, even have common internal methods each can utilize.

This approach is reasonable, but requires that I create a similar `process()`
implementation every time I want to accomplish a similar workflow. Let's create
a generic implementation, via an `AbstractPage` class:

```php
<?php
namespace App\Action;

use Fig\Http\Message\StatusCodeInterface as StatusCode;
use Interop\Http\ServerMiddleware\DelegateInterface;
use Interop\Http\ServerMiddleware\MiddlewareInterface;
use Psr\Http\Message\ServerRequestInterface;
use Zend\Diactoros\Response\EmptyResponse;

abstract class AbstractPage implements MiddlewareInterface
{
    public function process(ServerRequestInterface $request, DelegateInterface $delegate)
    {
        $action = $request->getAttribute('action', 'index') . 'Action';

        if (! method_exists($this, $action)) {
            return new EmptyResponse(StatusCode::STATUS_NOT_FOUND);
        }

        return $this->$action($request, $delegate);
    }
}
```

The above abstract class pulls the `action` attribute on invocation, and
concatenates it with the word `Action`. It then uses this value to determine if
a corresponding method exists in the current class, and, if so, calls it with
the arguments it received; otherwise, it returns an empty 404 response.

Our original `AlbumPage` implementation could then be modified to extend
`AbstractPage`:

```php
namespace Album\Action;

use App\Action\AbstractPage;
use Interop\Http\ServerMiddleware\DelegateInterface;
use Psr\Http\Message\ServerRequestInterface;
use Zend\Diactoros\Response\HtmlResponse;
use Zend\Expressive\Template\TemplateRendererInterface;

class AlbumPage extends AbstractPage
{
    private $template;    

    public function __construct(TemplateRendererInterface $template)
    {
        $this->template = $template;
    }

    public function indexAction( /* ... */ ) { /* ... */ }
    public function addAction( /* ... */ ) { /* ... */ }
    public function editAction( /* ... */ ) { /* ... */ }
}
```

> ## Or use a trait
>
> As an alternative to an abstract class, you could define the `__invoke()`
> logic in a trait, which you then compose into your middleware:
>
> ```php
> <?php
> namespace App\Action;
> 
> use Fig\Http\Message\StatusCodeInterface as StatusCode;
> use Interop\Http\ServerMiddleware\DelegateInterface;
> use Psr\Http\Message\ServerRequestInterface;
> use Zend\Diactoros\Response\EmptyResponse;
> 
> trait ActionBasedInvocation
> {
>     public function process(ServerRequestInterface $request, DelegateInterface $delegate)
>     {
>         $action = $request->getAttribute('action', 'index') . 'Action';
> 
>         if (! method_exists($this, $action)) {
>             return new EmptyResponse(StatusCode::STATUS_NOT_FOUND);
>         }
> 
>         return $this->$action($request, $delegate);
>     }
> }
> ```
>
> You would then compose it into a class as follows:
>
> ```php
> <?php
> namespace Album\Action;
> 
> use App\Action\ActionBasedInvocation;
> use Zend\Expressive\Template\TemplateRendererInterface;
> 
> class AlbumPage
> {
>     use ActionBasedInvocation;
> 
>     private $template;    
> 
>     public function __construct(TemplateRendererInterface $template)
>     {
>         $this->template = $template;
>     }
> 
>     public function indexAction( /* ... */ ) { /* ... */ }
>     public function addAction( /* ... */ ) { /* ... */ }
>     public function editAction( /* ... */ ) { /* ... */ }
> }
> ```
