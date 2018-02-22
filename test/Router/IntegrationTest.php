<?php
/**
 * @see       https://github.com/zendframework/zend-expressive for the canonical source repository
 * @copyright Copyright (c) 2015-2017 Zend Technologies USA Inc. (https://www.zend.com)
 * @license   https://github.com/zendframework/zend-expressive/blob/master/LICENSE.md New BSD License
 */

declare(strict_types=1);

namespace ZendTest\Expressive\Router;

use Fig\Http\Message\RequestMethodInterface as RequestMethod;
use Fig\Http\Message\StatusCodeInterface as StatusCode;
use Generator;
use PHPUnit\Framework\Assert;
use PHPUnit\Framework\TestCase;
use Prophecy\Argument;
use Prophecy\Prophecy\ObjectProphecy;
use Psr\Container\ContainerInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Zend\Diactoros\Response;
use Zend\Diactoros\ServerRequest;
use Zend\Diactoros\Stream;
use Zend\Expressive\Application;
use Zend\Expressive\MiddlewareContainer;
use Zend\Expressive\MiddlewareFactory;
use Zend\Expressive\Router\AuraRouter;
use Zend\Expressive\Router\FastRouteRouter;
use Zend\Expressive\Router\Middleware\DispatchMiddleware;
use Zend\Expressive\Router\Middleware\ImplicitHeadMiddleware;
use Zend\Expressive\Router\Middleware\ImplicitOptionsMiddleware;
use Zend\Expressive\Router\Middleware\MethodNotAllowedMiddleware;
use Zend\Expressive\Router\Middleware\PathBasedRoutingMiddleware as RouteMiddleware;
use Zend\Expressive\Router\RouteResult;
use Zend\Expressive\Router\RouterInterface;
use Zend\Expressive\Router\ZendRouter;
use Zend\HttpHandlerRunner\RequestHandlerRunner;
use Zend\Stratigility\MiddlewarePipe;
use ZendTest\Expressive\ContainerTrait;

use function array_pop;
use function sprintf;

class IntegrationTest extends TestCase
{
    use ContainerTrait;

    /** @var Response */
    private $response;

    /** @var callable */
    private $responseFactory;

    /** @var RouterInterface|ObjectProphecy */
    private $router;

    /** @var ContainerInterface|ObjectProphecy */
    private $container;

    protected function setUp()
    {
        $this->response  = new Response();
        $this->responseFactory = function () {
            return $this->response;
        };
        $this->router    = $this->prophesize(RouterInterface::class);
        $this->container = $this->mockContainerInterface();
    }

    private function createApplicationFromRouter(RouterInterface $router) : Application
    {
        $container = new MiddlewareContainer($this->container->reveal());
        $factory = new MiddlewareFactory($container);
        $pipeline = new MiddlewarePipe();
        $routeMiddleware = new RouteMiddleware($router);
        $runner = $this->prophesize(RequestHandlerRunner::class)->reveal();
        return new Application(
            $factory,
            $pipeline,
            $routeMiddleware,
            $runner
        );
    }

    /**
     * Get the router adapters to test
     */
    public function routerAdapters() : array
    {
        return [
            'aura'       => [AuraRouter::class],
            'fast-route' => [FastRouteRouter::class],
            'zf2'        => [ZendRouter::class],
        ];
    }

    /**
     * Create an Application object with 2 routes, a GET and a POST
     * using Application::get() and Application::post()
     */
    private function createApplicationWithGetPost(
        string $adapter,
        string $getName = null,
        string $postName = null
    ) : Application {
        $router = new $adapter();
        $app = $this->createApplicationFromRouter($router);
        $app->pipe(new RouteMiddleware($router));
        $app->pipe(new MethodNotAllowedMiddleware($this->responseFactory));

        $app->get('/foo', function ($req, $handler) {
            $stream = new Stream('php://temp', 'w+');
            $stream->write('Middleware GET');
            return $this->response->withBody($stream);
        }, $getName);
        $app->post('/foo', function ($req, $handler) {
            $stream = new Stream('php://temp', 'w+');
            $stream->write('Middleware POST');
            return $this->response->withBody($stream);
        }, $postName);

        return $app;
    }

