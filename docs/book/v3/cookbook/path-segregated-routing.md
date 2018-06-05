# Path-segregated routing

- Since zend-expressive-router 3.1.0, zend-expressive-helpers 5.1.0, and
  zend-expressive-hal 1.1.0.

You may want to develop a self-contained module that you can then drop in
to an existing application; you may even want to [path-segregate](../features/router/piping.md#path-segregation) it.

In such cases, you will want to use a different router instance, which has a
huge number of ramifications:

- You'll need separate routing middleware.
- You'll need a separate [UrlHelper](../features/helpers/url-helper.md) instance, as well as its related middleware,
  if you are generating URIs.
- If you are generating [HAL](https://docs.zendframework.com/zend-expressive-hal/),
  you'll need:
  - a separate URL generator for HAL that consumes the separate `UrlHelper`
    instance.
  - a separate `LinkGenerator` for HAL that consumes the separate URL generator.
  - a separate `ResourceGenerator` for HAL that consumes the separate
    `LinkGenerator`.

These tasks can be accomplished by writing your own factories, but that means a
lot of extra code, and the potential for the factories to go out-of-sync with
the official factories for these services. What should you do?

We provide details on how to accomplish these scenarios elsewhere:

- [For modules not using HAL](../features/helpers/url-helper.md#router-specific-helpers)
- [For modules using HAL](https://docs.zendframework.com/zend-expressive-hal/cookbook/path-segregated-uri-generation/)
