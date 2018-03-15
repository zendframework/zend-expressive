# URI Generation

One aspect of the `Zend\Expressive\Router\RouterInterface` is that it provides a
`generateUri()` method. This method accepts a route name, and optionally an
associative array of substitutions to use in the generated URI (e.g., if the URI
has any named placeholders). You may also pass router-specific options to use
during URI generation as a third argument.

## Naming routes

By default, routes use a combination of the path and HTTP methods supported as
the name:

- If you call `route()` with no HTTP methods, the name is the literal path with
  no changes.

  ```php
  $app->route('/foo', $middleware); // "foo"
  ```

- If you call `get()`, `post()`, `put()`, `patch()`, or `delete()`, the name
  will be the literal path, followed by a caret (`^`), followed by the
  uppercase HTTP method name:

  ```php
  $app->get('/foo', $middleware); // "foo^GET"
  ```

  Alternately, these methods return a `Route` instance, and you can set the
  name on it:

  ```php
  $app->get('/foo', $middleware)->setName('foo'); // "foo"
  ```

- If you call `route()` and specify a list of HTTP methods accepted, the name
  will be the literal path, followed by a caret (`^`), followed by a colon
  (`:`)-separated list of the uppercase HTTP method names, in the order in which
  they were added.

  ```php
  $app->route('/foo', $middleware, ['GET', 'POST']); // "foo^GET:POST"
  ```

  Like the HTTP-specific methods, `route()` also returns a `Route` instance,
  and you can set the name on it:

  ```php
  $route = $app->route('/foo', $middleware, ['GET', 'POST']); // "foo^GET:POST"
  $route->setName('foo'); // "foo"
  ```

Clearly, this can become difficult to remember. As such, Expressive offers the
ability to specify a custom string for the route name as an additional, optional
argument to any of the above:

```php
$app->route('/foo', $middleware, 'foo'); // 'foo'
$app->get('/foo/:id', $middleware, 'foo-item'); // 'foo-item'
$app->route('/foo', $middleware, ['GET', 'POST'], 'foo-collection'); // 'foo-collection'
```

As noted above, these methods also return `Route` instances, allowing you to
set the name after-the-fact; this is particularly useful with the `route()`
method, where you may want to omit the HTTP methods if any HTTP method is
allowed:

```php
$app->route('/foo', $middleware)->setName('foo'); // 'foo'
```

We recommend that if you plan on generating URIs for given routes, you provide a
custom name.

> #### Names must be unique
>
> In order for the URI generation functionality to work, routes must be uniquely
> named. This can be tricky when you use the same route path for multiple
> routes:
>
> ```php
> $app->get('/books', ListBooksHandler::class, 'books');
> $app->post('/books', CreateBookHandler::class, 'books'); // oops!
> ```
>
> You could, of course, name the second route "create-book" or similar, but you
> then have multiple names capable of generating the same URI.
>
> Since URIs do not have a concept of HTTP method built in, we recommend naming
> either the route matching `GET` or the first route in the sequence:
>
> ```php
> $app->get('/books', ListBooksHandler::class, 'books');
> $app->post('/books', CreateBookHandler::class); // no name
> ```


## Generating URIs

Once you know the name of a URI you wish to generate, you can do so from the
router instance:

```php
$uri = $router->generateUri('foo-item', ['id' => 'bar']); // "/foo/bar"
```

You can omit the second argument if no substitutions are necessary.

> ### Compose the router
>
> For this to work, you'll need to compose the router instance in any class that
> requires the URI generation facility. Inject the
> `Zend\Expressive\Router\RouterInterface` service in these situations.
>
> Alternately, use the [UrlHelper](../helpers/url-helper.md) instead.