    /**
     * Create an Application object with 2 routes, a GET and a POST
     * using Application::route()
     */
    private function createApplicationWithRouteGetPost(
        string $adapter,
        string $getName = null,
        string $postName = null
    ) : Application {
        $router = new $adapter();
        $app = $this->createApplicationFromRouter($router);
        $app->pipe(new RouteMiddleware($router));
        $app->pipe(new MethodNotAllowedMiddleware($this->responseFactory));

        $app->route('/foo', function ($req, $handler) {
            $stream = new Stream('php://temp', 'w+');
            $stream->write('Middleware GET');
            return $this->response->withBody($stream);
        }, ['GET'], $getName);
        $app->route('/foo', function ($req, $handler) {
            $stream = new Stream('php://temp', 'w+');
            $stream->write('Middleware POST');
            return $this->response->withBody($stream);
        }, ['POST'], $postName);

        return $app;
    }

    /**
     * @dataProvider routerAdapters
     */
    public function testRoutingDoesNotMatchMethod(string $adapter)
    {
        $app = $this->createApplicationWithGetPost($adapter);
        $handler = $this->prophesize(RequestHandlerInterface::class);
        $handler->handle(Argument::type(ServerRequest::class))
                ->shouldNotBeCalled();

        $request = new ServerRequest(['REQUEST_METHOD' => 'DELETE'], [], '/foo', 'DELETE');
        $result  = $app->process($request, $handler->reveal());

        $this->assertSame(StatusCode::STATUS_METHOD_NOT_ALLOWED, $result->getStatusCode());
        $headers = $result->getHeaders();
        $this->assertSame(['GET,POST'], $headers['Allow']);
    }

    /**
     * @see https://github.com/zendframework/zend-expressive/issues/40
     * @group 40
     *
     * @dataProvider routerAdapters
     */
    public function testRoutingWithSamePathWithoutName(string $adapter)
    {
        $app = $this->createApplicationWithGetPost($adapter);
        $app->pipe(new DispatchMiddleware());

        $handler = $this->prophesize(RequestHandlerInterface::class);
        $handler->handle(Argument::type(ServerRequest::class))
                ->shouldNotBeCalled();

        $request = new ServerRequest(['REQUEST_METHOD' => 'GET'], [], '/foo', 'GET');
        $result  = $app->process($request, $handler->reveal());

        $this->assertEquals('Middleware GET', (string) $result->getBody());

        $request  = new ServerRequest(['REQUEST_METHOD' => 'POST'], [], '/foo', 'POST');
        $result   = $app->process($request, $handler->reveal());

        $this->assertEquals('Middleware POST', (string) $result->getBody());
    }

    /**
     * @see https://github.com/zendframework/zend-expressive/issues/40
     * @group 40
     *
     * @dataProvider routerAdapters
     */
    public function testRoutingWithSamePathWithName(string $adapter)
    {
        $app = $this->createApplicationWithGetPost($adapter, 'foo-get', 'foo-post');
        $app->pipe(new DispatchMiddleware());

        $handler = $this->prophesize(RequestHandlerInterface::class);
        $handler
            ->handle(Argument::type(ServerRequest::class))
            ->shouldNotBeCalled();

        $request = new ServerRequest(['REQUEST_METHOD' => 'GET'], [], '/foo', 'GET');
        $result  = $app->process($request, $handler->reveal());

        $this->assertEquals('Middleware GET', (string) $result->getBody());

        $request = new ServerRequest(['REQUEST_METHOD' => 'POST'], [], '/foo', 'POST');
        $result  = $app->process($request, $handler->reveal());

        $this->assertEquals('Middleware POST', (string) $result->getBody());
    }

    /**
     * @see https://github.com/zendframework/zend-expressive/issues/40
     * @group 40
     *
     * @dataProvider routerAdapters
     */
    public function testRoutingWithSamePathWithRouteWithoutName(string $adapter)
    {
        $app = $this->createApplicationWithRouteGetPost($adapter);
        $app->pipe(new DispatchMiddleware());

        $handler = $this->prophesize(RequestHandlerInterface::class);
        $handler->handle(Argument::type(ServerRequest::class))
                ->shouldNotBeCalled();

        $request = new ServerRequest(['REQUEST_METHOD' => 'GET'], [], '/foo', 'GET');
        $result  = $app->process($request, $handler->reveal());

        $this->assertEquals('Middleware GET', (string) $result->getBody());

        $request = new ServerRequest(['REQUEST_METHOD' => 'POST'], [], '/foo', 'POST');
        $result  = $app->process($request, $handler->reveal());

        $this->assertEquals('Middleware POST', (string) $result->getBody());
    }

