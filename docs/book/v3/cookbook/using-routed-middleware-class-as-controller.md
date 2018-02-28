# Handling multiple routes in a single class

Typically, in Expressive, we would a single request handler class per route. For
a standard CRUD-style application, however, this leads to multiple related
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

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Zend\Diactoros\Response\EmptyResponse;
use Zend\Diactoros\Response\HtmlResponse;
use Zend\Expressive\Template\TemplateRendererInterface;

class AlbumPage implements RequestHandlerInterface
{
    private $template;

    public function __construct(TemplateRendererInterface $template)
    {
        $this->template = $template;
    }

    public function handle(ServerRequestInterface $request) : ResponseInterface
    {
        switch ($request->getAttribute('action', 'index')) {
            case 'index':
                return $this->indexAction($request);
            case 'add':
                return $this->addAction($request);
            case 'edit':
                return $this->editAction($request);
            default:
                // Invalid; thus, a 404!
                return new EmptyResponse(StatusCode::STATUS_NOT_FOUND);
        }
    }

    public function indexAction(ServerRequestInterface $request) : ResponseInterface
    {
        return new HtmlResponse($this->template->render('album::album-page'));
    }

    public function addAction(ServerRequestInterface $request) : ResponseInterface
    {
        return new HtmlResponse($this->template->render('album::album-page-add'));
    }

    public function editAction(ServerRequestInterface $request) : ResponseInterface
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

This approach is reasonable, but requires that I create a similar `handle()`
implementation every time I want to accomplish a similar workflow. Let's create
a generic implementation, via an `AbstractPage` class:

```php
<?php
namespace App\Action;

use Fig\Http\Message\StatusCodeInterface as StatusCode;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\http\Server\RequestHandlerInterface;
use Zend\Diactoros\Response\EmptyResponse;

abstract class AbstractPage implements RequestHandlerInterface
{
    public function handle(ServerRequestInterface $request) : ResponseInterface
    {
        $action = $request->getAttribute('action', 'index') . 'Action';

        if (! method_exists($this, $action)) {
            return new EmptyResponse(StatusCode::STATUS_NOT_FOUND);
        }

        return $this->$action($request);
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
<?php
namespace Album\Action;

use App\Action\AbstractPage;
use Psr\Http\Message\ResponseInterface;
use Zend\Expressive\Template\TemplateRendererInterface;

class AlbumPage extends AbstractPage
{
    private $template;

    public function __construct(TemplateRendererInterface $template)
    {
        $this->template = $template;
    }

    public function indexAction( /* ... */ ) : ResponseInterface { /* ... */ }
    public function addAction( /* ... */ ) : ResponseInterface { /* ... */ }
    public function editAction( /* ... */ ) : ResponseInterface { /* ... */ }
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
> use Psr\Http\Message\ResponseInterface;
> use Psr\Http\Message\ServerRequestInterface;
> use Zend\Diactoros\Response\EmptyResponse;
>
> trait ActionBasedInvocation
> {
>     public function handle(ServerRequestInterface $request) : ResponseInterface
>     {
>         $action = $request->getAttribute('action', 'index') . 'Action';
>
>         if (! method_exists($this, $action)) {
>             return new EmptyResponse(StatusCode::STATUS_NOT_FOUND);
>         }
> 
>         return $this->$action($request, $handler);
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
> use Psr\Http\Message\ResponseInterface;
> use Psr\Http\Server\RequestHandlerInterface;
> use Zend\Expressive\Template\TemplateRendererInterface;
>
> class AlbumPage implements RequestHandlerInterface
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
>     public function indexAction( /* ... */ ) : ResponseInterface { /* ... */ }
>     public function addAction( /* ... */ ) : ResponseInterface { /* ... */ }
>     public function editAction( /* ... */ ) : ResponseInterface { /* ... */ }
> }
> ```
