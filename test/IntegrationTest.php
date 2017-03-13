<?php
/**
 * @see       https://github.com/zendframework/zend-expressive for the canonical source repository
 * @copyright Copyright (c) 2015-2017 Zend Technologies USA Inc. (http://www.zend.com)
 * @license   https://github.com/zendframework/zend-expressive/blob/master/LICENSE.md New BSD License
 */

namespace ZendTest\Expressive;

use Fig\Http\Message\StatusCodeInterface as StatusCode;
use Interop\Http\ServerMiddleware\DelegateInterface;
use Interop\Http\ServerMiddleware\MiddlewareInterface;
use PHPUnit\Framework\TestCase;
use Prophecy\Argument;
use Psr\Container\ContainerInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\ResponseInterface;
use Zend\Diactoros\Response;
use Zend\Diactoros\Response\EmitterInterface;
use Zend\Diactoros\ServerRequest;
use Zend\Expressive\Application;
use Zend\Expressive\Delegate\NotFoundDelegate;
use Zend\Expressive\Router\FastRouteRouter;

class IntegrationTest extends TestCase
{
    public $errorHandler;
    public $response;

    public function setUp()
    {
        $this->response = null;
        $this->errorHandler = null;
    }

    public function tearDown()
    {
        if ($this->errorHandler) {
            set_error_handler($this->errorHandler);
            $this->errorHandler = null;
        }
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
        $this->assertEquals(StatusCode::STATUS_NOT_FOUND, $this->response->getStatusCode());
    }

    public function testInjectedFinalHandlerCanEmitA404WhenNoMiddlewareMatches()
    {
        $request  = new ServerRequest([], [], 'https://example.com/foo', 'GET');
        $response = new Response();
        $delegate = new NotFoundDelegate($response);
        $app      = new Application(new FastRouteRouter(), null, $delegate, $this->getEmitter());

        $app->run($request, $response);

        $this->assertInstanceOf(ResponseInterface::class, $this->response);
        $this->assertEquals(StatusCode::STATUS_NOT_FOUND, $this->response->getStatusCode());
    }

    public function testCallableClassInteropMiddlewareNotRegisteredWithContainerCanBeComposedSuccessfully()
    {
        $response = new Response();
        $routedMiddleware = $this->prophesize(MiddlewareInterface::class);
        $routedMiddleware
            ->process(
                Argument::type(ServerRequestInterface::class),
                Argument::type(DelegateInterface::class)
            )
            ->willReturn($response);

        $container = $this->prophesize(ContainerInterface::class);
        $container->has('RoutedMiddleware')->willReturn(true);
        $container->get('RoutedMiddleware')->will([$routedMiddleware, 'reveal']);
        $container->has(TestAsset\CallableInteropMiddleware::class)->willReturn(false);

        $delegate = new NotFoundDelegate($response);
        $app      = new Application(new FastRouteRouter(), $container->reveal(), $delegate, $this->getEmitter());

        $app->pipe(TestAsset\CallableInteropMiddleware::class);
        $app->get('/', 'RoutedMiddleware');

        $request  = new ServerRequest([], [], 'https://example.com/foo', 'GET');
        $app->run($request, new Response());

        $this->assertInstanceOf(ResponseInterface::class, $this->response);
        $this->assertTrue($this->response->hasHeader('X-Callable-Interop-Middleware'));
        $this->assertEquals(
            TestAsset\CallableInteropMiddleware::class,
            $this->response->getHeaderLine('X-Callable-Interop-Middleware')
        );
    }
}
