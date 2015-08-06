<?php
/**
 * Zend Framework (http://framework.zend.com/)
 *
 * @see       https://github.com/zendframework/zend-diactoros for the canonical source repository
 * @copyright Copyright (c) 2015 Zend Technologies USA Inc. (http://www.zend.com)
 * @license   https://github.com/zendframework/zend-diactoros/blob/master/LICENSE.md New BSD License
 */

namespace ZendTest\Expressive\Router;

use PHPUnit_Framework_TestCase as TestCase;
use Zend\Expressive\Router\Aura as AuraRouter;
use Zend\Expressive\Router\Route;

class AuraRouteTest extends TestCase
{
    public function setUp()
    {
        $this->auraRouter = $this->prophesize('Aura\Router\Router');
        $this->auraRoute  = $this->prophesize('Aura\Router\Route');
    }

    public function getRouter()
    {
        return new AuraRouter($this->auraRouter->reveal());
    }

    public function testAddingRouteProxiesToAuraRouter()
    {
        $route = new Route('/foo', 'foo', ['GET']);

        $this->auraRoute->setServer([
            'REQUEST_METHOD' => 'GET',
        ])->shouldBeCalled();
        $this->auraRouter->add('/foo', '/foo', 'foo')->willReturn($this->auraRoute->reveal());

        $router = $this->getRouter();
        $router->addRoute($route);
    }

    public function testCanSpecifyAuraRouteTokensViaRouteOptions()
    {
        $route = new Route('/foo', 'foo', ['GET']);
        $route->setOptions(['tokens' => ['foo' => 'bar']]);

        $this->auraRoute->setServer([
            'REQUEST_METHOD' => 'GET',
        ])->shouldBeCalled();
        $this->auraRoute->addTokens($route->getOptions()['tokens'])->shouldBeCalled();

        $this->auraRouter->add('/foo', '/foo', 'foo')->willReturn($this->auraRoute->reveal());

        $router = $this->getRouter();
        $router->addRoute($route);
    }

    public function testCanSpecifyAuraRouteValuesViaRouteOptions()
    {
        $route = new Route('/foo', 'foo', ['GET']);
        $route->setOptions(['values' => ['foo' => 'bar']]);

        $this->auraRoute->setServer([
            'REQUEST_METHOD' => 'GET',
        ])->shouldBeCalled();
        $this->auraRoute->addValues($route->getOptions()['values'])->shouldBeCalled();

        $this->auraRouter->add('/foo', '/foo', 'foo')->willReturn($this->auraRoute->reveal());

        $router = $this->getRouter();
        $router->addRoute($route);
    }

    public function testMatchingRouteShouldReturnSuccessfulRouteResult()
    {
        $uri     = $this->prophesize('Psr\Http\Message\UriInterface');
        $uri->getPath()->willReturn('/foo');

        $request = $this->prophesize('Psr\Http\Message\ServerRequestInterface');
        $request->getUri()->willReturn($uri);
        $request->getServerParams()->willReturn([
            'REQUEST_METHOD' => 'GET',
        ]);

        $auraRoute = new TestAsset\AuraRoute;
        $auraRoute->name = '/foo';
        $auraRoute->params = [
            'action' => 'foo',
            'bar'    => 'baz',
        ];

        $this->auraRouter->match('/foo', ['REQUEST_METHOD' => 'GET'])->willReturn($auraRoute);

        $router = $this->getRouter();
        $result = $router->match($request->reveal());
        $this->assertInstanceOf('Zend\Expressive\Router\RouteResult', $result);
        $this->assertTrue($result->isSuccess());
        $this->assertEquals('/foo', $result->getMatchedRouteName());
        $this->assertEquals('foo', $result->getMatchedMiddleware());
        $this->assertSame([
            'action' => 'foo',
            'bar'    => 'baz',
        ], $result->getMatchedParams());
    }

    public function testMatchFailureDueToHttpMethodReturnsRouteResultWithAllowedMethods()
    {
        $route = new Route('/foo', 'foo', ['POST']);

        $uri     = $this->prophesize('Psr\Http\Message\UriInterface');
        $uri->getPath()->willReturn('/foo');

        $request = $this->prophesize('Psr\Http\Message\ServerRequestInterface');
        $request->getUri()->willReturn($uri);
        $request->getServerParams()->willReturn([
            'REQUEST_METHOD' => 'GET',
        ]);

        $this->auraRouter->match('/foo', ['REQUEST_METHOD' => 'GET'])->willReturn(false);

        $auraRoute = new TestAsset\AuraRoute;
        $auraRoute->method = ['POST'];

        $this->auraRouter->getFailedRoute()->willReturn($auraRoute);

        $router = $this->getRouter();
        $result = $router->match($request->reveal());
        $this->assertInstanceOf('Zend\Expressive\Router\RouteResult', $result);
        $this->assertTrue($result->isFailure());
        $this->assertTrue($result->isMethodFailure());
        $this->assertSame(['POST'], $result->getAllowedMethods());
    }

    public function testMatchFailureNotDueToHttpMethodReturnsGenericRouteFailureResult()
    {
        $route = new Route('/foo', 'foo', ['GET']);

        $uri     = $this->prophesize('Psr\Http\Message\UriInterface');
        $uri->getPath()->willReturn('/bar');

        $request = $this->prophesize('Psr\Http\Message\ServerRequestInterface');
        $request->getUri()->willReturn($uri);
        $request->getServerParams()->willReturn([
            'REQUEST_METHOD' => 'PUT',
        ]);


        $this->auraRouter->match('/bar', ['REQUEST_METHOD' => 'PUT'])->willReturn(false);
        $this->auraRouter->getFailedRoute()->willReturn(new TestAsset\AuraRoute);

        $router = $this->getRouter();
        $result = $router->match($request->reveal());
        $this->assertInstanceOf('Zend\Expressive\Router\RouteResult', $result);
        $this->assertTrue($result->isFailure());
        $this->assertFalse($result->isMethodFailure());
        $this->assertSame([], $result->getAllowedMethods());
    }
}
