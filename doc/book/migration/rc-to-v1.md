# Migration from RC5 or earlier

RC6 provides a breaking change intended to simplify the creation of the
middleware pipeline and to allow replacing the routing middleware. Doing so,
however, required a change to the `middleware_pipeline` configuration.

The changes are done in such a way as to honor configuration from RC5 and
earlier, but using such configuration will now prompt you to update your
application. This document describes how to do so.

## Configuration for RC5 and earlier

RC5 and earlier defined the default `middleware_pipeline` configuration as follows:

```php
return [
    'middleware_pipeline' => [
        // An array of middleware to register prior to registration of the
        // routing middleware
        'pre_routing' => [
            //[
            // Required:
            //    'middleware' => 'Name or array of names of middleware services and/or callables',
            // Optional:
            //    'path'  => '/path/to/match',
            //    'error' => true,
            //],
            [
                'middleware' => [
                    Helper\ServerUrlMiddleware::class,
                    Helper\UrlHelperMiddleware::class,
                ],
            ],
        ],

        // An array of middleware to register after registration of the
        // routing middleware
        'post_routing' => [
            //[
            // Required:
            //    'middleware' => 'Name of middleware service, or a callable',
            // Optional:
            //    'path'  => '/path/to/match',
            //    'error' => true,
            //],
        ],
    ],
]
```

## Configuration for RC6 and above

RC6 and later change the configuration to remove the `pre_routing` and
`post_routing` keys. However, individual items within the array retain the same
format as middleware inside those keys, namely:

```php
[
    // Required:
    'middleware' => 'Name of middleware service, or a callable',
    // Optional:
    //    'path'  => '/path/to/match',
    //    'error' => true,
]
```

Additionally, the routing middleware itself now becomes one item in the array,
and is **required** if you have defined any routes. This change gives you full
control over the flow of the pipeline.

To specify the routing middleware, use the constant
`Zend\Expressive\Container\ApplicationFactory::ROUTING_MIDDLEWARE` in place of
a middleware array; this has the value `EXPRESSIVE_ROUTING_MIDDLEWARE`, if you
do not want to import the class.

As such, the default configuration now becomes:

```php
return [
    'middleware_pipeline' => [
        // An array of middleware to pipe to the application.
        // Each item is of the following structure:
        // [
        //     // Required:
        //     'middleware' => 'Name or array of names of middleware services and/or callables',
        //     // Optional:
        //     'path'  => '/path/to/match',
        //     'error' => true,
        // ],
        [
            'middleware' => [
                Helper\ServerUrlMiddleware::class,
                Helper\UrlHelperMiddleware::class,
            ],
        ],

        // The following is an entry for the routing middleware:
        Zend\Expressive\Container\ApplicationFactory::ROUTING_MIDDLEWARE,

        // Place error handling middleware after the routing middleware.
    ],
]
```

To update an existing application:

- Promote all `pre_routing` middleware up a level, and remove the `pre_routing`
  key.
- Add the entry for `Zend\Expressive\Container\ApplicationFactory::ROUTING_MIDDLEWARE`
  immediately following any `pre_routing` middleware, and before any
  `post_routing` middleware.
- Promote all `post_routing` middleware up a level, and remove the
  `post_routing` key.

Once you have made the above changes, you should no longer receive deprecation
notices when running your application.

## Timeline

Support for the `pre_routing` and `post_routing` configuration will be removed
with the 1.1.0 release.
