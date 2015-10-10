<?php
/**
 * Zend Framework (http://framework.zend.com/)
 *
 * @see       https://github.com/zendframework/zend-expressive for the canonical source repository
 * @copyright Copyright (c) 2015 Zend Technologies USA Inc. (http://www.zend.com)
 * @license   https://github.com/zendframework/zend-expressive/blob/master/LICENSE.md New BSD License
 */

namespace ZendTest\Expressive\Router;

use FastRoute\Dispatcher\GroupCountBased as Dispatcher;
use FastRoute\RouteCollector;
use PHPUnit_Framework_TestCase as TestCase;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\UriInterface;
use Zend\Expressive\Router\FastRouteRouter;
use Zend\Expressive\Router\Route;
use Zend\Expressive\Router\RouteResult;

class FastRouteRouterTest extends TestCase
{
    public function setUp()
    {
        $this->fastRouter = $this->prophesize(RouteCollector::class);
        $this->dispatcher = $this->prophesize(Dispatcher::class);
        $this->dispatchCallback = function ($data) {
            return $this->dispatcher->reveal();
        };
    }

    public function getRouter()
    {
        return new FastRouteRouter(
            $this->fastRouter->reveal(),
            $this->dispatchCallback
        );
    }

    public function testWillLazyInstantiateAFastRouteCollectorIfNoneIsProvidedToConstructor()
    {
        $router = new FastRouteRouter();
        $this->assertAttributeInstanceOf(RouteCollector::class, 'router', $router);
    }

    public function testAddingRouteAggregatesRoute()
    {
        $route = new Route('/foo', 'foo', ['GET']);
        $router = $this->getRouter();
        $router->addRoute($route);
        $this->assertAttributeContains($route, 'routesToInject', $router);
    }

    /**
     * @depends testAddingRouteAggregatesRoute
     */
    public function testMatchingInjectsRouteIntoFastRoute()
    {
        $route = new Route('/foo', 'foo', ['GET']);
        $this->fastRouter->addRoute(['GET'], '/foo', '/foo')->shouldBeCalled();
        $this->fastRouter->getData()->shouldBeCalled();
        $this->dispatcher->dispatch('GET', '/foo')->willReturn([
            Dispatcher::NOT_FOUND,
        ]);

        $router = $this->getRouter();
        $router->addRoute($route);

        $uri = $this->prophesize(UriInterface::class);
        $uri->getPath()->willReturn('/foo');

        $request = $this->prophesize(ServerRequestInterface::class);
        $request->getUri()->will(function () use ($uri) {
            return $uri->reveal();
        });
        $request->getMethod()->willReturn('GET');

        $router->match($request->reveal());
    }

    /**
     * @depends testAddingRouteAggregatesRoute
     */
    public function testGeneratingUriInjectsRouteIntoFastRoute()
    {
        $route = new Route('/foo', 'foo', ['GET'], 'foo');
        $this->fastRouter->addRoute(['GET'], '/foo', '/foo')->shouldBeCalled();

        $router = $this->getRouter();
        $router->addRoute($route);

        $this->assertEquals('/foo', $router->generateUri('foo'));
    }

    public function testIfRouteSpecifiesAnyHttpMethodFastRouteIsPassedHardCodedListOfMethods()
    {
        $route = new Route('/foo', 'foo');
        $this->fastRouter->addRoute([
            'GET',
            'POST',
            'PUT',
            'PATCH',
            'DELETE',
            'HEAD',
            'OPTIONS',
            'TRACE'
        ], '/foo', '/foo')->shouldBeCalled();

        $router = $this->getRouter();
        $router->addRoute($route);

        // routes are not injected until match or generateUri
        $router->generateUri($route->getName());
    }

    public function testIfRouteSpecifiesNoHttpMethodsFastRouteIsPassedHardCodedListOfMethods()
    {
        $route = new Route('/foo', 'foo', []);
        $this->fastRouter->addRoute([
            'GET',
            'HEAD',
            'OPTIONS',
        ], '/foo', '/foo')->shouldBeCalled();

        $router = $this->getRouter();
        $router->addRoute($route);

        // routes are not injected until match or generateUri
        $router->generateUri($route->getName());
    }

