# Migration from RC5 or earlier

RC6 introduced changes to the following:

- The routing middleware was split into separate middleware, one for routing,
  and one for dispatching.
- Due to the above change, we decided to remove auto-registration of routing
  middleware.
- The above change also suggested an alternative to the middleware pipeline
  configuration that simplifies it.

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
]
```

### Impact

While the configuration from RC5 and earlier will continue to work, it will
raise deprecation notices. As such, you will need to update your configuration
to follow the guidelines created with RC6.

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
                Helper\UrlHelperMiddleware::class,
            ],
        ],

        // The following is an entry for the routing middleware:
        Zend\Expressive\Container\ApplicationFactory::ROUTING_MIDDLEWARE,

        // The following is an entry for the dispatch middleware:
        Zend\Expressive\Container\ApplicationFactory::DISPATCH_MIDDLEWARE,

        // Place error handling middleware after the routing and dispatch
        // middleware.
    ],
]
```

To update an existing application:

- Promote all `pre_routing` middleware up a level, and remove the `pre_routing`
  key.
- Add the entries for `Zend\Expressive\Container\ApplicationFactory::ROUTING_MIDDLEWARE`
  and `Zend\Expressive\Container\ApplicationFactory::DISPATCH_MIDDLEWARE`
  immediately following any `pre_routing` middleware, and before any
  `post_routing` middleware.
- Promote all `post_routing` middleware up a level, and remove the
  `post_routing` key.

Once you have made the above changes, you should no longer receive deprecation
notices when running your application.

## Timeline for migration

Support for the `pre_routing` and `post_routing` configuration will be removed
with the 1.1.0 release.
