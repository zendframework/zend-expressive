# Content-Length Middleware

- **Available since zend-expressive-helpers version 4.1.0.**

In some cases, you may want to include an explicit `Content-Length` response
header, without having to inject it manually. To facilitate this, we provide
`Zend\Expressive\Helper\ContentLengthMiddleware`.

> ### When to use this middleware
>
> In most cases, you do not need to provide an explicit Content-Length value
> in your responses. While the HTTP/1.1 specification indicates the header
> SHOULD be provided, most clients will not degrade to HTTP/1.0 if the header
> is omitted.
>
> The one exception that has been reported is when working with
> [New Relic](https://newrelic.com), which requires valid `Content-Length`
> headers for some of its analytics; in such cases, enabling this middleware
> will fix those situations.

This middleware delegates the request, and operates on the returned response. It
will return a new response with the `Content-Length` header injected under the
following conditions:

- No `Content-Length` header is already present AND
- the body size is non-null.

To register it in your application, you will need to do two things: register the
middleware with the container, and register the middleware in either your
application pipeline, or within routed middleware.

To add it to your container, add the following configuration:

```php
// In a `config/autoload/*.global.php` file, or a `ConfigProvider` class:

use Zend\Expressive\Helper;

return [
    'dependencies' => [
        'invokables' => [
            Helper\ContentLengthMiddleware::class => Helper\ContentLengthMiddleware::class,
        ],
    ],
];
```

To register it as pipeline middleware to execute on any request:

```php
// In `config/pipeline.php`:

use Zend\Expressive\Helper;

$app->pipe(Helper\ContentLengthMiddleware::class);
```

To register it within a routed middleware pipeline:

```php
// In `config/routes.php`:

use Zend\Expressive\Helper;

$app->get('/download/tarball', [
    Helper\ContentLengthMiddleware::class,
    Download\Tarball::class,
], 'download-tar');
```

## Caveats

One caveat to note is that if you use this middleware, but also write directly
to the output buffer (e.g., via a `var_dump`, or if `display_errors` is on and
an uncaught error or exception occurs), the output will not appear as you
expect. Generally in such situations, the contents of the output buffer will
appear, up to the specified `Content-Length` value. This can lead to truncated
error content and/or truncated application content.

We recommend that if you use this feature, you also use a PHP error and/or
exception handler that logs errors in order to prevent truncated output.
