# Migration from RC5 or earlier

RC6 introduced changes to the following:

- The routing middleware was split into separate middleware, one for routing,
  and one for dispatching.
- Due to the above change, we decided to remove auto-registration of routing
  middleware.
- The above change also suggested an alternative to the middleware pipeline
  configuration that simplifies it.
- Route result observers are deprecated, and no longer triggered for routing
  failures.
- Middleware configuration specifications now accept a `priority` key to
  guarantee the order of items. If you have defined your middleware pipeline in
  multiple files that are then merged, you will need to defined these keys to
  ensure order.

## Routing and Dispatch middleware

Prior to RC6, the routing middleware:

- performed routing
- notified route result observers
- created a new request that composed the matched routing parameters as request
  attributes, and composed the route result instance itself as a request
  attribute.
- marshaled the middleware matched by routing
- dispatched the marshaled middleware

To provide a better separation of concerns, we split the routing middleware into
two distinct methods: `routingMiddleware()` and `dispatchMiddleware()`.

`routingMiddleware()` performs the following duties:

- routing; and
- creating a new request that composes the matched routing parameters as request
  attributes, and composes the route result instance itself as a request
  attribute.

`dispatchMiddleware()` performs the following duties:

- marshaling the middleware specified in the route result; and
- dispatching the marshaled middleware.

One reason for this split is to allow injecting middleware to operate between
routing and dispatch. As an example, you could have middleware that determines
if a matched route requires an authenticated identity:

```php
public function __invoke($request, $response, $next)
{
    $result = $request->getAttribute(RouteResult::class);
    if (! in_array($result->getMatchedRouteName(), $this->authRequired)) {
        return $next($request, $response);
    }

    if (! $this->authenticated) {
        return $next($request, $response->withStatus(401), 'authentication
        required');
    }
}
```

The above could then be piped between the routing and dispatch middleware:

```php
$app->pipeRoutingMiddleware();
$app->pipe(AuthenticationMiddleware::class);
$app->pipeDispatchMiddleware();
```

Since the routing middleware has been split, we determined we could no longer
automatically pipe the routing middleware; detection would require detecting
both sets of middleware, and ensuring they are in the correct order.
Additionally, since one goal of splitting the middleware is to allow
*substitutions* for these responsibilities, auto-injection could in some cases
be undesired. As a result, we now require you to inject each manually.

### Impact

This change will require changes in your application.

1. If you are using Expressive programmatically (i.e., you are not using
   a container and the `Zend\Expressive\Container\ApplicationFactory`),
   you are now *required* to call `Application::pipeRoutingMiddleware()`.
   Additionally, a new method, `Application::pipeDispatchMiddleware()` exists
   for injecting the application with the dispatch middleware, this, too, must
   be called.

   This has a fortunate side effect: registering routed middleware no longer
   affects the middleware pipeline order. As such, you can register your
   pipeline first or last prior to running the application. The only stipulation
   is that _unless you register the routing **and** dispatch middleware, your routed
   middleware will not be executed!_ As such, the following two lines **must**
   be added to your application prior to calling `Application::run()`:

```php
$app->pipeRoutingMiddleware();
$app->pipeDispatchMiddleware();
```

2. If you are creating your `Application` instance using a container and the
   `Zend\Expressive\Container\ApplicationFactory`, you will need to update your
   configuration to list the routing and dispatch middleware. The next section
   details the configuration changes necessary.

## ApplicationFactory configuration changes

