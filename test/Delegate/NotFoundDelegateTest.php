<?php
/**
 * @see       https://github.com/zendframework/zend-expressive for the canonical source repository
 * @copyright Copyright (c) 2017 Zend Technologies USA Inc. (http://www.zend.com)
 * @license   https://github.com/zendframework/zend-expressive/blob/master/LICENSE.md New BSD License
 */

namespace Zend\Expressive\Delegate;

use Fig\Http\Message\RequestMethodInterface as RequestMethod;
use Fig\Http\Message\StatusCodeInterface as StatusCode;
use PHPUnit\Framework\TestCase;
use Prophecy\Prophecy\ObjectProphecy;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\StreamInterface;
use Zend\Expressive\Template\TemplateRendererInterface;

class NotFoundDelegateTest extends TestCase
{
    /** @var ResponseInterface|ObjectProphecy */
    private $response;

    protected function setUp()
    {
        $this->response = $this->prophesize(ResponseInterface::class);
    }

    public function testConstructorDoesNotRequireARenderer()
    {
        $delegate = new NotFoundDelegate($this->response->reveal());
        $this->assertInstanceOf(NotFoundDelegate::class, $delegate);
        $this->assertAttributeSame($this->response->reveal(), 'responsePrototype', $delegate);
    }

    public function testConstructorCanAcceptRendererAndTemplate()
    {
        $renderer = $this->prophesize(TemplateRendererInterface::class)->reveal();
        $template = 'foo::bar';

        $delegate = new NotFoundDelegate($this->response->reveal(), $renderer, $template);

        $this->assertInstanceOf(NotFoundDelegate::class, $delegate);
        $this->assertAttributeSame($renderer, 'renderer', $delegate);
        $this->assertAttributeEquals($template, 'template', $delegate);
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

        $delegate = new NotFoundDelegate($this->response->reveal());

        $response = $delegate->process($request->reveal());

        $this->assertSame($this->response->reveal(), $response);
    }

    public function testUsesRendererToGenerateResponseContentsWhenPresent()
    {
        $request = $this->prophesize(ServerRequestInterface::class)->reveal();

        $renderer = $this->prophesize(TemplateRendererInterface::class);
        $renderer->render(NotFoundDelegate::TEMPLATE_DEFAULT, ['request' => $request])
            ->willReturn('CONTENT');

        $stream = $this->prophesize(StreamInterface::class);
        $stream->write('CONTENT')->shouldBeCalled();

        $this->response->withStatus(StatusCode::STATUS_NOT_FOUND)->will([$this->response, 'reveal']);
        $this->response->getBody()->will([$stream, 'reveal']);

        $delegate = new NotFoundDelegate($this->response->reveal(), $renderer->reveal());

        $response = $delegate->process($request);

        $this->assertSame($this->response->reveal(), $response);
    }
}
