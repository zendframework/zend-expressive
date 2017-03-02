# Passing Data Between Middleware

A frequently asked question is how to pass data between Middleware. The request 
object can be used for this. The Middleware is always executed in the order it 
is piped to the application. This way you can make sure the request object in 
ReceivingDataMiddleware contains the data set by PassingDataMiddleware.

In the `PassingDataMiddleware` the prepared data is passed as a request attribute 
to the next middleware. In this example the FQNS is used to make sure the used 
attribute is unique, but you can name it anything you want.

```php
<?php

namespace App\Middleware;

use Interop\Http\ServerMiddleware\DelegateInterface;
use Interop\Http\ServerMiddleware\MiddlewareInterface;
use Psr\Http\Message\ServerRequestInterface;

class PassingDataMiddleware implements MiddlewareInterface
{
    // ...

    public function process(ServerRequestInterface $request, DelegateInterface $delegate)
    {
        // Step 1: Do something first
        $data = [
            'foo' => 'bar',  
        ];
        
        // Step 2: Inject data into the request, call the next middleware and wait for the response
        $response = $delegate->process($request->withAttribute(self::class, $data));
        
        // Step 3: Do something (with the response) before returning the response
        
        // Step 4: Return the response
        return $response;
    }
}
```

The `ReceivingDataMiddleware` grabs the data and processes it.

```php
<?php

namespace App\Middleware;

use Interop\Http\ServerMiddleware\DelegateInterface;
use Interop\Http\ServerMiddleware\MiddlewareInterface;
use Psr\Http\Message\ServerRequestInterface;

class ReceivingDataMiddleware implements MiddlewareInterface
{
    // ...

    public function process(ServerRequestInterface $request, DelegateInterface $delegate)
    {
        // Step 1: Grab the data from the request and use it
        $data = $request->getAttribute(PassingDataMiddleware::class);
        
        // Step 2: Call the next middleware and wait for the response
        $response = $delegate->process($request);
        
        // Step 3: Do something (with the response) before returning the response
        
        // Step 4: Return the response
        return $response;
    }
}
```

In stead of passing data to other middleware you can also use the data in 
actions. It's the same concept since an action class is middleware itself. In
the `ExampleAction` the data is subtracted from the request and passed to the
template renderer to create a HtmlResponse.

```php
<?php

namespace App\Action;

use Interop\Http\ServerMiddleware\DelegateInterface;
use Interop\Http\ServerMiddleware\MiddlewareInterface;
use Psr\Http\Message\ServerRequestInterface;
use Zend\Diactoros\Response\HtmlResponse;

class ExampleAction implements MiddlewareInterface
{
    // ...
    
    public function process(ServerRequestInterface $request, DelegateInterface $delegate)
    {
        // Step 1: Grab the data from the request
        $data = $request->getAttribute(PassingDataMiddleware::class);
        $id = $request->getAttribute('id');
        
        // Step 2: Do some more stuff
        
        // Step 3: Return a Response
        return new HtmlResponse(
            $this->templateRenderer->render('blog::entry', [
                'data' => $data,
                'id' => $id,
            ])
        );
    }
}
```