    /**
     * @see https://github.com/zendframework/zend-expressive/issues/40
     *
     * @dataProvider routerAdapters
     */
    public function testRoutingWithSamePathWithRouteWithName(string $adapter)
    {
        $app = $this->createApplicationWithRouteGetPost($adapter, 'foo-get', 'foo-post');
        $app->pipe(new DispatchMiddleware());

        $handler = $this->prophesize(RequestHandlerInterface::class);
        $handler
            ->handle(Argument::type(ServerRequest::class))
            ->shouldNotBeCalled();

        $request = new ServerRequest(['REQUEST_METHOD' => 'GET'], [], '/foo', 'GET');
        $result  = $app->process($request, $handler->reveal());

        $this->assertEquals('Middleware GET', (string) $result->getBody());

        $request = new ServerRequest(['REQUEST_METHOD' => 'POST'], [], '/foo', 'POST');
        $result  = $app->process($request, $handler->reveal());

        $this->assertEquals('Middleware POST', (string) $result->getBody());
    }

    /**
     * @see https://github.com/zendframework/zend-expressive/issues/40
     * @group 40
     *
     * @dataProvider routerAdapters
     */
    public function testRoutingWithSamePathWithRouteWithMultipleMethods(string $adapter)
    {
        $router = new $adapter();
        $app = $this->createApplicationFromRouter($router);
        $app->pipe(new RouteMiddleware($router));
        $app->pipe(new MethodNotAllowedMiddleware($this->responseFactory));
        $app->pipe(new DispatchMiddleware());

        $response = clone $this->response;
        $app->route('/foo', function ($req, $handler) use ($response) {
            $stream = new Stream('php://temp', 'w+');
            $stream->write('Middleware GET, POST');
            return $response->withBody($stream);
        }, ['GET', 'POST']);

        $deleteResponse = clone $this->response;
        $app->route('/foo', function ($req, $handler) use ($deleteResponse) {
            $stream = new Stream('php://temp', 'w+');
            $stream->write('Middleware DELETE');
            return $deleteResponse->withBody($stream);
        }, ['DELETE']);

        $handler = $this->prophesize(RequestHandlerInterface::class);
        $handler
            ->handle(Argument::type(ServerRequest::class))
            ->shouldNotBeCalled();

        $request = new ServerRequest(['REQUEST_METHOD' => 'GET'], [], '/foo', 'GET');
        $result  = $app->process($request, $handler->reveal());
        $this->assertEquals('Middleware GET, POST', (string) $result->getBody());

        $request = new ServerRequest(['REQUEST_METHOD' => 'POST'], [], '/foo', 'POST');
        $result  = $app->process($request, $handler->reveal());
        $this->assertEquals('Middleware GET, POST', (string) $result->getBody());

        $request = new ServerRequest(['REQUEST_METHOD' => 'DELETE'], [], '/foo', 'DELETE');
        $result  = $app->process($request, $handler->reveal());
        $this->assertEquals('Middleware DELETE', (string) $result->getBody());
    }

    public function routerAdaptersForHttpMethods() : Generator
    {
        $allMethods = [
            RequestMethod::METHOD_GET,
            RequestMethod::METHOD_POST,
            RequestMethod::METHOD_PUT,
            RequestMethod::METHOD_DELETE,
            RequestMethod::METHOD_PATCH,
            RequestMethod::METHOD_HEAD,
            RequestMethod::METHOD_OPTIONS,
        ];
        foreach ($this->routerAdapters() as $adapterName => $adapter) {
            $adapter = array_pop($adapter);
            foreach ($allMethods as $method) {
                $name = sprintf('%s-%s', $adapterName, $method);
                yield $name => [$adapter, $method];
            }
        }
    }

