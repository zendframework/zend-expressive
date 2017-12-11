# How can I get a debug toolbar for my Expressive application?

Many modern frameworks and applications provide debug toolbars: in-browser
toolbars to provide profiling information of the request executed. These can
provide invaluable details into application objects, database queries, and more.
As an Expressive user, how can you get similar functionality?

## Zend Server Z-Ray

[Zend Server](http://www.zend.com/en/products/zend_server) ships with a tool
called [Z-Ray](http://www.zend.com/en/products/server/z-ray), which provides
both a debug toolbar and debug console (for API debugging). Z-Ray is also
currently [available as a standalone technology
preview](http://www.zend.com/en/products/z-ray/z-ray-preview), and can be added
as an extension to an existing PHP installation.

When using Zend Server or the standalone Z-Ray, you do not need to make any
changes to your application whatsoever to benefit from it; you simply need to
make sure Z-Ray is enabled and/or that you've setup a security token to
selectively enable it on-demand. See the
[Z-Ray documentation](http://files.zend.com/help/Zend-Server/content/z-ray_concept.htm)
for full usage details.

## bitExpert/prophiler-psr7-middleware

Another option is bitExpert's [prophiler-psr7-middleware](https://github.com/bitExpert/prophiler-psr7-middleware).
This package wraps [fabfuel/prophiler](https://github.com/fabfuel/prophiler),
which provides a PHP-based profiling tool and toolbar; the bitExpert package
wraps this in PSR-7 middleware to make consumption in those paradigms trivial.

To add the toolbar middleware to your application, use composer:

```bash
$ composer require bitExpert/prophiler-psr7-middleware
```

From there, you will need to create a factory for the middleware, and add it to
your middleware pipeline. Stephan Hochdörfer, author of the package, has written
a [post detailing these steps](https://blog.bitexpert.de/blog/using-prophiler-with-zend-expressive/).

> ### Use locally!
>
> One minor change we recommend over the directions Stephan provides is that you
> configure the factory and middleware in the
> `config/autoload/middleware-pipeline.local.php` file, vs the `.global` version.
> Doing so enables the middleware and toolbar only in the local environment
> &mdash; and not in production, where you likely do not want to expose such
> information!

## php-middleware/php-debug-bar

[php-middleware/php-debug-bar](https://github.com/php-middleware/phpdebugbar)
provides a PSR-7 middleware wrapper around [maximebf/php-debugbar](https://github.com/maximebf/php-debugbar),
a popular framework-agnostic debug bar for PHP projects.

First, install the middleware in your application:

```bash
$ composer require php-middleware/php-debug-bar
```

This package supplies a config provider, which could be added to your
`config/config.php` when using zend-config-aggregator or
expressive-config-manager. However, because it should only be enabled in
development, we recommend creating a "local" configuration file (e.g.,
`config/autoload/php-debugbar.local.php`) when you need to enable it, with the
following contents:

```php
<?php
use PhpMiddleware\PhpDebugBar\ConfigProvider;

$provider = new ConfigProvider();
return $provider();
```

> ### Use locally!
>
> Remember to enable `PhpMiddleware\PhpDebugBar\ConfigProvider` only in your
> development enviroments!
