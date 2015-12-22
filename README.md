# zend-expressive

[![Build Status](https://secure.travis-ci.org/zendframework/zend-expressive.svg?branch=master)](https://secure.travis-ci.org/zendframework/zend-expressive)

*Begin developing PSR-7 middleware applications in minutes!*

zend-expressive builds on [zend-stratigility](https://github.com/zendframework/zend-stratigility)
to provide a minimalist PSR-7 middleware framework for PHP, with the following
features:

- Routing. Choose your own router; we support:
    - [Aura.Router](https://github.com/auraphp/Aura.Router)
    - [FastRoute](https://github.com/nikic/FastRoute)
    - [ZF2's MVC router](https://github.com/zendframework/zend-mvc)
- DI Containers, via [container-interop](https://github.com/container-interop/container-interop).
  Middleware matched via routing is retrieved from the composed container.
- Optionally, templating. We support:
    - [Plates](http://platesphp.com/)
    - [Twig](http://twig.sensiolabs.org/)
    - [ZF2's PhpRenderer](https://github.com/zendframework/zend-view)

## Installation

We provide two ways to install Expressive, both using
[Composer](https://getcomposer.org): via our
[skeleton project and installer](https://github.com/zendframework/zend-expressive-skeleton),
or manually.

### Using the skeleton + installer

The simplest way to install and get started is using the skeleton project, which
includes installer scripts for choosing a router, dependency injection
container, and optionally a template renderer and/or error handler. The skeleton
also provides configuration for officially supported dependencies.

To use the skeleton, use Composer's `create-project` command:

```bash
$ composer create-project -s rc zendframework/zend-expressive-skeleton <project dir>
```

This will prompt you through choosing your dependencies, and then create and
install the project in the `<project dir>` (omitting the `<project dir>` will
create and install in a `zend-expressive-skeleton/` directory).

### Manual Composer installation

You can install Expressive standalone using Composer:

```bash
$ composer require zendframework/zend-expressive
```

However, at this point, Expressive is not usable, as you need to supply
minimally:

- a router.
- a dependency injection container.

We currently support and provide the following routing integrations:

- [Aura.Router](https://github.com/auraphp/Aura.Router):
  `composer require zendframework/zend-expressive-aurarouter`
- [FastRoute](https://github.com/nikic/FastRoute):
  `composer require zendframework/zend-expressive-fastroute`
- [ZF2 MVC Router](https://github.com/zendframework/zend-mvc):
  `composer require zendframework/zend-expressive-zendrouter`

We recommend using a dependency injection container, and typehint against
[container-interop](https://github.com/container-interop/container-interop). We
can recommend the following implementations:

- [zend-servicemanager](https://github.com/zendframework/zend-servicemanager):
  `composer require zendframework/zend-servicemanager`
- [pimple-container-interop](https://github.com/xtreamwayz/pimple-container-interop):
  `composer require xtreamwayz/pimple-container-interop`
- [Aura.Di](https://github.com/auraphp/Aura.Di):
  `composer require aura/di:3.0.*@beta`

Additionally, you may optionally want to install a template renderer
implementation, and/or an error handling integration. These are covered in the
documentation.

## Documentation

Documentation is [in the doc tree](https://github.com/zendframework/zend-expressive/tree/master/doc/), and can be compiled using [bookdown](http://bookdown.io):

```bash
$ bookdown doc/bookdown.json
$ php -S 0.0.0.0:8080 -t doc/html/ # then browse to http://localhost:8080/
```

> ### Bookdown
>
> You can install bookdown globally using `composer global require bookdown/bookdown`. If you do
> this, make sure that `$HOME/.composer/vendor/bin` is on your `$PATH`.

Additionally, public-facing, browseable documentation is available at
http://zend-expressive.rtfd.org.

## Architecture

Architectural notes are in [NOTES.md](https://github.com/zendframework/zend-expressive/blob/master/NOTES.md).

Please see the tests for full information on capabilities.
