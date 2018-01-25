<?php
/**
 * @see       https://github.com/zendframework/zend-expressive for the canonical source repository
 * @copyright Copyright (c) 2016-2017 Zend Technologies USA Inc. (https://www.zend.com)
 * @license   https://github.com/zendframework/zend-expressive/blob/master/LICENSE.md New BSD License
 */

declare(strict_types=1);

namespace ZendTest\Expressive\Middleware;

use PHPUnit\Framework\TestCase;
use Prophecy\Argument;
use Prophecy\Prophecy\ObjectProphecy;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Zend\Expressive\Handler\NotFoundHandler;
use Zend\Expressive\Middleware\NotFoundMiddleware;

class NotFoundMiddlewareTest extends TestCase
{
    /** @var NotFoundHandler|ObjectProphecy */
    private $internal;

    /** @var ServerRequestInterface|ObjectProphecy */
    private $request;

    /** @var RequestHandlerInterface|ObjectProphecy */
    private $handler;

    public function setUp()
    {
        $this->internal = $this->prophesize(NotFoundHandler::class);
        $this->request  = $this->prophesize(ServerRequestInterface::class);

        $this->handler = $this->prophesize(RequestHandlerInterface::class);
        $this->handler->handle(Argument::type(ServerRequestInterface::class))->shouldNotBeCalled();
    }

    public function testImplementsInteropMiddleware()
    {
        $handler = new NotFoundMiddleware($this->internal->reveal());
        $this->assertInstanceOf(MiddlewareInterface::class, $handler);
    }

    public function testProxiesToInternalHandler()
    {
        $response = $this->prophesize(ResponseInterface::class)->reveal();

        $this->internal
            ->handle(Argument::that([$this->request, 'reveal']))
            ->willReturn($response);

        $handler = new NotFoundMiddleware($this->internal->reveal());
        $this->assertEquals($response, $handler->process($this->request->reveal(), $this->handler->reveal()));
    }
}
