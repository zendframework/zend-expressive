# Expressive: PSR-7 Middleware in Minutes

Expressive builds on [Stratigility](https://github.com/zendframework/zend-stratigility)
to provide a minimalist [PSR-7](http://www.php-fig.org/psr/psr-7/) middleware
framework for PHP, with the following features:

- Routing. Choose your own router; we support:
    - [Aura.Router](https://github.com/auraphp/Aura.Router)
    - [FastRoute](https://github.com/nikic/FastRoute)
    - [zend-router](https://github.com/zendframework/zend-expressive-router)
- DI Containers, via [PSR-11 Container](https://github.com/php-fig/container).
  All middleware composed in Expressive may be retrieved from the composed
  container.
- Optionally, templating. We support:
    - [Plates](http://platesphp.com/)
    - [Twig](http://twig.sensiolabs.org/)
    - [ZF2's PhpRenderer](https://github.com/zendframework/zend-view)
- Error handling. Create templated error pages, or use tools like
  [whoops](https://github.com/filp/whoops) for debugging purposes.
- Nested middleware applications. Write an application, and compose it later
  in another, optionally under a separate subpath.
- [Simplfied installation](getting-started/skeleton.md). Our custom
  [Composer](https://getcomposer.org)-based installer prompts you for your
  initial stack choices, giving you exactly the base you want to start from.

Essentially, Expressive allows *you* to develop using the tools *you* prefer,
and provides minimal structure and facilities to ease your development.

Should I choose it over Zend\Mvc?
That’s a good question. [Here’s what we recommend.](why-expressive.md)

If you’re keen to get started, then [keep reading](getting-started/features.md)
and get started writing your first middleware application today!
