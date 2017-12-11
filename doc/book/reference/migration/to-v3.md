# Migration to Expressive 3.0

Expressive 3.0 should not result in many upgrade problems for users. However,
starting in this version, we offer a few changes affecting the following that
you should be aware of, and potentially update your application to adopt:

- [PHP 7.1 support](#php-7.1-support)
- [Signature changes](#signature-changes)
- [Removed functionality](#removed-functionality)
- [PSR-15 support](#psr-15-support)

## PHP 7.1 support

Starting in Expressive 3.0 we support only PHP 7.1+.

## Signature changes

All middlewares and delegators implements now interfaces from PSR-15
`http-interop/http-server-middleware`. It means the following changes:

- middleware's `process` method type hint `RequestHandlerInterface` as
  the second parameter instead of `DelegateInterface`,
- middleware's `process` method has now return type
  `\Psr\Http\Message\ResponseInterface`,
- delegators are changed to request handlers: these now implements interface
  `RequestHandlerInterface` instead of `DelegateInterface`,
- delegator's `process` method has been renamed to `handle` and
  return type `\Psr\Http\Message\ResponseInterface` has been declared.

The following signature changes were made that could affect _class extensions_:

- `Zend\Expressive\Application::__construct(...)`
  Third parameter is now `RequestHandlerInterface` instead of `DelegateInterface`

## Removed functionality 

- double-pass middlewares (introduced in Expressive 1.X, deprecated in Expressive 2.X)

## PSR-15 Support

As said before, all middlewares and request handlers now implements PSR-15
interfaces. It means `process` method (of middleware) and `handle` method
(of request handler) have declared return type `\Psr\Http\Message\ResponseInterface`.

To update your middlewares you can use tool available in `zend-expressive-tooling`:

```console
$ vendor/bin/expressive migrate:interop-middleware [--src|-s=<path-to-src>]
```

It looks for all interop middlewares and delegators and convert them to PSR-15
middlewares and request delegators.
