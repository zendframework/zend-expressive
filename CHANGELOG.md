# Changelog

All notable changes to this project will be documented in this file, in reverse chronological order by release.

## 0.3.1 - TBD

### Added

- Nothing.

### Deprecated

- Nothing.

### Removed

- Nothing.

### Fixed

- Nothing.

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
