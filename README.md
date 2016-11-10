# zend-expressive

[![Build Status](https://secure.travis-ci.org/zendframework/zend-expressive.svg?branch=master)](https://secure.travis-ci.org/zendframework/zend-expressive)
[![Coverage Status](https://coveralls.io/repos/github/zendframework/zend-expressive/badge.svg?branch=master)](https://coveralls.io/github/zendframework/zend-expressive?branch=master)

*Develop PSR-7 middleware applications in minutes!*

zend-expressive builds on [zend-stratigility](https://github.com/zendframework/zend-stratigility)
to provide a minimalist PSR-7 middleware framework for PHP, with the following
features:

- Routing. Choose your own router; we support:
    - [Aura.Router](https://github.com/auraphp/Aura.Router)
    - [FastRoute](https://github.com/nikic/FastRoute)
    - [zend-router](https://github.com/zendframework/zend-expressive-router)
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
$ composer create-project zendframework/zend-expressive-skeleton <project dir>
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
- [zend-router](https://github.com/zendframework/zend-expressive-router):
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

Documentation is [in the doc tree](doc/book/), and can be compiled using [mkdocs](http://www.mkdocs.org):

```bash
$ mkdocs build
```

Additionally, public-facing, browseable documentation is available at
https://zendframework.github.io/zend-expressive/

## Architecture

Architectural notes are in [NOTES.md](NOTES.md).

Please see the tests for full information on capabilities.