    public function testMatchingRouteShouldReturnSuccessfulRouteResult()
    {
        $route = new Route('/foo', 'foo', ['GET']);

        $uri     = $this->prophesize(UriInterface::class);
        $uri->getPath()->willReturn('/foo');

        $request = $this->prophesize(ServerRequestInterface::class);
        $request->getUri()->willReturn($uri);
        $request->getMethod()->willReturn('GET');

        $this->dispatcher->dispatch('GET', '/foo')->willReturn([
            Dispatcher::FOUND,
            '/foo',
            ['bar' => 'baz']
        ]);

        $this->fastRouter->addRoute(['GET'], '/foo', '/foo')->shouldBeCalled();
        $this->fastRouter->getData()->shouldBeCalled();

        $router = $this->getRouter();
        $router->addRoute($route); // Must add, so we can determine middleware later
        $result = $router->match($request->reveal());
        $this->assertInstanceOf(RouteResult::class, $result);
        $this->assertTrue($result->isSuccess());
        $this->assertEquals('/foo', $result->getMatchedRouteName());
        $this->assertEquals('foo', $result->getMatchedMiddleware());
        $this->assertSame(['bar' => 'baz'], $result->getMatchedParams());
    }

    public function testMatchFailureDueToHttpMethodReturnsRouteResultWithAllowedMethods()
    {
        $route = new Route('/foo', 'foo', ['POST']);

        $uri     = $this->prophesize(UriInterface::class);
        $uri->getPath()->willReturn('/foo');

        $request = $this->prophesize(ServerRequestInterface::class);
        $request->getUri()->willReturn($uri);
        $request->getMethod()->willReturn('GET');

        $this->dispatcher->dispatch('GET', '/foo')->willReturn([
            Dispatcher::METHOD_NOT_ALLOWED,
            ['POST']
        ]);

        $this->fastRouter->addRoute(['POST'], '/foo', '/foo')->shouldBeCalled();
        $this->fastRouter->getData()->shouldBeCalled();

        $router = $this->getRouter();
        $router->addRoute($route); // Must add, so we can determine middleware later
        $result = $router->match($request->reveal());
        $this->assertInstanceOf(RouteResult::class, $result);
        $this->assertTrue($result->isFailure());
        $this->assertTrue($result->isMethodFailure());
        $this->assertSame(['POST'], $result->getAllowedMethods());
    }

    public function testMatchFailureNotDueToHttpMethodReturnsGenericRouteFailureResult()
    {
        $route = new Route('/foo', 'foo', ['GET']);

        $uri     = $this->prophesize(UriInterface::class);
        $uri->getPath()->willReturn('/bar');

        $request = $this->prophesize(ServerRequestInterface::class);
        $request->getUri()->willReturn($uri);
        $request->getMethod()->willReturn('GET');

        $this->dispatcher->dispatch('GET', '/bar')->willReturn([
            Dispatcher::NOT_FOUND,
        ]);

        $this->fastRouter->addRoute(['GET'], '/foo', '/foo')->shouldBeCalled();
        $this->fastRouter->getData()->shouldBeCalled();

        $router = $this->getRouter();
        $router->addRoute($route); // Must add, so we can determine middleware later
        $result = $router->match($request->reveal());
        $this->assertInstanceOf(RouteResult::class, $result);
        $this->assertTrue($result->isFailure());
        $this->assertFalse($result->isMethodFailure());
        $this->assertSame([], $result->getAllowedMethods());
    }

    /**
     * @group 53
     */
    public function testCanGenerateUriFromRoutes()
    {
        $router = new FastRouteRouter();
        $route1 = new Route('/foo', 'foo', ['POST'], 'foo-create');
        $route2 = new Route('/foo', 'foo', ['GET'], 'foo-list');
        $route3 = new Route('/foo/{id:\d+}', 'foo', ['GET'], 'foo');
        $route4 = new Route('/bar/{baz}', 'bar', Route::HTTP_METHOD_ANY, 'bar');

        $router->addRoute($route1);
        $router->addRoute($route2);
        $router->addRoute($route3);
        $router->addRoute($route4);

        $this->assertEquals('/foo', $router->generateUri('foo-create'));
        $this->assertEquals('/foo', $router->generateUri('foo-list'));
        $this->assertEquals('/foo/42', $router->generateUri('foo', ['id' => 42]));
        $this->assertEquals('/bar/BAZ', $router->generateUri('bar', ['baz' => 'BAZ']));
    }
}