    /**
     * @dataProvider routerAdaptersForHttpMethods
     */
    public function testMatchWithAllHttpMethods(string $adapter, string $method)
    {
        $router = new $adapter();
        $app = $this->createApplicationFromRouter($router);
        $app->pipe(new RouteMiddleware($router));
        $app->pipe(new MethodNotAllowedMiddleware($this->responseFactory));
        $app->pipe(new DispatchMiddleware());

        // Add a route with Zend\Expressive\Router\Route::HTTP_METHOD_ANY
        $response = clone $this->response;
        $app->route('/foo', function ($req, $handler) use ($response) {
            $stream = new Stream('php://temp', 'w+');
            $stream->write('Middleware');
            return $response->withBody($stream);
        });

        $handler = $this->prophesize(RequestHandlerInterface::class);
        $handler
            ->handle(Argument::type(ServerRequest::class))
            ->shouldNotBeCalled();

        $request = new ServerRequest(['REQUEST_METHOD' => $method], [], '/foo', $method);
        $result  = $app->process($request, $handler->reveal());
        $this->assertEquals('Middleware', (string) $result->getBody());
    }

    public function allowedMethod() : array
    {
        return [
            'aura-head'          => [AuraRouter::class, RequestMethod::METHOD_HEAD],
            'aura-options'       => [AuraRouter::class, RequestMethod::METHOD_OPTIONS],
            'fast-route-head'    => [FastRouteRouter::class, RequestMethod::METHOD_HEAD],
            'fast-route-options' => [FastRouteRouter::class, RequestMethod::METHOD_OPTIONS],
            'zf2-head'           => [ZendRouter::class, RequestMethod::METHOD_HEAD],
            'zf2-options'        => [ZendRouter::class, RequestMethod::METHOD_OPTIONS],
        ];
    }

    public function notAllowedMethod() : array
    {
        return [
            'aura-get'          => [AuraRouter::class, RequestMethod::METHOD_GET],
            'aura-post'         => [AuraRouter::class, RequestMethod::METHOD_POST],
            'aura-put'          => [AuraRouter::class, RequestMethod::METHOD_PUT],
            'aura-delete'       => [AuraRouter::class, RequestMethod::METHOD_DELETE],
            'aura-patch'        => [AuraRouter::class, RequestMethod::METHOD_PATCH],
            'fast-route-post'   => [FastRouteRouter::class, RequestMethod::METHOD_POST],
            'fast-route-put'    => [FastRouteRouter::class, RequestMethod::METHOD_PUT],
            'fast-route-delete' => [FastRouteRouter::class, RequestMethod::METHOD_DELETE],
            'fast-route-patch'  => [FastRouteRouter::class, RequestMethod::METHOD_PATCH],
            'zf2-get'           => [ZendRouter::class, RequestMethod::METHOD_GET],
            'zf2-post'          => [ZendRouter::class, RequestMethod::METHOD_POST],
            'zf2-put'           => [ZendRouter::class, RequestMethod::METHOD_PUT],
            'zf2-delete'        => [ZendRouter::class, RequestMethod::METHOD_DELETE],
            'zf2-patch'         => [ZendRouter::class, RequestMethod::METHOD_PATCH],
        ];
    }

    /**
     * @dataProvider allowedMethod
     */
    public function testAllowedMethodsWhenOnlyPutMethodSet(string $adapter, string $method)
    {
        $router = new $adapter();
        $app = $this->createApplicationFromRouter($router);
        $app->pipe(new RouteMiddleware($router));
        $app->pipe(new MethodNotAllowedMiddleware($this->responseFactory));
        $app->pipe(new ImplicitHeadMiddleware($this->responseFactory, function () {
        }));
        $app->pipe(new ImplicitOptionsMiddleware($this->responseFactory));
        $app->pipe(new DispatchMiddleware());

        // Add a PUT route
        $app->put('/foo', function ($req, $res, $next) {
            $stream = new Stream('php://temp', 'w+');
            $stream->write('Middleware');
            return $res->withBody($stream);
        });

        $handler = $this->prophesize(RequestHandlerInterface::class);
        $handler->handle(Argument::type(ServerRequest::class))
                ->shouldNotBeCalled();

        $request  = new ServerRequest(['REQUEST_METHOD' => $method], [], '/foo', $method);
        $result   = $app->process($request, $handler->reveal());

        $this->assertEquals(StatusCode::STATUS_OK, $result->getStatusCode());
        $this->assertEquals('', (string) $result->getBody());
    }

