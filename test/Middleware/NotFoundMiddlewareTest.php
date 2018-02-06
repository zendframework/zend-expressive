<?php
/**
 * @see       https://github.com/zendframework/zend-expressive for the canonical source repository
 * @copyright Copyright (c) 2016-2017 Zend Technologies USA Inc. (https://www.zend.com)
 * @license   https://github.com/zendframework/zend-expressive/blob/master/LICENSE.md New BSD License
 */

declare(strict_types=1);

namespace ZendTest\Expressive\Middleware;

use Fig\Http\Message\RequestMethodInterface as RequestMethod;
use Fig\Http\Message\StatusCodeInterface as StatusCode;
use PHPUnit\Framework\TestCase;
use Prophecy\Argument;
use Prophecy\Prophecy\ObjectProphecy;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\StreamInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Zend\Expressive\Middleware\NotFoundMiddleware;
use Zend\Expressive\Template\TemplateRendererInterface;

class NotFoundMiddlewareTest extends TestCase
{
    /** @var RequestHandlerInterface|ObjectProphecy */
    private $handler;

    /** @var ServerRequestInterface|ObjectProphecy */
    private $request;

    /** @var ResponseInterface|ObjectProphecy */
    private $response;

    public function setUp()
    {
        $this->request  = $this->prophesize(ServerRequestInterface::class);
        $this->response = $this->prophesize(ResponseInterface::class);

        $this->handler = $this->prophesize(RequestHandlerInterface::class);
        $this->handler->handle(Argument::type(ServerRequestInterface::class))->shouldNotBeCalled();
    }

    public function testImplementsInteropMiddleware()
    {
        $handler = new NotFoundMiddleware($this->response->reveal());
        $this->assertInstanceOf(MiddlewareInterface::class, $handler);
    }

    public function testConstructorDoesNotRequireARenderer()
    {
        $middleware = new NotFoundMiddleware($this->response->reveal());
        $this->assertInstanceOf(NotFoundMiddleware::class, $middleware);
        $this->assertAttributeSame($this->response->reveal(), 'responsePrototype', $middleware);
    }

    public function testConstructorCanAcceptRendererAndTemplate()
    {
        $renderer = $this->prophesize(TemplateRendererInterface::class)->reveal();
        $template = 'foo::bar';
        $layout = 'layout::error';

        $middleware = new NotFoundMiddleware($this->response->reveal(), $renderer, $template, $layout);

        $this->assertInstanceOf(NotFoundMiddleware::class, $middleware);
        $this->assertAttributeSame($renderer, 'renderer', $middleware);
        $this->assertAttributeEquals($template, 'template', $middleware);
        $this->assertAttributeEquals($layout, 'layout', $middleware);
    }

    public function testRendersDefault404ResponseWhenNoRendererPresent()
    {
        $request = $this->prophesize(ServerRequestInterface::class);
        $request->getMethod()->willReturn(RequestMethod::METHOD_POST);
        $request->getUri()->willReturn('https://example.com/foo/bar');

        $stream = $this->prophesize(StreamInterface::class);
        $stream->write('Cannot POST https://example.com/foo/bar')->shouldBeCalled();
        $this->response->withStatus(StatusCode::STATUS_NOT_FOUND)->will([$this->response, 'reveal']);
        $this->response->getBody()->will([$stream, 'reveal']);

        $middleware = new NotFoundMiddleware($this->response->reveal());

        $response = $middleware->process($request->reveal(), $this->handler->reveal());

        $this->assertSame($this->response->reveal(), $response);
    }

    public function testUsesRendererToGenerateResponseContentsWhenPresent()
    {
        $request = $this->prophesize(ServerRequestInterface::class)->reveal();

        $renderer = $this->prophesize(TemplateRendererInterface::class);
        $renderer
            ->render(
                NotFoundMiddleware::TEMPLATE_DEFAULT,
                [
                    'request' => $request,
                    'layout' => NotFoundMiddleware::LAYOUT_DEFAULT,
                ]
            )
            ->willReturn('CONTENT');

        $stream = $this->prophesize(StreamInterface::class);
        $stream->write('CONTENT')->shouldBeCalled();

        $this->response->withStatus(StatusCode::STATUS_NOT_FOUND)->will([$this->response, 'reveal']);
        $this->response->getBody()->will([$stream, 'reveal']);

        $middleware = new NotFoundMiddleware($this->response->reveal(), $renderer->reveal());

        $response = $middleware->process($request, $this->handler->reveal());

        $this->assertSame($this->response->reveal(), $response);
    }
}
