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

        $this->response->getBody()->will([$this->stream, 'reveal']);
        $this->response
            ->withStatus(500)
            ->will([$this->response, 'reveal']);

        $this->stream->write('An unexpected error occurred')->shouldBeCalled();

        $generator = new ErrorResponseGenerator();
        $response = $generator($error, $this->request->reveal(), $this->response->reveal());

        $this->assertSame($response, $this->response->reveal());
    }

    public function testWritesStackTraceToResponseWhenNoRendererPresentInDebugMode()
    {
        $leaf   = new RuntimeException('leaf', 415);
        $branch = new RuntimeException('branch', 0, $leaf);
        $error  = new RuntimeException('root', 599, $branch);

        $this->response->getBody()->will([$this->stream, 'reveal']);
        $this->response
            ->withStatus(599)
            ->will([$this->response, 'reveal']);

        $this->stream
            ->write(Argument::that(function ($body) use ($leaf, $branch, $error) {
                $this->assertContains($leaf->getTraceAsString(), $body);
                $this->assertContains($branch->getTraceAsString(), $body);
                $this->assertContains($error->getTraceAsString(), $body);
                return true;
            }))
            ->shouldBeCalled();

        $generator = new ErrorResponseGenerator($debug = true);
        $response = $generator($error, $this->request->reveal(), $this->response->reveal());

        $this->assertSame($response, $this->response->reveal());
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

        $this->renderer
            ->render($expected, [])
            ->willReturn('TEMPLATED CONTENTS');

        $this->response->getBody()->will([$this->stream, 'reveal']);
        $this->response
            ->withStatus(500)
            ->will([$this->response, 'reveal']);

        $this->stream->write('TEMPLATED CONTENTS')->shouldBeCalled();

        $generator = $template
            ? new ErrorResponseGenerator(false, $this->renderer->reveal(), $template)
            : new ErrorResponseGenerator(false, $this->renderer->reveal());

        $response = $generator($error, $this->request->reveal(), $this->response->reveal());

        $this->assertSame($response, $this->response->reveal());
    }

    /**
     * @dataProvider testTemplates
     */
    public function testRendersTemplateWithErrorDetailsWhenRendererPresentAndInDebugMode($template, $expected)
    {
        $error = new RuntimeException('', 0);

        $this->renderer
            ->render($expected, ['error' => $error])
            ->willReturn('TEMPLATED CONTENTS');

        $this->response->getBody()->will([$this->stream, 'reveal']);
        $this->response
            ->withStatus(500)
            ->will([$this->response, 'reveal']);

        $this->stream->write('TEMPLATED CONTENTS')->shouldBeCalled();

        $generator = $template
            ? new ErrorResponseGenerator(true, $this->renderer->reveal(), $template)
            : new ErrorResponseGenerator(true, $this->renderer->reveal());

        $response = $generator($error, $this->request->reveal(), $this->response->reveal());

        $this->assertSame($response, $this->response->reveal());
    }
}
