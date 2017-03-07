<?php
/**
 * @see       https://github.com/zendframework/zend-expressive for the canonical source repository
 * @copyright Copyright (c) 2015-2017 Zend Technologies USA Inc. (http://www.zend.com)
 * @license   https://github.com/zendframework/zend-expressive/blob/master/LICENSE.md New BSD License
 */

namespace ZendTest\Expressive\Router;

use Fig\Http\Message\RequestMethodInterface as RequestMethod;
use Fig\Http\Message\StatusCodeInterface as StatusCode;
use Interop\Http\ServerMiddleware\DelegateInterface;
use PHPUnit\Framework\TestCase;
use Prophecy\Argument;
use Prophecy\Prophecy\ObjectProphecy;
use Psr\Container\ContainerInterface;
use Zend\Diactoros\Response;
use Zend\Diactoros\ServerRequest;
use Zend\Diactoros\Stream;
use Zend\Expressive\Application;
use Zend\Expressive\Middleware;
use Zend\Expressive\Router\AuraRouter;
use Zend\Expressive\Router\FastRouteRouter;
use Zend\Expressive\Router\RouterInterface;
use Zend\Expressive\Router\ZendRouter;
use ZendTest\Expressive\ContainerTrait;

class IntegrationTest extends TestCase
{
    use ContainerTrait;

    /** @var Response */
    private $response;

    /** @var RouterInterface|ObjectProphecy */
    private $router;

    /** @var ContainerInterface|ObjectProphecy */
    private $container;

    public function setUp()
    {
        $this->response  = new Response();
        $this->router    = $this->prophesize(RouterInterface::class);
        $this->container = $this->mockContainerInterface();
    }

    public function getApplication()
    {
        return new Application(
            $this->router->reveal(),
            $this->container->reveal()
        );
    }

    /**
     * Get the router adapters to test
     */
    public function routerAdapters()
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
     *
     * @param string $adapter
     * @param string $getName
     * @param string $postName
     * @return Application
     */
    private function createApplicationWithGetPost($adapter, $getName = null, $postName = null)
    {
        $app = new Application(new $adapter());
        $app->pipeRoutingMiddleware();

        $app->get('/foo', function ($req, $res, $next) {
            $stream = new Stream('php://temp', 'w+');
            $stream->write('Middleware GET');
            return $res->withBody($stream);
        }, $getName);
        $app->post('/foo', function ($req, $res, $next) {
            $stream = new Stream('php://temp', 'w+');
            $stream->write('Middleware POST');
            return $res->withBody($stream);
        }, $postName);

        return $app;
    }

    /**
     * Create an Application object with 2 routes, a GET and a POST
     * using Application::route()
     *
     * @param string $adapter
     * @param string $getName
     * @param string $postName
     * @return Application
     */
    private function createApplicationWithRouteGetPost($adapter, $getName = null, $postName = null)
    {
        $app = new Application(new $adapter());
        $app->pipeRoutingMiddleware();

        $app->route('/foo', function ($req, $res, $next) {
            $stream = new Stream('php://temp', 'w+');
            $stream->write('Middleware GET');
            return $res->withBody($stream);
        }, ['GET'], $getName);
        $app->route('/foo', function ($req, $res, $next) {
            $stream = new Stream('php://temp', 'w+');
            $stream->write('Middleware POST');
            return $res->withBody($stream);
        }, ['POST'], $postName);

        return $app;
    }

    /**
     * @dataProvider routerAdapters
     *
     * @param string $adapter
     */
    public function testRoutingDoesNotMatchMethod($adapter)
    {
        $app = $this->createApplicationWithGetPost($adapter);
        $delegate = $this->prophesize(DelegateInterface::class);
        $delegate->process(Argument::type(ServerRequest::class))
            ->shouldNotBeCalled();

        $request = new ServerRequest(['REQUEST_METHOD' => 'DELETE'], [], '/foo', 'DELETE');
        $result  = $app->process($request, $delegate->reveal());

        $this->assertSame(StatusCode::STATUS_METHOD_NOT_ALLOWED, $result->getStatusCode());
        $headers = $result->getHeaders();
        $this->assertSame(['GET,POST'], $headers['Allow']);
    }

    /**
     * @see https://github.com/zendframework/zend-expressive/issues/40
     * @group 40
     *
     * @dataProvider routerAdapters
     *
     * @param string $adapter
     */
    public function testRoutingWithSamePathWithoutName($adapter)
    {
        $app = $this->createApplicationWithGetPost($adapter);
        $app->pipeDispatchMiddleware();

        $delegate = $this->prophesize(DelegateInterface::class);
        $delegate->process(Argument::type(ServerRequest::class))
            ->shouldNotBeCalled();

        $request = new ServerRequest(['REQUEST_METHOD' => 'GET'], [], '/foo', 'GET');
        $result  = $app->process($request, $delegate->reveal());

        $this->assertEquals('Middleware GET', (string) $result->getBody());

        $request  = new ServerRequest(['REQUEST_METHOD' => 'POST'], [], '/foo', 'POST');
        $result   = $app->process($request, $delegate->reveal());

        $this->assertEquals('Middleware POST', (string) $result->getBody());
    }