As noted in the document summary, the middleware pipeline configuration was
changed starting in RC6.  The changes are done in such a way as to honor
configuration from RC5 and earlier, but using such configuration will now prompt
you to update your application.

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
];
```

The following changes have been made:

- The concept of `pre_routing` and `post_routing` have been deprecated, and will
  be removed starting with the 1.1 version. A single middleware pipeline is now
  provided, though *any individual specification can also specify an array of
  middleware*.
- **The routing and dispatch middleware must now be added to your configuration
  for them to be added to your application.**
- Middleware specifications can now optionally provide a `priority` key, with 1
  being the default. High integer priority indicates earlier execution, while
  low/negative integer priority indicates later execution. Items with the same
  priority are executed in the order they are registered. Priority is now how
  you can indicate the order in which middleware should execute.

### Impact

While the configuration from RC5 and earlier will continue to work, it will
raise deprecation notices. As such, you will need to update your configuration
to follow the guidelines created with RC6.

RC6 and later change the configuration to remove the `pre_routing` and
`post_routing` keys. However, individual items within the array retain the same
format as middleware inside those keys, with the addition of a new key,
`priority`:

```php
[
    // Required:
    'middleware' => 'Name of middleware service, or a callable',
    // Optional:
    //    'path'  => '/path/to/match',
    //    'error' => true,
    //    'priority' => 1, // integer
]
```

The `priority` key is used to determine the order in which middleware is piped
to the application. Higher integer values are piped earlier, while
lower/negative integer values are piped later; middleware with the same priority
are piped in the order in which they are discovered in the pipeline. The default
priority used is 1.

Additionally, the routing and dispatch middleware now become items in the array;
they (or equivalent entries for your own implementations) must be present in
your configuration if you want your routed middleware to dispatch!  This change
gives you full control over the flow of the pipeline.

To specify the routing middleware, use the constant
`Zend\Expressive\Container\ApplicationFactory::ROUTING_MIDDLEWARE` in place of
a middleware array; this has the value `EXPRESSIVE_ROUTING_MIDDLEWARE`, if you
do not want to import the class. Similarly, for the dispatch middleware, use the
constant `Zend\Expressive\Container\ApplicationFactory::DISPATCH_MIDDLEWARE`
(value `EXPRESSIVE_DISPATCH_MIDDLEWARE`) to specify the dispatch middleware.

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
            ],
            'priority' => PHP_INT_MAX,
        ],

        // The following is an entry for:
        // - routing middleware
        // - middleware that reacts to the routing results
        // - dispatch middleware
        [
            'middleware' => [
                Zend\Expressive\Container\ApplicationFactory::ROUTING_MIDDLEWARE,
                Helper\UrlHelperMiddleware::class,
                Zend\Expressive\Container\ApplicationFactory::DISPATCH_MIDDLEWARE,
            ],
            'priority' => 1,
        ]

        // The following is an entry for the dispatch middleware:

        // Place error handling middleware after the routing and dispatch
        // middleware, with negative priority.
        // [
        //     'middleware' => [
        //     ],
        //     'priority' => -1000,
        // ],
    ],
];
```

To update an existing application:

- Promote all `pre_routing` middleware up a level, and remove the `pre_routing`
  key. Provide a `priority` value greater than 1. We recommend having a single
  middleware specification with an array of middleware that represents the "pre
  routing" middleware.
- Add the entries for `Zend\Expressive\Container\ApplicationFactory::ROUTING_MIDDLEWARE`
  and `Zend\Expressive\Container\ApplicationFactory::DISPATCH_MIDDLEWARE`
  immediately following any `pre_routing` middleware, and before any
  `post_routing` middleware; we recommend grouping it per the above example.
- Promote all `post_routing` middleware up a level, and remove the
  `post_routing` key. Provide a `priority` value less than 1 or negative.
- **If you have `middleware_pipeline` specifications in multiple files**, you
  will need to specify `priority` keys for all middleware in order to guarantee
  order after merging. We recommend having a single middleware specification
  with an array of middleware that represents the "post routing" middleware.

As an example, consider the following application configuration:

```php
return [
    'middleware_pipeline' => [
        'pre_routing' => [
            [
                'middleware' => [
                    Zend\Expressive\Helper\ServerUrlMiddleware::class,
                    Zend\Expressive\Helper\UrlHelperMiddleware::class,
                ],
            ],
            ['middleware' => DebugToolbarMiddleware::class],
            [
                'middleware' => ApiMiddleware::class,
                'path' => '/api',
            ],
        ],

        'post_routing' => [
            ['middleware' => NotFoundMiddleware::class, 'error' => true],
        ],
    ],
];
```

This would be rewritten to the following to work with RC6 and later:

```php
return [
    'middleware_pipeline' => [
        'always' => [
            'middleware' => [
                Zend\Expressive\Helper\ServerUrlMiddleware::class,
                DebugToolbarMiddleware::class,
            ],
            'priority' => PHP_INT_MAX,
        ],
        'api' => [
            'middleware' => ApiMiddleware::class,
            'path' => '/api',
            'priority' => 100,
        ],

        'routing' => [
            'middleware' => [
                Zend\Expressive\Container\ApplicationFactory::ROUTING_MIDDLEWARE,
                Zend\Expressive\Helper\UrlHelperMiddleware::class,
                Zend\Expressive\Container\ApplicationFactory::DISPATCH_MIDDLEWARE,
            ],
            'priority' => 1,
        ],

        'error' => [
            'middleware' => [
                NotFoundMiddleware::class,
            ],
            'error' => true,
            'priority' => -1000,
        ],
    ],
]
```

