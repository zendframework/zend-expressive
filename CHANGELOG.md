# Changelog

All notable changes to this project will be documented in this file, in reverse chronological order by release.

<<<<<<< HEAD
## 1.1.0 - TBD

### Added

- Nothing.

### Deprecated

- Nothing.

### Removed

- Nothing.

### Fixed

- Nothing.

## 1.0.0rc5 - TBD

Fifth release candidate.

### Added

- [#233](https://github.com/zendframework/zend-expressive/pull/233) adds a
  documentation page detailing projects using and tutorials written on
  Expressive.

### Deprecated

- Nothing.

### Removed

- Nothing.

### Fixed

- [#234](https://github.com/zendframework/zend-expressive/pull/234) fixes the
  inheritance tree for `Zend\Expressive\Exception\RuntimeException` to inherit
  from `RuntimeException` and not `InvalidArgumentException`.
- [#237](https://github.com/zendframework/zend-expressive/pull/237) updates the
  Pimple documentation to recommend `xtreamwayz/pimple-container-interop`
  instead of `mouf/pimple-interop`, as the latter consumed Pimple v1, instead of
  the current stable v3.

## 1.0.0rc4 - 2015-12-09

Fourth release candidate.

### Added

- [#217](https://github.com/zendframework/zend-expressive/pull/217) adds a
  cookbook entry to the documentation detailing how to configure zend-view
  helpers from other components, as well as how to add custom view helpers.

### Deprecated

- Nothing.

### Removed

- Nothing.

### Fixed

- [#219](https://github.com/zendframework/zend-expressive/pull/219) updates the
  "Hello World Using a Configuration-Driven Container" usage case to use
  zend-stdlib's `Glob::glob()` instead of the `glob()` native function, to
  ensure the documented solution is portable across platforms.
- [#223](https://github.com/zendframework/zend-expressive/pull/223) updates the
  documentation to refer to the `composer serve` command where relevant, and
  also details how to create the command for standalone users.
- [#221](https://github.com/zendframework/zend-expressive/pull/221) splits the
  various cookbook entries into separate files, so each is self-contained.
- [#224](https://github.com/zendframework/zend-expressive/pull/224) adds opening
  `<?php` tags to two configuration file examples, in order to prevent
  copy-paste errors.

## 1.0.0rc3 - 2015-12-07

Third release candidate.

### Added

- [#185](https://github.com/zendframework/zend-expressive/pull/185)
  Support casting zend-view models to arrays.
- [#192](https://github.com/zendframework/zend-expressive/pull/192) adds support
  for specifying arrays of middleware both when routing and when creating
  pipeline middleware. This feature is opt-in and backwards compatible; simply
  specify an array value that does not resolve as a callable. Values in the
  array **must** be callables, service names resolving to callable middleware,
  or fully qualified class names that can be instantiated without arguments, and
  which result in invokable middleware.
- [#200](https://github.com/zendframework/zend-expressive/pull/200),
  [#206](https://github.com/zendframework/zend-expressive/pull/206), and
  [#211](https://github.com/zendframework/zend-expressive/pull/211) add
  functionality for observing computed `RouteResult`s.
  `Zend\Expressive\Application` now implements
  `Zend\Expressive\Router\RouteResultSubjectInterface`, which allows attaching
  `Zend\Expressive\RouteResultObserverInterface` implementations and notifying
  them of computed `RouteResult` instances. The following methods are now
  available on the `Application` instance:
  - `attachRouteResultObserver(Router\RouteResultObserverInterface $observer)`
  - `detachRouteResultObserver(Router\RouteResultObserverInterface $observer)`
  - `notifyRouteResultObservers(RouteResult $result)`; `Application` calls this
    internally within `routeMiddleware`.
  This feature enables the ability to notify objects of the calculated
  `RouteResult` without needing to inject middleware into the system.
- [#81](https://github.com/zendframework/zend-expressive/pull/81) adds a
  cookbook entry for creating 404 handlers.
- [#210](https://github.com/zendframework/zend-expressive/pull/210) adds a
  documentation section on the new [zendframework/zend-expressive-helpers](https://github.com/zendframework/zend-expressive-helpers)
  utilities.

### Deprecated

- Nothing.

### Removed

- [#204](https://github.com/zendframework/zend-expressive/pull/204) removes the
  `Router` and `Template` components, as they are now shipped with the following
  packages, respectively:
  - [zendframework/zend-expressive-router](https://github.com/zendframework/zend-expressive-router)
  - [zendframework/zend-expressive-template](https://github.com/zendframework/zend-expressive-template)
  This package has been updated to depend on each of them.

### Fixed

- [#187](https://github.com/zendframework/zend-expressive/pull/187)
  Inject the route result as an attribute
- [#197](https://github.com/zendframework/zend-expressive/pull/197) updates the
  `Zend\Expressive\Container\ApplicationFactory` to raise exceptions in cases
  where received configuration is unusable, instead of silently ignoring it.
  This is a small backwards compatibility break, but is done to eliminate
  difficult to identify issues due to bad configuration.
- [#202](https://github.com/zendframework/zend-expressive/pull/202) clarifies
  that `RouterInterface` implements **MUST** throw a `RuntimeException` if
  `addRoute()` is called after either `match()` or `generateUri()` have been
  called.

## 1.0.0rc2 - 2015-10-20

Second release candidate.

### Added

- Nothing.

### Deprecated

- Nothing.

### Removed

- Nothing.

### Fixed

- Updated branch aliases: dev-master => 1.0-dev, dev-develop => 1.1-dev.
- Point dev dependencies on sub-components to `~1.0-dev`.

## 1.0.0rc1 - 2015-10-19

First release candidate.

### Added

- Nothing.

### Deprecated

- Nothing.

### Removed

- Nothing.

### Fixed

- Nothing.

## 0.5.3 - 2015-10-19

### Added

- Nothing.

### Deprecated

- Nothing.

### Removed

- Nothing.

### Fixed

- [#160](https://github.com/zendframework/zend-expressive/pull/160) updates
  `EmitterStack` to throw a component-specific `InvalidArgumentException`
  instead of the generic SPL version.
- [#163](https://github.com/zendframework/zend-expressive/pull/163) change the
  documentation on wiring middleware factories to put them in the `dependencies`
  section of `routes.global.php`; this keeps the routing and middleware
  configuration in the same file.

## 0.5.2 - 2015-10-17

### Added

- [#158](https://github.com/zendframework/zend-expressive/pull/158) documents
  getting started via the [installer + skeleton](https://github.com/zendframework/zend-expressive-skeleton),
  and also documents "next steps" in terms of creating and wiring middleware
  when using the skeleton.

### Deprecated

- Nothing.

### Removed

- Nothing.

### Fixed

- Nothing.

## 0.5.1 - 2015-10-13

### Added

- Nothing.

### Deprecated

- Nothing.

### Removed

- Nothing.

### Fixed

- [#156](https://github.com/zendframework/zend-expressive/pull/156) updates how
  the routing middleware pulls middleware from the container; in order to work
  with zend-servicemanager v3 and allow `has()` queries to query abstract
  factories, a second, boolean argument is now passed.

## 0.5.0 - 2015-10-10

### Added

- Nothing.

### Deprecated

- Nothing.

### Removed

- [#131](https://github.com/zendframework/zend-expressive/pull/131) modifies the
  repository to remove the concrete router and template renderer
  implementations, along with any related factories; these are now in their own
  packages. The classes removed include:
  - `Zend\Expressive\Container\Template\PlatesRendererFactory`
  - `Zend\Expressive\Container\Template\TwigRendererFactory`
  - `Zend\Expressive\Container\Template\ZendViewRendererFactory`
  - `Zend\Expressive\Router\AuraRouter`
  - `Zend\Expressive\Router\FastRouteRouter`
  - `Zend\Expressive\Router\ZendRouter`
  - `Zend\Expressive\Template\PlatesRenderer`
  - `Zend\Expressive\Template\TwigRenderer`
  - `Zend\Expressive\Template\Twig\TwigExtension`
  - `Zend\Expressive\Template\ZendViewRenderer`
  - `Zend\Expressive\Template\ZendView\NamespacedPathStackResolver`
  - `Zend\Expressive\Template\ZendView\ServerUrlHelper`
  - `Zend\Expressive\Template\ZendView\UrlHelper`

### Fixed

- Nothing.

## 0.4.1 - TBD

### Added

- Nothing.

### Deprecated

- Nothing.

### Removed

- Nothing.

### Fixed

- Nothing.

## 0.4.0 - 2015-10-10

### Added

- [#132](https://github.com/zendframework/zend-expressive/pull/132) adds
  `Zend\Expressive\Router\ZendRouter`, replacing
  `Zend\Expressive\Router\Zf2Router`.
- [#139](https://github.com/zendframework/zend-expressive/pull/139) adds:
  - `Zend\Expressive\Template\TemplateRendererInterface`, replacing
    `Zend\Expressive\Template\TemplateInterface`.
  - `Zend\Expressive\Template\PlatesRenderer`, replacing
    `Zend\Expressive\Template\Plates`.
  - `Zend\Expressive\Template\TwigRenderer`, replacing
    `Zend\Expressive\Template\Twig`.
  - `Zend\Expressive\Template\ZendViewRenderer`, replacing
    `Zend\Expressive\Template\ZendView`.
- [#143](https://github.com/zendframework/zend-expressive/pull/143) adds
  the method `addDefaultParam($templateName, $param, $value)` to
  `TemplateRendererInterface`, allowing users to specify global and
  template-specific default parameters to use when rendering. To implement the
  feature, the patch also provides `Zend\Expressive\Template\DefaultParamsTrait`
  to simplify incorporating the feature in implementations.
- [#133](https://github.com/zendframework/zend-expressive/pull/133) adds a
  stipulation to `Zend\Expressive\Router\RouterInterface` that `addRoute()`
  should *aggregate* `Route` instances only, and delay injection until `match()`
  and/or `generateUri()` are called; all shipped routers now follow this. This
  allows manipulating `Route` instances before calling `match()` or
  `generateUri()` — for instance, to inject options or a name.
- [#133](https://github.com/zendframework/zend-expressive/pull/133) re-instates
  the `Route::setName()` method, as the changes to lazy-inject routes means that
  setting names and options after adding them to the application now works
  again.

### Deprecated

- Nothing.

### Removed

- [#132](https://github.com/zendframework/zend-expressive/pull/132) removes
  `Zend\Expressive\Router\Zf2Router`, renaming it to
  `Zend\Expressive\Router\ZendRouter`.
- [#139](https://github.com/zendframework/zend-expressive/pull/139) removes:
  - `Zend\Expressive\Template\TemplateInterface`, renaming it to
    `Zend\Expressive\Template\TemplateRendererInterface`.
  - `Zend\Expressive\Template\Plates`, renaming it to
    `Zend\Expressive\Template\PlatesRenderer`.
  - `Zend\Expressive\Template\Twig`, renaming it to
    `Zend\Expressive\Template\TwigRenderer`.
  - `Zend\Expressive\Template\ZendView`, renaming it to
    `Zend\Expressive\Template\ZendViewRenderer`.

### Fixed

- Nothing.

## 0.3.1 - 2015-10-09

### Added

- [#149](https://github.com/zendframework/zend-expressive/pull/149) adds
  verbiage to the `RouterInterface::generateUri()` method, specifying that the
  returned URI **MUST NOT** be escaped. The `AuraRouter` implementation has been
  updated to internally use `generateRaw()` to follow this guideline, and retain
  parity with the other existing implementations.

### Deprecated

- Nothing.

### Removed

- Nothing.

### Fixed

- [#140](https://github.com/zendframework/zend-expressive/pull/140) updates the
  AuraRouter to use the request method from the request object, and inject that
  under the `REQUEST_METHOD` server parameter key before passing the server
  parameters for matching. This simplifies testing.

## 0.3.0 - 2015-09-12

### Added

- [#128](https://github.com/zendframework/zend-expressive/pull/128) adds
  container factories for each supported template implementation:
  - `Zend\Expressive\Container\Template\PlatesFactory`
  - `Zend\Expressive\Container\Template\TwigFactory`
  - `Zend\Expressive\Container\Template\ZendViewFactory`
- [#128](https://github.com/zendframework/zend-expressive/pull/128) adds
  custom `url` and `serverUrl` zend-view helper implementations, to allow
  integration with any router and with PSR-7 URI instances. The newly
  added `ZendViewFactory` will inject these into the `HelperPluginManager` by
  default.

### Deprecated

- Nothing.

### Removed

- Nothing.

### Fixed

- [#128](https://github.com/zendframework/zend-expressive/pull/128) fixes an
  expectation in the `WhoopsErrorHandler` tests to ensure the tests can run
  successfully.

## 0.2.1 - 2015-09-10

### Added

- Nothing.

### Deprecated

- Nothing.

### Removed

- Nothing.

### Fixed

- [#125](https://github.com/zendframework/zend-expressive/pull/125) fixes the
  `WhoopsErrorHandler` to ensure it pushes the "pretty page handler" into the
  Whoops runtime.

## 0.2.0 - 2015-09-03

### Added

- [#116](https://github.com/zendframework/zend-expressive/pull/116) adds
  `Application::any()` to complement the various HTTP-specific routing methods;
  it has the same signature as `get()`, `post()`, `patch()`, et al, but allows
  any HTTP method.
- [#120](https://github.com/zendframework/zend-expressive/pull/120) renames the
  router classes for easier discoverability, to better reflect their usage, and
  for better naming consistency. `Aura` becomes `AuraRouter`, `FastRoute`
  becomes `FastRouteRouter` and `Zf2` becomes `Zf2Router`.

### Deprecated

- Nothing.

### Removed

- [#120](https://github.com/zendframework/zend-expressive/pull/120) removes the
  classes `Zend\Expressive\Router\Aura`, `Zend\Expressive\Router\FastRoute`, and
  `Zend\Expressive\Router\Zf`, per the "Added" section above.

### Fixed

- Nothing.

## 0.1.1 - 2015-09-03

### Added

- [#112](https://github.com/zendframework/zend-expressive/pull/112) adds a
  chapter to the documentation on using Aura.Di (v3beta) with zend-expressive.

### Deprecated

- Nothing.

### Removed

- Nothing.

### Fixed

- [#118](https://github.com/zendframework/zend-expressive/pull/118) fixes an
  issue whereby route options specified via configuration were not being pushed
  into generated `Route` instances before being passed to the underlying router.

## 0.1.0 - 2015-08-26

Initial tagged release.

### Added

- Everything.

### Deprecated

- Nothing.

### Removed

- Nothing.

### Fixed

- Nothing.
