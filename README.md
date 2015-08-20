# zend-expressive

[![Build Status](https://secure.travis-ci.org/zendframework/zend-expressive.svg?branch=master)](https://secure.travis-ci.org/zendframework/zend-expressive)

*Begin developing PSR-7 middleware applications in minutes!*

**Note: This project is a work in progress. Don't use it in production!**

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

Install this library using composer:

```bash
$ composer require zendframework/zend-expressive:*@dev
```

## Documentation

Documentation is [in the doc tree](doc/), and can be compiled using [bookdown](http://bookdown.io):

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

Architectural notes are in [NOTES.md](NOTES.md).

Please see the tests for full information on capabilities.
