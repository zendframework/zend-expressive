<?php
/**
 * Zend Framework (http://framework.zend.com/)
 *
 * @see       http://github.com/zendframework/zend-diactoros for the canonical source repository
 * @copyright Copyright (c) 2015 Zend Technologies USA Inc. (http://www.zend.com)
 * @license   https://github.com/zendframework/zend-diactoros/blob/master/LICENSE.md New BSD License
 */

namespace ZendTest\Expressive\Router;

use PHPUnit_Framework_TestCase as TestCase;
use Prophecy\Argument;
use ReflectionProperty;
use Zend\Expressive\Router\Zf2 as Zf2Router;
use Zend\Expressive\Router\Route;

class Zf2Test extends TestCase
{
    public function setUp()
    {
        $this->zf2Router = $this->prophesize('Zend\Mvc\Router\Http\TreeRouteStack');
    }

    public function getRouter()
    {
        return new Zf2Router($this->zf2Router->reveal());
    }

    public function testGetRouterPropertyWithNullInConstruct()
    {
        $router = new Zf2Router();
        $r = new ReflectionProperty($router, 'zf2Router');
        $r->setAccessible(true);

        $this->assertInstanceOf('Zend\Mvc\Router\Http\TreeRouteStack', $r->getValue($router));
    }

    public function createRequestProphecy()
    {
        $request = $this->prophesize('Psr\Http\Message\ServerRequestInterface');

        $request->getMethod()->willReturn('GET');
        $request->getUri()->willReturn('https://example.com/foo');
        $request->getHeaders()->willReturn([]);
        $request->getCookieParams()->willReturn([]);
        $request->getQueryParams()->willReturn([]);
        $request->getServerParams()->willReturn([]);

        return $request;
    }

    public function testAddingRouteProxiesToZf2Router()
    {
        $route = new Route('/foo', 'foo', ['GET']);

        $this->zf2Router->addRoute('/foo', [
            'type' => 'segment',
            'options' => [
                'route' => '/foo',
                'defaults' => [
                    'middleware' => 'foo',
                ],
            ],
        ])->shouldBeCalled();

        $router = $this->getRouter();
        $router->addRoute($route);
    }

    public function testCanSpecifyRouteOptions()
    {
        $route = new Route('/foo/:id', 'foo', ['GET']);
        $route->setOptions([
            'constraints' => [
                'id' => '\d+',
            ],
            'defaults' => [
                'bar' => 'baz',
            ],
        ]);

        $this->zf2Router->addRoute('/foo/:id', [
            'type' => 'segment',
            'options' => [
                'route' => '/foo/:id',
                'constraints' => [
                    'id' => '\d+',
                ],
                'defaults' => [
                    'bar' => 'baz',
                    'middleware' => 'foo',
                ],
            ],
        ])->shouldBeCalled();

        $router = $this->getRouter();
        $router->addRoute($route);
    }

    public function routeResults()
    {
        $successRoute = new Route('/foo', 'bar');
        return [
            'success' => [
                new Route('/foo', 'bar'),
                RouteResult::fromRouteMatch('/foo', 'bar'),
            ],
            'failure' => [
                new Route('/foo', 'bar'),
                RouteResult::fromRouteFailure(),
            ],
        ];
    }

    /**
     * @group match
     */
    public function testSuccessfulMatchIsPossible()
    {
        $routeMatch = $this->prophesize('Zend\Mvc\Router\RouteMatch');
        $routeMatch->getMatchedRouteName()->willReturn('/foo');
        $routeMatch->getParams()->willReturn([
            'middleware' => 'bar',
        ]);

        $this->zf2Router
            ->match(Argument::type('Zend\Http\PhpEnvironment\Request'))
            ->willReturn($routeMatch->reveal());

        $request = $this->createRequestProphecy();

        $router = $this->getRouter();
        $result = $router->match($request->reveal());
        $this->assertInstanceOf('Zend\Expressive\Router\RouteResult', $result);
        $this->assertTrue($result->isSuccess());
        $this->assertEquals('/foo', $result->getMatchedRouteName());
        $this->assertEquals('bar', $result->getMatchedMiddleware());
    }

    /**
     * @group match
     */
    public function testNonSuccessfulMatchNotDueToHttpMethodsIsPossible()
    {
        $this->zf2Router
            ->match(Argument::type('Zend\Http\PhpEnvironment\Request'))
            ->willReturn(null);

        $request = $this->createRequestProphecy();

        $router = $this->getRouter();
        $result = $router->match($request->reveal());
        $this->assertInstanceOf('Zend\Expressive\Router\RouteResult', $result);
        $this->assertTrue($result->isFailure());
        $this->assertFalse($result->isMethodFailure());
    }

    /**
     * @group match
     */
    public function testMatchFailureDueToHttpMethodReturnsRouteResultWithAllowedMethods()
    {
        $routeMatch = $this->prophesize('Zend\Mvc\Router\RouteMatch');
        $routeMatch->getMatchedRouteName()->willReturn('/foo');
        $routeMatch->getParams()->willReturn([
            'middleware' => 'bar',
        ]);

        $this->zf2Router->addRoute('/foo', [
            'type' => 'segment',
            'options' => [
                'route' => '/foo',
                'defaults' => [
                    'middleware' => 'bar',
                ],
            ],
        ])->shouldBeCalled();
        $this->zf2Router
            ->match(Argument::type('Zend\Http\PhpEnvironment\Request'))
            ->willReturn($routeMatch->reveal());


        $router = $this->getRouter();
        $router->addRoute(new Route('/foo', 'bar', ['POST', 'DELETE']));

        $request = $this->createRequestProphecy();
        $result = $router->match($request->reveal());

        $this->assertInstanceOf('Zend\Expressive\Router\RouteResult', $result);
        $this->assertTrue($result->isFailure());
        $this->assertTrue($result->isMethodFailure());
        $this->assertEquals(['POST', 'DELETE'], $result->getAllowedMethods());
    }
}