Note in the above example the various groupings. By grouping middleware by
priority, you can simplify adding new middleware, particularly if you know it
should execute before routing, or as error middleware, or between routing and
dispatch.

> #### Keys are ignored
>
> The above example provides keys for each middleware specification. The factory
> will ignore these, but they can be useful for cases when you might want to
> specify configuration in multiple files, and merge specific entries together.
> Be aware, however, that the `middleware` key itself is an indexed array;
> items will be appended based on the order in which configuration files are
> merged. If order of these is important, create separate specifications with
> relevant `priority` values.

## Route result observer deprecation

As of RC6, the following changes have occurred with regards to route result
observers:

- They are deprecated for usage with `Zend\Expressive\Application`, and that
  class will not be a route result subject starting in 1.1. You will need to
  start migrating to alternative solutions.
- The functionality for notifying observers has been moved from the routing
  middleware into a dedicated `Application::routeResultObserverMiddleware()`
  method. This middleware must be piped separately to the middleware pipeline
  for it to trigger.

### Impact

If you are using any route result observers, you will need to ensure your
application notifies them, and you will want to migrate to alternative solutions
to ensure your functionality continues to work.

To ensure your observers are triggered, you will need to adapt your application,
based on how you create your instance.

If you are *not* using the `ApplicationFactory`, you will need to pipe the
`routeResultObserverMiddleware` to your application, between the routing and
dispatch middleware:

```php
$app->pipeRoutingMiddleware();
$app->pipeRouteResultObserverMiddleware();
$app->pipeDispatchMiddleware();
```

If you are using the `ApplicationFactory`, you may need to update your
configuration to allow injecting the route result observer middleware. If you
have *not* updated your configuration to remove the `pre_routing` and/or
`post_routing` keys, the middleware *will* be registered for you. If you have,
however, you will need to register it following the routing middleware:

```php
[
    'middleware_pipeline' => [
        /* ... */
        'routing' => [
            'middleware' => [
                Zend\Expressive\Container\ApplicationFactory::ROUTING_MIDDLEWARE,
                Zend\Expressive\Container\ApplicationFactory::ROUTE_RESULT_OBSERVER_MIDDLEWARE,
                Zend\Expressive\Container\ApplicationFactory::DISPATCH_MIDDLEWARE,
            ],
            'priority' => 1,
        ],
        /* ... */
    ],
]
```

To make your observers forwards-compatible requires two changes:

- Rewriting your observer as middleware.
- Registering your observer as middleware following the routing middleware.

If your observer looked like the following:

```php
use Zend\Expressive\Router\RouteResult;
use Zend\Expressive\Router\RouteResultObserverInterface;

class MyObserver implements RouteResultObserverInterface
{
    private $logger;

    public function __construct(Logger $logger)
    {
        $this->logger = $logger;
    }

    public function update(RouteResult $result)
    {
        $this->logger->log($result);
    }
}
```

You could rewrite it as follows:

```php
use Zend\Expressive\Router\RouteResult;

class MyObserver
{
    private $logger;

    public function __construct(Logger $logger)
    {
        $this->logger = $logger;
    }

    public function __invoke($request, $response, $next)
    {
        $result = $request->getAttribute(RouteResult::class, false);
        if (! $result) {
            return $next($request, $response);
        }

        $this->logger->log($result);
        return $next($request, $response);
    }
}
```

You would then register it following the routing middleware. If you are building
your application programmatically, you would do this as follows:

```php
$app->pipeRoutingMiddleware();
$app->pipe(MyObserver::class);
$app->pipeDispatchMiddleware();
```

If you are using the `ApplicationFactory`, alter your configuration:

```php
[
    'middleware_pipeline' => [
        /* ... */
        'routing' => [
            'middleware' => [
                Zend\Expressive\Container\ApplicationFactory::ROUTING_MIDDLEWARE,
                MyObserver::class,
                Zend\Expressive\Container\ApplicationFactory::DISPATCH_MIDDLEWARE,
            ],
            'priority' => 1,
        ],
        /* ... */
    ],
]
```

## Timeline for migration

The following features will be removed in version 1.1.0:

- Support for the `pre_routing` and `post_routing` configuration.
- Support for route result observers.
