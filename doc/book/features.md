# Overview

Expressive allows you to write [PSR-7](http://www.php-fig.org/psr/psr-7/)
[middleware](https://github.com/zendframework/zend-stratigility/blob/master/doc/book/middleware.md)
applications for the web.

PSR-7 is a standard defining HTTP message interfaces; these are the incoming
request and outgoing response for your application. By using PSR-7, we ensure
that your applications will work in other PSR-7 contexts.

Middleware is any code sitting between a request and a response; it typically
analyzes the request to aggregate incoming data, delegates it to another layer
to process, and then creates and returns a response. Middleware can and should
be relegated only to those tasks, and should be relatively easy to write and
maintain.

Middleware is also designed for composability; you should be able to nest
middleware and re-use middleware.

With Expressive, you can build PSR-7-based middleware applications:

- APIs
- Websites
- Single Page Applications
- and more.

## Features

Expressive builds on [zend-stratigility](https://github.com/zendframework/zend-stratigility)
to provide a robust convenience layer on which to build applications. The
features it provides include:

- **Routing**
  
  Stratigility provides limited, literal matching only. Expressive allows you
  to utilize dynamic routing capabilities from a variety of routers, providing
  much more fine-grained matching capabilities. The routing layer also allows
  restricting matched routes to specific HTTP methods, and will return "405 Not
  Allowed" responses with an "Allow" HTTP header containing allowed HTTP
  methods for invalid requests.

  Routing is abstracted in Expressive, allowing the developer to choose the
  routing library that best fits the project needs. By default, we provide
  wrappers for Aura.Router, FastRoute, and the zend-mvc router.

- **contaienr-interop**

  Expressive encourages the use of Dependency Injection, and defines its
  `Application` class to compose a container-interop `ContainerInterface`
  instance. The container is used to lazy-load middleware, whether it is
  piped (Stratigility interface) or routed (Expressive).

- **Templating**

  While Expressive does not assume templating is being used, it provides a
  templating abstraction. Developers can write middleware that typehints on
  this abstraction, and assume that the underlying adapter will provide
  layout support and namespaced template support.

- **Error Handling**

  Applications should handle errors gracefully, but also handle them differently
  in development versus production. Expressive provides both basic error
  handling via Stratigility's own `FinalHandler` implementation, as well as
  more advanced error handling via two specialized error handlers: a templated
  error handler for production, and a Whoops-based error handler for development.
