# Changelog

All notable changes to this project will be documented in this file, in reverse chronological order by release.

## 2.0.3 - 2017-03-28

### Added

- Nothing.

### Changed

- [#468](https://github.com/zendframework/zend-expressive/pull/468) updates
  references to `DefaultDelegate::class` to instead use the string
  `'Zend\Expressive\Delegate\DefaultDelegate'`; using the string makes it clear
  that the service name does not resolve to an actual class.

### Deprecated

- Nothing.

### Removed

- Nothing.

### Fixed

- [#476](https://github.com/zendframework/zend-expressive/pull/476) fixes the
  `WhoopsErrorResponseGenerator` to ensure it returns a proper error status
  code, instead of using a `200 OK` status.

## 2.0.2 - 2017-03-13

### Added

- Nothing.

### Deprecated

- Nothing.

### Removed

- Nothing.

### Fixed

- [#467](https://github.com/zendframework/zend-expressive/pull/467) fixes an
  issue when passing invokable, duck-typed, interop middleware to the
  application without registering it with the container. Prior to the patch, it
  was incorrectly being decorated by
  `Zend\Stratigility\Middleware\CallableMiddlewareWrapper` instead of
  `Zend\Stratigility\Middleware\CallableInteropMiddlewareWrapper`.

## 2.0.1 - 2017-03-09

### Added

- Nothing.

### Deprecated

- Nothing.

### Removed

- Nothing.

### Fixed

- [#464](https://github.com/zendframework/zend-expressive/pull/464) fixes the
  `WhoopsErrorResponseGenerator` to provide a correct `Content-Type` header to
  the response when a JSON request occurs.

## 2.0.0 - 2017-03-07

### Added

- [#450](https://github.com/zendframework/zend-expressive/pull/450) adds support
  for [PSR-11](http://www.php-fig.org/psr/psr-11/); Expressive is now a PSR-11
  consumer.

- [#428](https://github.com/zendframework/zend-expressive/pull/428) updates the
  zend-stratigility dependency to require `^2.0`; this allows usage of both
  the new middleare-based error handling system introduced in zend-stratigility
  1.3, as well as usage of [http-interop/http-middleware](https://github.com/http-interop/http-middleware)
  implementations with Expressive. The following middleware is now supported:
  - Implementations of `Interop\Http\ServerMiddleware\MiddlewareInterface`.
  - Callable middleware that implements the same signature as
    `Interop\Http\ServerMiddleware\MiddlewareInterface`.
  - Callable middleware using the legacy double-pass signature
    (`function ($request, $response, callable $next)`); these are now decorated
    in `Zend\Stratigility\Middleware\CallableMiddlewareWrapper` instances.
  - Service names resolving to any of the above.
  - Arrays of any of the above; these will be cast to
    `Zend\Stratigility\MiddlewarePipe` instances, piping each middleware.

- [#396](https://github.com/zendframework/zend-expressive/pull/396) adds
  `Zend\Expressive\Middleware\NotFoundHandler`, which provides a way to return a
  templated 404 response to users. This middleware should be used as innermost
  middleware. You may use the new `Zend\Expressive\Container\NotFoundHandlerFactory`
  to generate the instance via your DI container.

- [#396](https://github.com/zendframework/zend-expressive/pull/396) adds
  `Zend\Expressive\Container\ErrorHandlerFactory`, for generating a
  `Zend\Stratigility\Middleware\ErrorHandler` to use with your application.
  If a `Zend\Expressive\Middleware\ErrorResponseGenerator` service is present in
  the container, it will be used to seed the `ErrorHandler` with a response
  generator. If you use this facility, you should enable the
  `zend-expressive.raise_throwables` configuration flag.

- [#396](https://github.com/zendframework/zend-expressive/pull/396) adds
  `Zend\Expressive\Middleware\ErrorResponseGenerator` and
  `Zend\Expressive\Middleware\WhoopsErrorResponseGenerator`, which may be used
  with `Zend\Stratigility\Middleware\ErrorHandler` to generate error responses.
  The first will generate templated error responses if a template renderer is
  composed, and the latter will generate Whoops output.
  You may use the new `Zend\Expressive\Container\ErrorResponseGeneratorFactory`
  and `Zend\Expressive\Container\WhoopsErrorResponseGeneratorFactory`,
  respectively, to create these instances; if you do, assign these to the
  service name `Zend\Expressive\Middleware\ErrorResponseGenerator` to have them
  automatically registered with the `ErrorHandler`.

- [#396](https://github.com/zendframework/zend-expressive/pull/396) adds
  `Zend\Expressive\ApplicationConfigInjectionTrait`, which exposes two methods,
  `injectRoutesFromConfig()` and `injectPipelineFromConfig()`; this trait is now
  composed into the `Application` class. These methods allow you to configure an
  `Application` instance from configuration if desired, and are now used by the
  `ApplicationFactory` to configure the `Application` instance.

- [#396](https://github.com/zendframework/zend-expressive/pull/396) adds
  a vendor binary, `vendor/bin/expressive-tooling`, which will install (or
  uninstall) the [zend-expressive-tooling](https://github.com/zendframework/zend-expressive-tooling);
  this package provides migration tools for updating your application to use
  programmatic pipelines and the new error handling strategy, as well as tools
  for identifying usage of the legacy Stratigility request and response
  decorators and error middleware.

- [#413](https://github.com/zendframework/zend-expressive/pull/413) adds the
  middleware `Zend\Expressive\Middleware\ImplicitHeadMiddleware`; this
  middleware can be used to provide implicit support for `HEAD` requests when
  the matched route does not explicitly support the method.

- [#413](https://github.com/zendframework/zend-expressive/pull/413) adds the
  middleware `Zend\Expressive\Middleware\ImplicitOptionsMiddleware`; this
  middleware can be used to provide implicit support for `OPTIONS` requests when
  the matched route does not explicitly support the method; the returned 200
  response will also include an `Allow` header listing allowed HTTP methods for
  the URI.

- [#426](https://github.com/zendframework/zend-expressive/pull/426) adds the
  method `Application::getRoutes()`, which will return the list of
  `Zend\Expressive\Router\Route` instances currently registered with the
  application.

- [#428](https://github.com/zendframework/zend-expressive/pull/428) adds the
  class `Zend\Expressive\Delegate\NotFoundDelegate`, an
  `Interop\Http\ServerMiddleware\DelegateInterface` implementation. The class
  will return a 404 response; if a `TemplateRendererInterface` is available and
  injected into the delegate, it will provide templated contents for the 404
  response as well. We also provide `Zend\Expressive\Container\NotFoundDelegateFactory`
  for providing an instance.

- [#428](https://github.com/zendframework/zend-expressive/pull/428) adds the
  method `Zend\Expressive\Application::getDefaultDelegate()`. This method will
  return the default `Interop\Http\ServerMiddleware\DelegateInterface` injected
  during instantiation, or, if none was injected, lazy load an instance of
  `Zend\Expressive\Delegate\NotFoundDelegate`.

- [#428](https://github.com/zendframework/zend-expressive/pull/428) adds the
  constants `DISPATCH_MIDDLEWARE` and `ROUTING_MIDDLEWARE` to 
  `Zend\Expressive\Application`; they have identical values to the constants
  previously defined in `Zend\Expressive\Container\ApplicationFactory`.

- [#428](https://github.com/zendframework/zend-expressive/pull/428) adds
  `Zend\Expressive\Middleware\LazyLoadingMiddleware`; this essentially extracts
  the logic previously used within `Zend\Expressive\Application` to provide
  container-based middleware to allow lazy-loading only when dispatched.

### Changes

- [#440](https://github.com/zendframework/zend-expressive/pull/440) changes the
  `Zend\Expressive\Application::__call($method, array $args)` signature; in
  previous versions, `$args` did not have a typehint. If you are extending the
  class and overriding this method, you will need to update your signature
  accordingly.

- [#428](https://github.com/zendframework/zend-expressive/pull/428) updates
  `Zend\Expressive\Container\ApplicationFactory` to ignore the
  `zend-expressive.raise_throwables` configuration setting; Stratigility 2.X no
  longer catches exceptions in its middleware dispatcher, making the setting
  irrelevant.

- [#422](https://github.com/zendframework/zend-expressive/pull/422) updates the
  zend-expressive-router minimum supported version to 2.0.0.

- [#428](https://github.com/zendframework/zend-expressive/pull/428) modifies the
  `Zend\Expressive\Container\ApplicationFactory` constants `DISPATCH_MIDDLEWARE`
  and `ROUTING_MIDDLEWARE` to define themselves based on the constants of the
  same name now defined in `Zend\Expressive\Application`.

- [#428](https://github.com/zendframework/zend-expressive/pull/428) modifies the
  constructor of `Zend\Expressive\Application`; the third argument was
  previously a nullable callable `$finalHandler`; it is now a nullable
  `Interop\Http\ServerMiddleware\DelegateInterface` with the name
  `$defaultDelegate`.

- [#450](https://github.com/zendframework/zend-expressive/pull/450) modifies the
  signatures in several classes to typehint against [PSR-11](http://www.php-fig.org/psr/psr-11/)
  instead of [container-interop](https://github.com/container-interop/container-interop);
  these include:

  - `Zend\Expressive\AppFactory::create()`
  - `Zend\Expressive\Application::__construct()`
  - `Zend\Expressive\Container\ApplicationFactory::__invoke()`
  - `Zend\Expressive\Container\ErrorHandlerFactory::__invoke()`
  - `Zend\Expressive\Container\ErrorResponseGeneratorFactory::__invoke()`
  - `Zend\Expressive\Container\NotFoundDelegateFactory::__invoke()`
  - `Zend\Expressive\Container\NotFoundHandlerFactory::__invoke()`
  - `Zend\Expressive\Container\WhoopsErrorResponseGeneratorFactory::__invoke()`
  - `Zend\Expressive\Container\WhoopsFactory::__invoke()`
  - `Zend\Expressive\Container\WhoopsPageHandlerFactory::__invoke()`

- [#450](https://github.com/zendframework/zend-expressive/pull/450) changes the
  interface inheritance of `Zend\Expressive\Container\Exception\InvalidServiceException`
  to extend `Psr\Container\ContainerExceptionInterface` instead of
  `Interop\Container\Exception\ContainerException`.

### Deprecated

- Nothing.

### Removed

- [#428](https://github.com/zendframework/zend-expressive/pull/428) removes the
  following routing/dispatch methods from `Zend\Expressive\Application`:
  - `routeMiddleware()`; this is now encapsulated in `Zend\Expressive\Middleware\RouteMiddleware`.
  - `dispatchMiddleware()`; this is now encapsulated in `Zend\Expressive\Middleware\DispatchMiddleware`.

- [#428](https://github.com/zendframework/zend-expressive/pull/428) removes the
  various "final handler" implementations and related factories. Users should
  now use the "default delegates" as detailed in sections previous. Classes
  and methods removed include:
  - `Zend\Expressive\Application::getFinalHandler()`
  - `Zend\Expressive\TemplatedErrorHandler`
  - `Zend\Expressive\WhoopsErrorHandler`
  - `Zend\Expressive\Container\TemplatedErrorHandlerFactory`
  - `Zend\Expressive\Container\WhoopsErrorHandlerFactory`

- [#428](https://github.com/zendframework/zend-expressive/pull/428) removes the
  `Zend\Expressive\ErrorMiddlewarePipe` class, as zend-stratigility 2.X no
  longer defines `Zend\Stratigility\ErrorMiddlewareInterface` or has a concept
  of variant-signature error middleware. Use standard middleware to provide
  error handling now.

- [#428](https://github.com/zendframework/zend-expressive/pull/428) removes the
  exception types `Zend\Expressive\Container\Exception\InvalidArgumentException`
  (use `Zend\Expressive\Exception\InvalidArgumentException` instead) and
  `Zend\Expressive\Container\Exception\NotFoundException` (which was never used
  internally).

### Fixed

- Nothing.

## 1.1.1 - 2017-02-14

### Added

- Nothing.

### Deprecated

- Nothing.

### Removed

- Nothing.

### Fixed

- [#447](https://github.com/zendframework/zend-expressive/pull/447) fixes an
  error in the `ApplicationFactory` that occurs when the `config` service is an
  `ArrayObject`. Prior to the fix, `ArrayObject` configurations would cause a
  fatal error when injecting the pipeline and/or routes.

## 1.1.0 - 2017-02-13

### Added

- [#309](https://github.com/zendframework/zend-expressive/pull/309) adds the
  ability to provide options with which to instantiate the `FinalHandler`
  instance, via the configuration:

  ```php
  [
      'final_handler' => [
          'options' => [ /* array of options */ ],
      ],
  ```

- [#373](https://github.com/zendframework/zend-expressive/pull/373) adds interception
  of exceptions from the `ServerRequestFactory` for invalid request information in order
  to return `400` responses.

- [#432](https://github.com/zendframework/zend-expressive/pull/432) adds two new
  configuration flags for use with `Zend\Expressive\Container\ApplicationFactory`:
  - `zend-expressive.programmatic_pipelines`: when enabled, the factory will
    ignore the `middleware_pipeline` and `routes` configuration, allowing you to
    wire these programmatically instead. We recommend creating these in the
    files `config/pipeline.php` and `config/routes.php`, respectively, and
    modifying your `public/index.php` to `require` these files in statements
    immediately preceding the call to `$app->run()`.
  - `zend-expressive.raise_throwables`: when enabled, this will be used to
    notify zend-stratigility's internal dispatcher to no longer catch
    exceptions/throwables, and instead allow them to bubble out. This allows you
    to write custom middleware for handling errors.

- [#429](https://github.com/zendframework/zend-expressive/pull/429) adds
  `Zend\Expressive\Application::getDefaultDelegate()` as a
  forwards-compatibility measure for the upcoming version 2.0.0. Currently,
  it proxies to `getFinalHandler()`.

- [#435](https://github.com/zendframework/zend-expressive/pull/435) adds support
  for the 2.X versions of zend-expressive-router and the various router
  implementations. This change also allows usage of zend-expressive-helpers 3.X.

### Changed

- [#429](https://github.com/zendframework/zend-expressive/pull/429) updates the
  minimum supported zend-stratigility version to 1.3.3.

- [#396](https://github.com/zendframework/zend-expressive/pull/396) updates the
  `Zend\Expressive\Container\ApplicationFactory` to vary creation of the
  `Application` instance based on two new configuration variables:

  - `zend-expressive.programmatic_pipeline` will cause the factory to skip
    injection of the middleware pipeline and routes from configuration. It is
    then up to the developer to do so, or use the `Application` API to pipe
    middleware and/or add routed middleware.

  - `zend-expressive.raise_throwables` will cause the factory to call the new
    `raiseThrowables()` method exposed by `Application` (and inherited from
    `Zend\Stratigility\MiddlewarePipe`). Doing so will cause the application to
    raise any `Throwable` or `Exception` instances caught, instead of catching
    them and dispatching them to (legacy) Stratigility error middleware.

### Deprecated

- [#429](https://github.com/zendframework/zend-expressive/pull/429) deprecates
  the following methods and classes:
  - `Zend\Expressive\Application::pipeErrorHandler()`; use the
    `raise_throwables` flag and standard middleware to handle errors instead.
  - `Zend\Expressive\Application::routeMiddleware()`; this is extracted to a
    dedicated middleware class for 2.0.
  - `Zend\Expressive\Application::dispatchMiddleware()`; this is extracted to a
    dedicated middleware class for 2.0.
  - `Zend\Expressive\Application::getFinalHandler()` (this patch provides `getDefaultDelegate()` as a forwards-compatibility measure)
  - `Zend\Expressive\Container\Exception\InvalidArgumentException`; this will be removed
    in 2.0.0, and places where it was used will instead throw
    `Zend\Expressive\Exception\InvalidArgumentException`.
  - `Zend\Expressive\Container\Exception\NotFoundException`; this exception is
    never thrown at this point.
  - `Zend\Expressive\Container\TemplatedErrorHandlerFactory`
  - `Zend\Expressive\Container\WhoopsErrorHandlerFactory`
  - `Zend\Expressive\ErrorMiddlewarePipe`; Stratigility 1.3 deprecates its
    `Zend\Stratigility\ErrorMiddlewareInterface`, and removes it in version 2.0.
    use the `raise_throwables` flag and standard middleware to handle errors
    instead.
  - `Zend\Expressive\TemplatedErrorHandler`; the "final handler" concept is
    retired in Expressive 2.0, and replaced with default delegates (classes
    implementing `Interop\Http\ServerMiddleware\DelegateInterface` that will be
    executed when the internal pipeline is exhausted, in order to guarantee a
    response). If you are using custom final handlers, you will need to rewrite
    them when adopting Expressive 2.0.
  - `Zend\Expressive\WhoopsErrorHandler`

### Removed

- [#406](https://github.com/zendframework/zend-expressive/pull/406) removes the
  `RouteResultSubjectInterface` implementation from `Zend\Expressive\Application`,
  per the deprecation prior to the 1.0 stable release.

### Fixed

- [#442](https://github.com/zendframework/zend-expressive/pull/442) fixes how
  the `WhoopsFactory` disables JSON output for whoops; previously, providing
  boolean `false` values for either of the configuration flags
  `json_exceptions.show_trace` or `json_exceptions.ajax_only` would result in
  enabling the settings; these flags are now correctly evaluated by the
  `WhoopsFactory`.

## 1.0.6 - 2017-01-09

### Added

- Nothing.

### Deprecated

- Nothing.

### Removed

- Nothing.

### Fixed

- [#420](https://github.com/zendframework/zend-expressive/pull/420) fixes the
  `routeMiddleware()`'s handling of 405 errors such that it now no longer emits
  deprecation notices when running under the Stratigility 1.3 series.

## 1.0.5 - 2016-12-08

### Added

- Nothing.

### Deprecated

- Nothing.

### Removed

- Nothing.

### Fixed

- [#403](https://github.com/zendframework/zend-expressive/pull/403) updates the
  `AppFactory::create()` logic to raise exceptions in either of the following
  scenarios:
  - no container is specified, and the class `Zend\ServiceManager\ServiceManager`
    is not available.
  - no router is specified, and the class `Zend\Expressive\Router\FastRouteRouter`
    is not available.
- [#405](https://github.com/zendframework/zend-expressive/pull/405) fixes how
  the `TemplatedErrorHandler` injects templated content into the response.
  Previously, it would `write()` directly to the existing response body, which
  could lead to issues if previous middleware had written to the response (as
  the templated contents would append the previous contents). With this release,
  it now creates a new `Zend\Diactoros\Stream`, writes to that, and returns a
  new response with that new stream, guaranteeing it only contains the new
  contents.
- [#404](https://github.com/zendframework/zend-expressive/pull/404) fixes the
  `swallowDeprecationNotices()` handler such that it will not swallow a global
  handler once application execution completes.

## 1.0.4 - 2016-12-07

### Added

- Nothing.

### Deprecated

- Nothing.

### Removed

- Nothing.

### Fixed

- [#402](https://github.com/zendframework/zend-expressive/pull/402) fixes how
  `Application::__invoke()` registers the error handler designed to swallow
  deprecation notices, as introduced in 1.0.3. It now checks to see if another
  error handler was previously registered, and, if so, creates a composite
  handler that will delegate to the previous for all other errors.

## 1.0.3 - 2016-11-11

### Added

- Nothing.

### Changes

- [#395](https://github.com/zendframework/zend-expressive/pull/395) updates
  `Application::__invoke()` to add an error handler to swallow deprecation
  notices due to triggering error middleware when using Stratigility 1.3+. Since
  error middleware is triggered whenever the `raiseThrowables` flag is not
  enabled and an error or empty queue situation is encountered, handling it this
  way prevents any such errors from bubbling out of the application.

### Deprecated

- Nothing.

### Removed

- Nothing.

### Fixed

- Nothing.

## 1.0.2 - 2016-11-11

### Added

- Nothing.

### Changes

- [#393](https://github.com/zendframework/zend-expressive/pull/393) updates
  `Application::run()` to inject the request with an `originalResponse`
  attribute using the provided response as the value.

### Deprecated

- Nothing.

### Removed

- Nothing.

### Fixed

- [#393](https://github.com/zendframework/zend-expressive/pull/393) fixes how
  each of the `TemplatedErrorHandler` and `WhoopsErrorHandler` access the
  "original" request, URI, and/or response. Previously, these used
  Stratigility-specific methods; they now use request attributes, eliminating
  deprecation notices emitted in Stratigility 1.3+ versions.

## 1.0.1 - 2016-11-11

### Added

- [#306](https://github.com/zendframework/zend-expressive/pull/306) adds a
  cookbook recipe covering flash messages.
- [#384](https://github.com/zendframework/zend-expressive/pull/384) adds support
  for Whoops version 2 releases, providing PHP 7 support for Whoops.

### Deprecated

- Nothing.

### Removed

- Nothing.

### Fixed

- [#391](https://github.com/zendframework/zend-expressive/pull/391) fixes the
  `Application::run()` implementation to prevent emission of deprecation notices
  when used with Stratigility 1.3.

## 1.0.0 - 2016-01-28

Initial stable release.

### Added

- [#279](https://github.com/zendframework/zend-expressive/pull/279) updates
  the documentation to provide automation for pushing to GitHub pages. As part
  of that work, documentation was re-organized, and a landing page provided.
  Documentation can now be found at: https://zendframework.github.io/zend-expressive/
- [#299](https://github.com/zendframework/zend-expressive/pull/299) adds
  component-specific CSS to the documentation.
- [#295](https://github.com/zendframework/zend-expressive/pull/295) adds
  support for handling PHP 7 engine exceptions in the templated and whoops final
  handlers.

### Deprecated

- Nothing.

### Removed

- Nothing.

### Fixed

- [#280](https://github.com/zendframework/zend-expressive/pull/280) fixes
  references to the `PlatesRenderer` in the error handling documentation.
- [#284](https://github.com/zendframework/zend-expressive/pull/284) fixes
  the reference to maximebf/php-debugbar in the debug bar documentation.
- [#285](https://github.com/zendframework/zend-expressive/pull/285) updates
  the section on mtymek/blast-base-url in the "Using a Base Path" cookbook
  recipe to conform to its latest release.
- [#286](https://github.com/zendframework/zend-expressive/pull/286) fixes the
  documentation of the Composer "serve" command to correct a typo.
- [#291](https://github.com/zendframework/zend-expressive/pull/291) fixes the
  documentation links to the RC5 -> v1 migration guide in both the CHANGELOG as
  well as the error messages emitted, ensuring users can locate the correct
  documentation in order to upgrade.
- [#287](https://github.com/zendframework/zend-expressive/pull/287) updates the
  "standalone" quick start to reference calling `$app->pipeRoutingMiddleware()`
  and `$app->pipeDispatchMiddleware()` per the changes in RC6.
- [#293](https://github.com/zendframework/zend-expressive/pull/293) adds
  a `require 'vendor/autoload.php';` line to the bootstrap script referenced in
  the zend-servicemanager examples.
- [#294](https://github.com/zendframework/zend-expressive/pull/294) updates the
  namespace referenced in the modulear-layout documentation to provide a better
  separation between the module/package/whatever, and the application consuming
  it.
- [#298](https://github.com/zendframework/zend-expressive/pull/298) fixes a typo
  in a URI generation example.

## 1.0.0rc7 - 2016-01-21

Seventh release candidate.

### Added

- [#277](https://github.com/zendframework/zend-expressive/pull/277) adds a new
  class, `Zend\Expressive\ErrorMiddlewarePipe`. It composes a
  `Zend\Stratigility\MiddlewarePipe`, but implements the error middleware
  signature via its own `__invoke()` method.

### Deprecated

- Nothing.

### Removed

- Nothing.

### Fixed

- [#277](https://github.com/zendframework/zend-expressive/pull/277) updates the
  `MarshalMiddlewareTrait` to create and return an `ErrorMiddlewarePipe` when
  the `$forError` argument provided indicates error middleware is expected.
  This fix allows defining arrays of error middleware via configuration.

## 1.0.0rc6 - 2016-01-18

Sixth release candidate.

This release contains backwards compatibility breaks with previous release
candidates. All previous functionality should continue to work, but will
emit `E_USER_DEPRECATED` notices prompting you to update your application.
In particular:

- The routing middleware has been split into two separate middleware
  implementations, one for routing, another for dispatching. This eliminates the
  need for the route result observer system, as middleware can now be placed
  *between* routing and dispatching — an approach that provides for greater
  flexibility with regards to providing route-based functionality.
- As a result of the above, `Zend\Expressive\Application` no longer implements
  `Zend\Expressive\Router\RouteResultSubjectInterface`, though it retains the
  methods associated (each emits a deprecation notice).
- Configuration for `Zend\Expressive\Container\ApplicationFactory` was modified
  to implement the `middleware_pipeline` as a single queue, instead of
  segregating it between `pre_routing` and `post_routing`. Each item in the
  queue follows the original middleware specification from those keys, with one
  addition: a `priority` key can be used to allow you to granularly shape the
  execution order of the middleware pipeline.

A [migration guide](https://zendframework.github.io/zend-expressive/reference/migration/rc-to-v1/)
was written to help developers migrate to RC6 from earlier versions.

### Added

- [#255](https://github.com/zendframework/zend-expressive/pull/255) adds
  documentation for the base path functionality provided by the `UrlHelper`
  class of zend-expressive-helpers.
- [#227](https://github.com/zendframework/zend-expressive/pull/227) adds
  a section on creating localized routes, and setting the application locale
  based on the matched route.
- [#244](https://github.com/zendframework/zend-expressive/pull/244) adds
  a recipe on using middleware to detect localized URIs (vs using a routing
  parameter), setting the application locale based on the match detected,
  and setting the `UrlHelper` base path with the same match.
- [#260](https://github.com/zendframework/zend-expressive/pull/260) adds
  a recipe on how to add debug toolbars to your Expressive applications.
- [#261](https://github.com/zendframework/zend-expressive/pull/261) adds
  a flow/architectural diagram to the "features" chapter.
- [#262](https://github.com/zendframework/zend-expressive/pull/262) adds
  a recipe demonstrating creating classes that can intercept multiple routes.
- [#270](https://github.com/zendframework/zend-expressive/pull/270) adds
  new methods to `Zend\Expressive\Application`:
  - `dispatchMiddleware()` is new middleware for dispatching the middleware
    matched by routing (this functionality was split from `routeMiddleware()`).
  - `routeResultObserverMiddleware()` is new middleware for notifying route
    result observers, and exists only to aid migration functionality; it is
    marked deprecated!
  - `pipeDispatchMiddleware()` will pipe the dispatch middleware to the
    `Application` instance.
  - `pipeRouteResultObserverMiddleware()` will pipe the route result observer
    middleware to the `Application` instance; like
    `routeResultObserverMiddleware()`, the method only exists for aiding
    migration, and is marked deprecated.
- [#270](https://github.com/zendframework/zend-expressive/pull/270) adds
  `Zend\Expressive\MarshalMiddlewareTrait`, which is composed by
  `Zend\Expressive\Application`; it provides methods for marshaling
  middleware based on service names or arrays of services.

### Deprecated

- [#270](https://github.com/zendframework/zend-expressive/pull/270) deprecates
  the following methods in `Zend\Expressive\Application`, all of which will
  be removed in version 1.1:
  - `attachRouteResultObserver()`
  - `detachRouteResultObserver()`
  - `notifyRouteResultObservers()`
  - `pipeRouteResultObserverMiddleware()`
  - `routeResultObserverMiddleware()`

### Removed

- [#270](https://github.com/zendframework/zend-expressive/pull/270) removes the
  `Zend\Expressive\Router\RouteResultSubjectInterface` implementation from
  `Zend\Expressive\Application`.
- [#270](https://github.com/zendframework/zend-expressive/pull/270) eliminates
  the `pre_routing`/`post_routing` terminology from the `middleware_pipeline`,
  in favor of individually specified `priority` values in middleware
  specifications.

### Fixed

- [#263](https://github.com/zendframework/zend-expressive/pull/263) typo
  fixes in documentation

## 1.0.0rc5 - 2015-12-22

Fifth release candidate.

### Added

- [#233](https://github.com/zendframework/zend-expressive/pull/233) adds a
  documentation page detailing projects using and tutorials written on
  Expressive.
- [#238](https://github.com/zendframework/zend-expressive/pull/238) adds a
  cookbook recipe detailing how to handle serving an Expressive application from
  a subdirectory of your web root.
- [#239](https://github.com/zendframework/zend-expressive/pull/239) adds a
  cookbook recipe detailing how to create modular Expressive applications.
- [#243](https://github.com/zendframework/zend-expressive/pull/243) adds a
  chapter to the helpers section detailing the new `BodyParseMiddleware`.

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