    /**
     * @dataProvider allowedMethod
     */
    public function testAllowedMethodsWhenNoHttpMethodsSet(string $adapter, string $method)
    {
        $router = new $adapter();
        $app = $this->createApplicationFromRouter($router);
        $app->pipe(new RouteMiddleware($router));
        $app->pipe(new MethodNotAllowedMiddleware($this->responseFactory));

        // This middleware is used just to check that request has successful RouteResult
        $middleware = $this->prophesize(MiddlewareInterface::class);
        $middleware->process(Argument::that(function (ServerRequestInterface $req) {
                       $routeResult = $req->getAttribute(RouteResult::class);

                       Assert::assertInstanceOf(RouteResult::class, $routeResult);
                       Assert::assertTrue($routeResult->isSuccess());
                       Assert::assertFalse($routeResult->isMethodFailure());

                       return true;
                   }), Argument::any())
                   ->will(function (array $args) {
                       return $args[1]->handle($args[0]);
                   });

        $app->pipe($middleware->reveal());

        if ($method === 'HEAD') {
            $app->pipe(new ImplicitHeadMiddleware($this->responseFactory, function () {
            }));
        }
        if ($method === 'OPTIONS') {
            $app->pipe(new ImplicitOptionsMiddleware($this->responseFactory));
        }
        $app->pipe(new DispatchMiddleware());

        // Add a route with empty array - NO HTTP methods
        $app->route('/foo', function ($req, $res, $next) {
            $stream = new Stream('php://temp', 'w+');
            $stream->write('Middleware');
            return $res->withBody($stream);
        }, []);

        $handler = $this->prophesize(RequestHandlerInterface::class);
        $handler->handle(Argument::type(ServerRequest::class))
                ->willReturn($this->response);

        $request = new ServerRequest(['REQUEST_METHOD' => $method], [], '/foo', $method);
        $result  = $app->process($request, $handler->reveal());

        $this->assertEquals(StatusCode::STATUS_OK, $result->getStatusCode());
        $this->assertEquals('', (string) $result->getBody());
    }

    /**
     * @dataProvider notAllowedMethod
     */
    public function testNotAllowedMethodWhenNoHttpMethodsSet(string $adapter, string $method)
    {
        $router = new $adapter();
        $app = $this->createApplicationFromRouter($router);
        $app->pipe(new RouteMiddleware($router));
        $app->pipe(new MethodNotAllowedMiddleware($this->responseFactory));
        $app->pipe(new DispatchMiddleware());

        // Add a route with empty array - NO HTTP methods
        $app->route('/foo', function ($req, $res, $next) {
            $stream = new Stream('php://temp', 'w+');
            $stream->write('Middleware');
            return $res->withBody($stream);
        }, []);

        $handler = $this->prophesize(RequestHandlerInterface::class);
        $handler->handle(Argument::type(ServerRequest::class))
                ->shouldNotBeCalled();

        $request = new ServerRequest(['REQUEST_METHOD' => $method], [], '/foo', $method);
        $result  = $app->process($request, $handler->reveal());
        $this->assertEquals(StatusCode::STATUS_METHOD_NOT_ALLOWED, $result->getStatusCode());
        $this->assertNotContains('Middleware', (string) $result->getBody());
    }

    /**
     * @group 74
     *
     * @dataProvider routerAdapters
     */
    public function testWithOnlyRootPathRouteDefinedRoutingToSubPathsShouldDelegate(string $adapter)
    {
        $router = new $adapter();
        $app = $this->createApplicationFromRouter($router);
        $app->pipe(new RouteMiddleware($router));

        $response = clone $this->response;
        $app->route('/', function ($req, $handler) use ($response) {
            $stream = new Stream('php://temp', 'w+');
            $stream->write('Middleware');
            return $response->withBody($stream);
        }, ['GET']);

        $expected = $this->response->withStatus(StatusCode::STATUS_NOT_FOUND);
        $handler = $this->prophesize(RequestHandlerInterface::class);
        $handler
            ->handle(Argument::type(ServerRequest::class))
            ->willReturn($expected);

        $request = new ServerRequest(['REQUEST_METHOD' => 'GET'], [], '/foo', 'GET');
        $result  = $app->process($request, $handler->reveal());
        $this->assertSame($expected, $result);
    }
}