    /**
     * @see https://github.com/zendframework/zend-expressive/issues/40
     * @group 40
     *
     * @dataProvider routerAdapters
     *
     * @param string $adapter
     */
    public function testRoutingWithSamePathWithName($adapter)
    {
        $app = $this->createApplicationWithGetPost($adapter, 'foo-get', 'foo-post');
        $app->pipeDispatchMiddleware();

        $delegate = $this->prophesize(DelegateInterface::class);
        $delegate->process(Argument::type(ServerRequest::class))
            ->shouldNotBeCalled();

        $request = new ServerRequest(['REQUEST_METHOD' => 'GET'], [], '/foo', 'GET');
        $result  = $app->process($request, $delegate->reveal());

        $this->assertEquals('Middleware GET', (string) $result->getBody());

        $request = new ServerRequest(['REQUEST_METHOD' => 'POST'], [], '/foo', 'POST');
        $result  = $app->process($request, $delegate->reveal());

        $this->assertEquals('Middleware POST', (string) $result->getBody());
    }

    /**
     * @see https://github.com/zendframework/zend-expressive/issues/40
     * @group 40
     *
     * @dataProvider routerAdapters
     *
     * @param string $adapter
     */
    public function testRoutingWithSamePathWithRouteWithoutName($adapter)
    {
        $app = $this->createApplicationWithRouteGetPost($adapter);
        $app->pipeDispatchMiddleware();

        $delegate = $this->prophesize(DelegateInterface::class);
        $delegate->process(Argument::type(ServerRequest::class))
            ->shouldNotBeCalled();

        $request = new ServerRequest(['REQUEST_METHOD' => 'GET'], [], '/foo', 'GET');
        $result  = $app->process($request, $delegate->reveal());

        $this->assertEquals('Middleware GET', (string) $result->getBody());

        $request = new ServerRequest(['REQUEST_METHOD' => 'POST'], [], '/foo', 'POST');
        $result  = $app->process($request, $delegate->reveal());

        $this->assertEquals('Middleware POST', (string) $result->getBody());
    }

    /**
     * @see https://github.com/zendframework/zend-expressive/issues/40
     *
     * @dataProvider routerAdapters
     *
     * @param string $adapter
     */
    public function testRoutingWithSamePathWithRouteWithName($adapter)
    {
        $app = $this->createApplicationWithRouteGetPost($adapter, 'foo-get', 'foo-post');
        $app->pipeDispatchMiddleware();

        $delegate = $this->prophesize(DelegateInterface::class);
        $delegate->process(Argument::type(ServerRequest::class))
            ->shouldNotBeCalled();

        $request = new ServerRequest(['REQUEST_METHOD' => 'GET'], [], '/foo', 'GET');
        $result  = $app->process($request, $delegate->reveal());

        $this->assertEquals('Middleware GET', (string) $result->getBody());

        $request = new ServerRequest(['REQUEST_METHOD' => 'POST'], [], '/foo', 'POST');
        $result  = $app->process($request, $delegate->reveal());

        $this->assertEquals('Middleware POST', (string) $result->getBody());
    }

    /**
     * @see https://github.com/zendframework/zend-expressive/issues/40
     * @group 40
     *
     * @dataProvider routerAdapters
     *
     * @param string $adapter
     */
    public function testRoutingWithSamePathWithRouteWithMultipleMethods($adapter)
    {
        $app = new Application(new $adapter());
        $app->pipeRoutingMiddleware();
        $app->pipeDispatchMiddleware();

        $app->route('/foo', function ($req, $res, $next) {
            $stream = new Stream('php://temp', 'w+');
            $stream->write('Middleware GET, POST');
            return $res->withBody($stream);
        }, ['GET', 'POST']);
        $app->route('/foo', function ($req, $res, $next) {
            $stream = new Stream('php://temp', 'w+');
            $stream->write('Middleware DELETE');
            return $res->withBody($stream);
        }, ['DELETE']);

        $delegate = $this->prophesize(DelegateInterface::class);
        $delegate->process(Argument::type(ServerRequest::class))
            ->shouldNotBeCalled();

        $request = new ServerRequest(['REQUEST_METHOD' => 'GET'], [], '/foo', 'GET');
        $result  = $app->process($request, $delegate->reveal());
        $this->assertEquals('Middleware GET, POST', (string) $result->getBody());

        $request = new ServerRequest(['REQUEST_METHOD' => 'POST'], [], '/foo', 'POST');
        $result  = $app->process($request, $delegate->reveal());
        $this->assertEquals('Middleware GET, POST', (string) $result->getBody());

        $request = new ServerRequest(['REQUEST_METHOD' => 'DELETE'], [], '/foo', 'DELETE');
        $result  = $app->process($request, $delegate->reveal());
        $this->assertEquals('Middleware DELETE', (string) $result->getBody());
    }

