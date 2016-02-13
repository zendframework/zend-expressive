<?php
/**
 * Zend Framework (http://framework.zend.com/)
 *
 * @see       https://github.com/zendframework/zend-expressive for the canonical source repository
 * @copyright Copyright (c) 2015-2016 Zend Technologies USA Inc. (http://www.zend.com)
 * @license   https://github.com/zendframework/zend-expressive/blob/master/LICENSE.md New BSD License
 */

namespace ZendTest\Expressive;

use PHPUnit_Framework_TestCase as TestCase;
use Prophecy\Argument;
use Psr\Http\Message\ResponseInterface;
use Zend\Diactoros\Response;
use Zend\Diactoros\Response\EmitterInterface;
use Zend\Diactoros\ServerRequest;
use Zend\Expressive\Application;
use Zend\Expressive\Router\FastRouteRouter;
use Zend\Expressive\TemplatedErrorHandler;

class IntegrationTest extends TestCase
{
    public $response;

    protected function setUp()
    {
        $this->response = null;
    }

    public function getEmitter()
    {
        $self    = $this;
        $emitter = $this->prophesize(EmitterInterface::class);
        $emitter
            ->emit(Argument::type(ResponseInterface::class))
            ->will(function ($args) use ($self) {
                $response = array_shift($args);
                $self->response = $response;
                return null;
            })
            ->shouldBeCalled();
        return $emitter->reveal();
    }

    public function testDefaultFinalHandlerCanEmitA404WhenNoMiddlewareMatches()
    {
        $app      = new Application(new FastRouteRouter(), null, null, $this->getEmitter());
        $request  = new ServerRequest([], [], 'https://example.com/foo', 'GET');
        $response = new Response();

        $app->run($request, $response);
        $this->assertInstanceOf(ResponseInterface::class, $this->response);
        $this->assertEquals(404, $this->response->getStatusCode());
    }

    public function testInjectedFinalHandlerCanEmitA404WhenNoMiddlewareMatches()
    {
        $finalHandler = new TemplatedErrorHandler();
        $app          = new Application(new FastRouteRouter(), null, $finalHandler, $this->getEmitter());
        $request      = new ServerRequest([], [], 'https://example.com/foo', 'GET');
        $response     = new Response();

        $app->run($request, $response);
        $this->assertInstanceOf(ResponseInterface::class, $this->response);
        $this->assertEquals(404, $this->response->getStatusCode());
    }
}
