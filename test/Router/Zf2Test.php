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
use Zend\Expressive\Router\Zf2 as Zf2Router;
use Zend\Expressive\Router\Route;
use Zend\Diactoros\ServerRequest;

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

    public function testWillLazyInstantiateAZf2TreeRouteStackIfNoneIsProvidedToConstructor()
    {
        $router = new Zf2Router();
        $this->assertAttributeInstanceOf('Zend\Mvc\Router\Http\TreeRouteStack', 'zf2Router', $router);
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
            ],
            'may_terminate' => false,
            'child_routes' => [
                'GET' => [
                    'type' => 'method',
                    'options' => [
                        'verb' => 'GET',
                        'defaults' => [
                            'middleware' => 'foo',
                        ]
                    ]
                ],
                'fail' => [
                    'type' => 'segment',
                    'priority' => -1,
                    'options' => [
                        'route' => '[/]',
                        'defaults' => [
                            'middleware' => null
                        ]
                    ]
                ]
            ]
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
                    'bar' => 'baz'
                ],
            ],
            'may_terminate' => false,
            'child_routes' => [
                'GET' => [
                    'type' => 'method',
                    'options' => [
                        'verb' => 'GET',
                        'defaults' => [
                            'middleware' => 'foo',
                        ]
                    ]
                ],
                'fail' => [
                    'type' => 'segment',
                    'priority' => -1,
                    'options' => [
                        'route' => '[/]',
                        'defaults' => [
                            'middleware' => null
                        ]
                    ]
                ]
            ]
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

    public function testMatch()
    {
        $middleware = function ($req, $res, $next) {
            return $res;
        };

        $route = new Route('/foo', $middleware, ['GET']);
        $zf2Router = new Zf2Router();
        $zf2Router->addRoute($route);

        $request = new ServerRequest([ 'REQUEST_METHOD' => 'GET' ], [], '/foo', 'GET');

        $result = $zf2Router->match($request);
        $this->assertInstanceOf('Zend\Expressive\Router\RouteResult', $result);
        $this->assertEquals('/foo/GET', $result->getMatchedRouteName());
        $this->assertEquals($middleware, $result->getMatchedMiddleware());
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

        $router = new Zf2Router();
        $router->addRoute(new Route('/foo', 'bar', ['POST', 'DELETE']));
        $request = new ServerRequest([ 'REQUEST_METHOD' => 'GET' ], [], '/foo', 'GET');
        $result = $router->match($request);

        $this->assertInstanceOf('Zend\Expressive\Router\RouteResult', $result);
        $this->assertTrue($result->isFailure());
        $this->assertTrue($result->isMethodFailure());
        $this->assertEquals(['POST', 'DELETE'], $result->getAllowedMethods());
    }
}
