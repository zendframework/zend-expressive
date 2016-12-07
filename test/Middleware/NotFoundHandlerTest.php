<?php
/**
 * @link      http://github.com/zendframework/zend-expressive for the canonical source repository
 * @copyright Copyright (c) 2016 Zend Technologies USA Inc. (http://www.zend.com)
 * @license   http://framework.zend.com/license/new-bsd New BSD License
 */

namespace ZendTest\Expressive\Middleware;

use Fig\Http\Message\StatusCodeInterface;
use Interop\Http\Middleware\DelegateInterface;
use PHPUnit_Framework_TestCase as TestCase;
use Prophecy\Argument;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\StreamInterface;
use Zend\Expressive\Middleware\NotFoundHandler;
use Zend\Expressive\Template\TemplateRendererInterface;

class NotFoundHandlerTest extends TestCase
{
    public function setUp()
    {
        $this->request  = $this->prophesize(ServerRequestInterface::class);
        $this->response = $this->prophesize(ResponseInterface::class);
        $this->stream   = $this->prophesize(StreamInterface::class);
        $this->renderer = $this->prophesize(TemplateRendererInterface::class);

        $this->delegate = $this->prophesize(DelegateInterface::class);
        $this->delegate->process(Argument::type(ServerRequestInterface::class))->shouldNotBeCalled();
    }

    public function testWritesDefaultMessageToResponseIfNoTemplateRendererFound()
    {
        $this->request
            ->getUri()
            ->willReturn('https://example.com/foo');
        $this->request
            ->getMethod()
            ->willReturn('POST');

        $this->renderer->render(Argument::any(), Argument::any())->shouldNotBeCalled();

        $this->response->withStatus(StatusCodeInterface::STATUS_NOT_FOUND)->will([$this->response, 'reveal']);
        $this->response->getBody()->will([$this->stream, 'reveal']);

        $this->stream->write('Cannot POST https://example.com/foo')->shouldBeCalled();

        $middleware = new NotFoundHandler($this->response->reveal());

        $response = $middleware->process($this->request->reveal(), $this->delegate->reveal());

        $this->assertSame($this->response->reveal(), $response);
    }

    public function templates()
    {
        return [
            'default' => [null, 'error::404'],
            'custom' => ['error::custom404', 'error::custom404'],
        ];
    }

    /**
     * @dataProvider templates
     */
    public function testWritesRenderedTemplateToResponseIfTemplateRendererFound($template, $expected)
    {
        $this->renderer
            ->render($expected, [
                'request' => $this->request->reveal(),
            ])
            ->willReturn('TEMPLATED RESPONSE');

        $this->response->withStatus(StatusCodeInterface::STATUS_NOT_FOUND)->will([$this->response, 'reveal']);
        $this->response->getBody()->will([$this->stream, 'reveal']);

        $this->stream->write('TEMPLATED RESPONSE')->shouldBeCalled();

        $middleware = $template
            ? new NotFoundHandler($this->response->reveal(), $this->renderer->reveal(), $template)
            : new NotFoundHandler($this->response->reveal(), $this->renderer->reveal());

        $response = $middleware->process($this->request->reveal(), $this->delegate->reveal());

        $this->assertSame($this->response->reveal(), $response);
    }
}
