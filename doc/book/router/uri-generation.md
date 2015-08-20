# URI Generation

One aspect of the `Zend\Expressive\Router\RouterInterface` is that it provides a
`generateUri()` method. This method accepts a route name, and optionally an
associative array of substitutions to use in the generated URI (e.g., if the URI
has any named placeholders).

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

- If you call `route()` and specify a list of HTTP methods accepted, the name
  will be the literal path, followed by a caret (`^`), followed by a colon
  (`:`)-separated list of the uppercase HTTP method names, in the order in which
  they were added.

  ```php
  $app->route('/foo, $middleware', ['GET', 'POST']); // "foo^GET:POST"
  ```

Clearly, this can become difficult to remember. As such, Expressive offers the
ability to specify a custom string for the route name as an additional, optional
argument to any of the above:

```php
$app->route('/foo', $middleware, 'foo'); // 'foo'
$app->get('/foo/:id', $middleware, 'foo-item'); // 'foo-item'
$app->route('/foo', $middleware, ['GET', 'POST'], 'foo-collection'); // 'foo-collection'
```

We recommend that if you plan on generating URIs for given routes, you provide a
custom name.

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