    public function routerAdaptersForHttpMethods()
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
     *
     * @param string $adapter
     * @param string $method
     */
    public function testMatchWithAllHttpMethods($adapter, $method)
    {
        $app = new Application(new $adapter());
        $app->pipeRoutingMiddleware();
        $app->pipeDispatchMiddleware();

        // Add a route with Zend\Expressive\Router\Route::HTTP_METHOD_ANY
        $app->route('/foo', function ($req, $res, $next) {
            $stream = new Stream('php://temp', 'w+');
            $stream->write('Middleware');
            return $res->withBody($stream);
        });

        $delegate = $this->prophesize(DelegateInterface::class);
        $delegate->process(Argument::type(ServerRequest::class))
            ->shouldNotBeCalled();

        $request = new ServerRequest(['REQUEST_METHOD' => $method], [], '/foo', $method);
        $result  = $app->process($request, $delegate->reveal());
        $this->assertEquals('Middleware', (string) $result->getBody());
    }

    public function allowedMethod()
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

    public function notAllowedMethod()
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
     *
     * @param string $adapter
     * @param string $method
     */
    public function testAllowedMethodsWhenOnlyPutMethodSet($adapter, $method)
    {
        $app = new Application(new $adapter());
        $app->pipeRoutingMiddleware();
        $app->pipe(new Middleware\ImplicitHeadMiddleware());
        $app->pipe(new Middleware\ImplicitOptionsMiddleware());
        $app->pipeDispatchMiddleware();

        // Add a PUT route
        $app->put('/foo', function ($req, $res, $next) {
            $stream = new Stream('php://temp', 'w+');
            $stream->write('Middleware');
            return $res->withBody($stream);
        });

        $delegate = $this->prophesize(DelegateInterface::class);
        $delegate->process(Argument::type(ServerRequest::class))
            ->shouldNotBeCalled();

        $request  = new ServerRequest(['REQUEST_METHOD' => $method], [], '/foo', $method);
        $result   = $app->process($request, $delegate->reveal());

        $this->assertEquals(StatusCode::STATUS_OK, $result->getStatusCode());
        $this->assertEquals('', (string) $result->getBody());
    }

    /**
     * @dataProvider allowedMethod
     *
     * @param string $adapter
     * @param string $method
     */
    public function testAllowedMethodsWhenNoHttpMethodsSet($adapter, $method)
    {
        $app = new Application(new $adapter());
        $app->pipeRoutingMiddleware();
        $app->pipe(new Middleware\ImplicitHeadMiddleware());
        $app->pipe(new Middleware\ImplicitOptionsMiddleware());
        $app->pipeDispatchMiddleware();

        // Add a route with empty array - NO HTTP methods
        $app->route('/foo', function ($req, $res, $next) {
            $stream = new Stream('php://temp', 'w+');
            $stream->write('Middleware');
            return $res->withBody($stream);
        }, []);

        $delegate = $this->prophesize(DelegateInterface::class);
        $delegate->process(Argument::type(ServerRequest::class))
            ->willReturn($this->response);

        $request = new ServerRequest(['REQUEST_METHOD' => $method], [], '/foo', $method);
        $result  = $app->process($request, $delegate->reveal());

        $this->assertEquals(StatusCode::STATUS_OK, $result->getStatusCode());
        $this->assertEquals('', (string) $result->getBody());
    }

    /**
     * @dataProvider notAllowedMethod
     *
     * @param string $adapter
     * @param string $method
     */
    public function testNotAllowedMethodWhenNoHttpMethodsSet($adapter, $method)
    {
        $app = new Application(new $adapter());
        $app->pipeRoutingMiddleware();
        $app->pipeDispatchMiddleware();

        // Add a route with empty array - NO HTTP methods
        $app->route('/foo', function ($req, $res, $next) {
            $stream = new Stream('php://temp', 'w+');
            $stream->write('Middleware');
            return $res->withBody($stream);
        }, []);

        $delegate = $this->prophesize(DelegateInterface::class);
        $delegate->process(Argument::type(ServerRequest::class))
            ->shouldNotBeCalled();

        $request = new ServerRequest(['REQUEST_METHOD' => $method], [], '/foo', $method);
        $result  = $app->process($request, $delegate->reveal());
        $this->assertEquals(StatusCode::STATUS_METHOD_NOT_ALLOWED, $result->getStatusCode());
        $this->assertNotContains('Middleware', (string) $result->getBody());
    }

    /**
     * @group 74
     *
     * @dataProvider routerAdapters
     *
     * @param string $adapter
     */
    public function testWithOnlyRootPathRouteDefinedRoutingToSubPathsShouldDelegate($adapter)
    {
        $app = new Application(new $adapter());
        $app->pipeRoutingMiddleware();

        $app->route('/', function ($req, $res, $next) {
            $stream = new Stream('php://temp', 'w+');
            $stream->write('Middleware');
            return $res->withBody($stream);
        }, ['GET']);

        $expected = (new Response())->withStatus(StatusCode::STATUS_NOT_FOUND);
        $delegate = $this->prophesize(DelegateInterface::class);
        $delegate->process(Argument::type(ServerRequest::class))
            ->willReturn($expected);

        $request = new ServerRequest(['REQUEST_METHOD' => 'GET'], [], '/foo', 'GET');
        $result  = $app->process($request, $delegate->reveal());
        $this->assertSame($expected, $result);
    }
}
