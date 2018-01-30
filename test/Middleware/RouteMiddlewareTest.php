<?php
/**
 * @see       https://github.com/zendframework/zend-expressive for the canonical source repository
 * @copyright Copyright (c) 2017 Zend Technologies USA Inc. (https://www.zend.com)
 * @license   https://github.com/zendframework/zend-expressive/blob/master/LICENSE.md New BSD License
 */

declare(strict_types=1);

namespace ZendTest\Expressive\Middleware;

use Fig\Http\Message\StatusCodeInterface as StatusCode;
use PHPUnit\Framework\TestCase;
use Prophecy\Argument;
use Prophecy\Prophecy\ObjectProphecy;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use TypeError;
use Zend\Expressive\Exception;
use Zend\Expressive\Middleware\RouteMiddleware;
use Zend\Expressive\MiddlewareFactory;
use Zend\Expressive\Router\Route;
use Zend\Expressive\Router\RouteResult;
use Zend\Expressive\Router\RouterInterface;
use ZendTest\Expressive\TestAsset\InteropMiddleware;

use function Zend\Stratigility\middleware;

class RouteMiddlewareTest extends TestCase
{
    /** @var RouterInterface|ObjectProphecy */
    private $router;

    /** @var ResponseInterface|ObjectProphecy */
    private $response;

    /** @var RouteMiddleware */
    private $middleware;

    /** @var ServerRequestInterface|ObjectProphecy */
    private $request;

    /** @var RequestHandlerInterface|ObjectProphecy */
    private $handler;

    public function setUp()
    {
        $this->router     = $this->prophesize(RouterInterface::class);
        $this->response   = $this->prophesize(ResponseInterface::class);
        $this->middleware = new RouteMiddleware(
            $this->router->reveal(),
            $this->response->reveal()
        );

        $this->request  = $this->prophesize(ServerRequestInterface::class);
        $this->handler = $this->prophesize(RequestHandlerInterface::class);
        $this->noopMiddleware = new InteropMiddleware();
    }

    public function commonHttpMethods()
    {
        return [
            'GET'    => ['GET'],
            'POST'   => ['POST'],
            'PUT'    => ['PUT'],
            'PATCH'  => ['PATCH'],
            'DELETE' => ['DELETE'],
        ];
    }

    public function testRoutingFailureDueToHttpMethodCallsNextWithNotAllowedResponseAndError()
    {
        $result = RouteResult::fromRouteFailure(['GET', 'POST']);

        $this->router->match($this->request->reveal())->willReturn($result);
        $this->handler->handle()->shouldNotBeCalled();
        $this->request->withAttribute()->shouldNotBeCalled();
        $this->response->withStatus(StatusCode::STATUS_METHOD_NOT_ALLOWED)->will([$this->response, 'reveal']);
        $this->response->withHeader('Allow', 'GET,POST')->will([$this->response, 'reveal']);

        $response = $this->middleware->process($this->request->reveal(), $this->handler->reveal());
        $this->assertSame($response, $this->response->reveal());
    }

    public function testGeneralRoutingFailureInvokesDelegateWithSameRequest()
    {
        $result = RouteResult::fromRouteFailure(Route::HTTP_METHOD_ANY);

        $this->router->match($this->request->reveal())->willReturn($result);
        $this->response->withStatus()->shouldNotBeCalled();
        $this->response->withHeader()->shouldNotBeCalled();
        $this->request->withAttribute()->shouldNotBeCalled();

        $expected = $this->prophesize(ResponseInterface::class)->reveal();
        $this->handler->handle($this->request->reveal())->willReturn($expected);

        $response = $this->middleware->process($this->request->reveal(), $this->handler->reveal());
        $this->assertSame($expected, $response);
    }

    public function testRoutingSuccessDelegatesToNextAfterFirstInjectingRouteResultAndAttributesInRequest()
    {
        $middleware = $this->prophesize(MiddlewareInterface::class)->reveal();
        $parameters = ['foo' => 'bar', 'baz' => 'bat'];
        $result = RouteResult::fromRoute(
            new Route('/foo', $middleware),
            $parameters
        );

        $this->router->match($this->request->reveal())->willReturn($result);

        $this->request
            ->withAttribute(RouteResult::class, $result)
            ->will([$this->request, 'reveal']);
        foreach ($parameters as $key => $value) {
            $this->request->withAttribute($key, $value)->will([$this->request, 'reveal']);
        }

        $this->response->withStatus()->shouldNotBeCalled();
        $this->response->withHeader()->shouldNotBeCalled();

        $expected = $this->prophesize(ResponseInterface::class)->reveal();
        $this->handler
            ->handle($this->request->reveal())
            ->willReturn($expected);

        $response = $this->middleware->process($this->request->reveal(), $this->handler->reveal());
        $this->assertSame($expected, $response);
    }

