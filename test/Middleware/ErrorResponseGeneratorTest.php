<?php
/**
 * @link      http://github.com/zendframework/zend-expressive for the canonical source repository
 * @copyright Copyright (c) 2016-2016 Zend Technologies USA Inc. (http://www.zend.com)
 * @license   http://framework.zend.com/license/new-bsd New BSD License
 */

namespace ZendTest\Expressive\Middleware;

use PHPUnit_Framework_TestCase as TestCase;
use Prophecy\Argument;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\StreamInterface;
use RuntimeException;
use Zend\Expressive\Middleware\ErrorResponseGenerator;
use Zend\Expressive\Template\TemplateRendererInterface;

class ErrorResponseGeneratorTest extends TestCase
{
    public function setUp()
    {
        $this->request  = $this->prophesize(ServerRequestInterface::class);
        $this->response = $this->prophesize(ResponseInterface::class);
        $this->stream   = $this->prophesize(StreamInterface::class);
        $this->renderer = $this->prophesize(TemplateRendererInterface::class);
    }

    public function testWritesGenericMessageToResponseWhenNoRendererPresentAndNotInDebugMode()
    {
        $error = new RuntimeException('', 0);

        $initialResponse   = clone $this->response;
        $secondaryResponse = clone $this->response;

        $secondaryResponse->getBody()->will([$this->stream, 'reveal']);

        $initialResponse
            ->getStatusCode()
            ->willReturn(200);
        $initialResponse
            ->withStatus(500)
            ->will(function () use ($secondaryResponse) {
                $secondaryResponse->getStatusCode()->willReturn(500);
                $secondaryResponse->getReasonPhrase()->willReturn('Network Connect Timeout Error');
                return $secondaryResponse->reveal();
            });

        $this->stream->write('An unexpected error occurred')->shouldBeCalled();

        $generator = new ErrorResponseGenerator();
        $response = $generator($error, $this->request->reveal(), $initialResponse->reveal());

        $this->assertSame($response, $secondaryResponse->reveal());
    }

    public function testWritesStackTraceToResponseWhenNoRendererPresentInDebugMode()
    {
        $leaf   = new RuntimeException('leaf', 415);
        $branch = new RuntimeException('branch', 0, $leaf);
        $error  = new RuntimeException('root', 599, $branch);

        $initialResponse   = clone $this->response;
        $secondaryResponse = clone $this->response;

        $secondaryResponse->getBody()->will([$this->stream, 'reveal']);

        $initialResponse
            ->getStatusCode()
            ->willReturn(200);
        $initialResponse
            ->withStatus(599)
            ->will(function () use ($secondaryResponse) {
                $secondaryResponse->getStatusCode()->willReturn(599);
                $secondaryResponse->getReasonPhrase()->willReturn('Network Connect Timeout Error');
                return $secondaryResponse->reveal();
            });

        $this->stream
            ->write(Argument::that(function ($body) use ($leaf, $branch, $error) {
                $this->assertContains($leaf->getTraceAsString(), $body);
                $this->assertContains($branch->getTraceAsString(), $body);
                $this->assertContains($error->getTraceAsString(), $body);
                return true;
            }))
            ->shouldBeCalled();

        $generator = new ErrorResponseGenerator($debug = true);
        $response = $generator($error, $this->request->reveal(), $initialResponse->reveal());

        $this->assertSame($response, $secondaryResponse->reveal());
    }

    public function testTemplates()
    {
        return [
            'default' => [null, 'error::error'],
            'custom' => ['error::custom', 'error::custom'],
        ];
    }

    /**
     * @dataProvider testTemplates
     */
    public function testRendersTemplateWithoutErrorDetailsWhenRendererPresentAndNotInDebugMode($template, $expected)
    {
        $error = new RuntimeException('', 0);

        $initialResponse   = clone $this->response;
        $secondaryResponse = clone $this->response;

        $this->renderer
            ->render($expected, [
                'response' => $secondaryResponse->reveal(),
                'request'  => $this->request->reveal(),
                'uri'      => 'https://example.com/foo',
                'status'   => 500,
                'reason'   => 'Internal Server Error',
            ])
            ->willReturn('TEMPLATED CONTENTS');

        $secondaryResponse->getBody()->will([$this->stream, 'reveal']);

        $initialResponse
            ->getStatusCode()
            ->willReturn(200);
        $initialResponse
            ->withStatus(500)
            ->will(function () use ($secondaryResponse) {
                $secondaryResponse->getStatusCode()->willReturn(500);
                $secondaryResponse->getReasonPhrase()->willReturn('Internal Server Error');
                return $secondaryResponse->reveal();
            });

        $this->stream->write('TEMPLATED CONTENTS')->shouldBeCalled();

        $this->request->getUri()->willReturn('https://example.com/foo');

        $generator = $template
            ? new ErrorResponseGenerator(false, $this->renderer->reveal(), $template)
            : new ErrorResponseGenerator(false, $this->renderer->reveal());

        $response = $generator($error, $this->request->reveal(), $initialResponse->reveal());

        $this->assertSame($response, $secondaryResponse->reveal());
    }

    /**
     * @dataProvider testTemplates
     */
    public function testRendersTemplateWithErrorDetailsWhenRendererPresentAndInDebugMode($template, $expected)
    {
        $error = new RuntimeException('', 0);

        $initialResponse   = clone $this->response;
        $secondaryResponse = clone $this->response;

        $secondaryResponse->getBody()->will([$this->stream, 'reveal']);

        $initialResponse
            ->getStatusCode()
            ->willReturn(200);
        $initialResponse
            ->withStatus(500)
            ->will(function () use ($secondaryResponse) {
                $secondaryResponse->getStatusCode()->willReturn(500);
                $secondaryResponse->getReasonPhrase()->willReturn('Network Connect Timeout Error');
                return $secondaryResponse->reveal();
            });

        $this->renderer
            ->render($expected, [
                'response' => $secondaryResponse->reveal(),
                'request'  => $this->request->reveal(),
                'uri'      => 'https://example.com/foo',
                'status'   => 500,
                'reason'   => 'Network Connect Timeout Error',
                'error'    => $error,
            ])
            ->willReturn('TEMPLATED CONTENTS');

        $this->stream->write('TEMPLATED CONTENTS')->shouldBeCalled();

        $this->request->getUri()->willReturn('https://example.com/foo');

        $generator = $template
            ? new ErrorResponseGenerator(true, $this->renderer->reveal(), $template)
            : new ErrorResponseGenerator(true, $this->renderer->reveal());

        $response = $generator($error, $this->request->reveal(), $initialResponse->reveal());

        $this->assertSame($response, $secondaryResponse->reveal());
    }
}