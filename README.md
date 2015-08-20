# zend-expressive

[![Build Status](https://secure.travis-ci.org/zendframework/zend-expressive.svg?branch=master)](https://secure.travis-ci.org/zendframework/zend-expressive)

*Start to develop PSR-7 middleware applications in PHP in a minute!*

**Note: This project is a work in progress. Don't use it in production!**

This component gives you a minimalist PSR-7 middleware framework for PHP.

It's based on [zend-stratigility](https://github.com/zendframework/zend-stratigility)
and offers an easy way to start developing using a single application object.

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

## Architecture

Architectural notes are in [NOTES.md](NOTES.md).

Please see the tests for full information on capabilities.