    public function testRouteMethodReturnsRouteInstance()
    {
        $this->router->addRoute(Argument::type(Route::class))->shouldBeCalled();
        $route = $this->middleware->route('/foo', $this->noopMiddleware);
        $this->assertInstanceOf(Route::class, $route);
        $this->assertEquals('/foo', $route->getPath());
        $this->assertSame($this->noopMiddleware, $route->getMiddleware());
    }

    public function testAnyRouteMethod()
    {
        $this->router->addRoute(Argument::type(Route::class))->shouldBeCalled();
        $route = $this->middleware->any('/foo', $this->noopMiddleware);
        $this->assertInstanceOf(Route::class, $route);
        $this->assertEquals('/foo', $route->getPath());
        $this->assertSame($this->noopMiddleware, $route->getMiddleware());
        $this->assertSame(Route::HTTP_METHOD_ANY, $route->getAllowedMethods());
    }

    /**
     * @dataProvider commonHttpMethods
     *
     * @param string $method
     */
    public function testCanCallRouteWithHttpMethods($method)
    {
        $this->router->addRoute(Argument::type(Route::class))->shouldBeCalled();
        $route = $this->middleware->route('/foo', $this->noopMiddleware, [$method]);
        $this->assertInstanceOf(Route::class, $route);
        $this->assertEquals('/foo', $route->getPath());
        $this->assertSame($this->noopMiddleware, $route->getMiddleware());
        $this->assertTrue($route->allowsMethod($method));
        $this->assertSame([$method], $route->getAllowedMethods());
    }

    public function testCanCallRouteWithMultipleHttpMethods()
    {
        $this->router->addRoute(Argument::type(Route::class))->shouldBeCalled();
        $methods = array_keys($this->commonHttpMethods());
        $route = $this->middleware->route('/foo', $this->noopMiddleware, $methods);
        $this->assertInstanceOf(Route::class, $route);
        $this->assertEquals('/foo', $route->getPath());
        $this->assertSame($this->noopMiddleware, $route->getMiddleware());
        $this->assertSame($methods, $route->getAllowedMethods());
    }

    public function testCallingRouteWithExistingPathAndOmittingMethodsArgumentRaisesException()
    {
        $this->router->addRoute(Argument::type(Route::class))->shouldBeCalledTimes(2);
        $this->middleware->route('/foo', $this->noopMiddleware);
        $this->middleware->route('/bar', $this->noopMiddleware);
        $this->expectException(Exception\DuplicateRouteException::class);
        $this->middleware->route('/foo', middleware(function ($request, $handler) {
        }));
    }

    public function invalidPathTypes()
    {
        return [
            'null'       => [null],
            'true'       => [true],
            'false'      => [false],
            'zero'       => [0],
            'int'        => [1],
            'zero-float' => [0.0],
            'float'      => [1.1],
            'array'      => [['path' => 'route']],
            'object'     => [(object) ['path' => 'route']],
        ];
    }

    /**
     * @dataProvider invalidPathTypes
     *
     * @param mixed $path
     */
    public function testCallingRouteWithAnInvalidPathTypeRaisesAnException($path)
    {
        $this->expectException(TypeError::class);
        $this->middleware->route($path, new InteropMiddleware());
    }

    /**
     * @dataProvider commonHttpMethods
     *
     * @param mixed $method
     */
    public function testCommonHttpMethodsAreExposedAsClassMethodsAndReturnRoutes($method)
    {
        $route = $this->middleware->{$method}('/foo', $this->noopMiddleware);
        $this->assertInstanceOf(Route::class, $route);
        $this->assertEquals('/foo', $route->getPath());
        $this->assertSame($this->noopMiddleware, $route->getMiddleware());
        $this->assertEquals([$method], $route->getAllowedMethods());
    }

    public function testCreatingHttpRouteMethodWithExistingPathButDifferentMethodCreatesNewRouteInstance()
    {
        $this->router->addRoute(Argument::type(Route::class))->shouldBeCalledTimes(2);
        $route = $this->middleware->route('/foo', $this->noopMiddleware, []);

        $middleware = new InteropMiddleware();
        $test = $this->middleware->get('/foo', $middleware);
        $this->assertNotSame($route, $test);
        $this->assertSame($route->getPath(), $test->getPath());
        $this->assertSame(['GET'], $test->getAllowedMethods());
        $this->assertSame($middleware, $test->getMiddleware());
    }

    public function testCreatingHttpRouteWithExistingPathAndMethodRaisesException()
    {
        $this->router->addRoute(Argument::type(Route::class))->shouldBeCalledTimes(1);
        $this->middleware->get('/foo', $this->noopMiddleware);

        $this->expectException(Exception\DuplicateRouteException::class);
        $this->middleware->get('/foo', middleware(function ($request, $handler) {
        }));
    }
}
