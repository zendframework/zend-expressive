# Passing Data Between Middleware

A frequently asked question is how to pass data between middleware.

The answer is present in every middleware: via request object attributes.

Middleware is always executed in the order in which it is piped to the
application. This way you can ensure the request object in middleware receiving
data contains an attribute containing data passed by outer middleware.

In the following example, `PassingDataMiddleware` prepares data to pass as a
request attribute to nested middleware. We use the fully qualified class name
for the attribute name to ensure uniqueness, but you can name it anything you
want.

```php
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
        
        // Step 3: Optionally, do something (with the response) before returning the response
        
        // Step 4: Return the response
        return $response;
    }
}
```

Later, `ReceivingDataMiddleware` grabs the data and processes it:

```php
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
        
        // Step 3: Optionally, do something (with the response) before returning the response
        
        // Step 4: Return the response
        return $response;
    }
}
```

Of course, you could also use the data in routed middleware, which is usually at
the innermost layer of your application. The `ExampleAction` below takes that
information and passes it to the template renderer to create an `HtmlResponse`:

```php
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
