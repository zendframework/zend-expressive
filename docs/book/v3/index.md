# Expressive: PSR-15 Middleware in Minutes

Expressive builds on [Stratigility](https://docs.zendframework.com/zend-stratigility/)
to provide a minimalist [PSR-15](http://www.php-fig.org/psr/psr-15/) middleware
framework for PHP, with the following features:

- Routing. Choose your own router; we support:
    - [Aura.Router](https://github.com/auraphp/Aura.Router)
    - [FastRoute](https://github.com/nikic/FastRoute)
    - [zend-router](https://github.com/zendframework/zend-router)
- DI Containers, via [PSR-11 Container](https://www.php-fig.org/psr/psr-11/).
  All middleware composed in Expressive may be retrieved from the composed
  container.
- Optionally, templating. We support:
    - [Plates](http://platesphp.com/)
    - [Twig](http://twig.sensiolabs.org/)
    - [zend-view's PhpRenderer](https://docs.zendeframework..com/zend-view/)
- Error handling. Create templated error pages, or use tools like
  [whoops](https://github.com/filp/whoops) for debugging purposes.
- Nested middleware applications. Write an application, and compose it later
  in another, optionally under a separate subpath.
- [Simplified installation](getting-started/quick-start.md#create-a-new-project). 
  Our custom [Composer](https://getcomposer.org)-based installer prompts you for 
  your initial stack choices, giving you exactly the base you want to start from.

Essentially, Expressive allows *you* to develop using the tools *you* prefer,
and provides minimal structure and facilities to ease your development.

Should I choose it over zend-mvc?
That’s a good question. [Here’s what we recommend.](why-expressive.md)

If you’re keen to get started, then [keep reading](getting-started/features.md)
and get started writing your first middleware application today!
