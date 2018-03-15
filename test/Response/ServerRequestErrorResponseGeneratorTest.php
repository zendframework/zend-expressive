<?php
/**
 * @see       https://github.com/zendframework/zend-expressive for the canonical source repository
 * @copyright Copyright (c) 2018 Zend Technologies USA Inc. (https://www.zend.com)
 * @license   https://github.com/zendframework/zend-expressive/blob/master/LICENSE.md New BSD License
 */

declare(strict_types=1);

namespace ZendTest\Expressive\Response;

use PHPUnit\Framework\TestCase;
use Prophecy\Argument;
use Prophecy\Prophecy\ObjectProphecy;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\StreamInterface;
use RuntimeException;
use Zend\Expressive\Response\ServerRequestErrorResponseGenerator;
use Zend\Expressive\Template\TemplateRendererInterface;

use function preg_match;
use function strpos;

class ServerRequestErrorResponseGeneratorTest extends TestCase
{
    /** @var TemplateRendererInterface|ObjectProphecy */
    private $renderer;

    /** @var ResponseInterface|ObjectProphecy */
    private $response;

    /** @var callable */
    private $responseFactory;

    public function setUp()
    {
        $this->response = $this->prophesize(ResponseInterface::class);
        $this->responseFactory = function () {
            return $this->response->reveal();
        };

        $this->renderer = $this->prophesize(TemplateRendererInterface::class);
    }

    public function testPreparesTemplatedResponseWhenRendererPresent()
    {
        $stream = $this->prophesize(StreamInterface::class);
        $stream->write('data from template')->shouldBeCalled();

        $this->response->withStatus(422)->will([$this->response, 'reveal']);
        $this->response->getBody()->will([$stream, 'reveal']);
        $this->response->getStatusCode()->willReturn(422);
        $this->response->getReasonPhrase()->willReturn('Unexpected entity');

        $template = 'some::template';
        $e = new RuntimeException('This is the exception message', 422);
        $this->renderer
            ->render($template, [
                'response' => $this->response->reveal(),
                'status'   => 422,
                'reason'   => 'Unexpected entity',
                'error'    => $e,
            ])
            ->willReturn('data from template');

        $generator = new ServerRequestErrorResponseGenerator(
            $this->responseFactory,
            true,
            $this->renderer->reveal(),
            $template
        );

        $this->assertSame($this->response->reveal(), $generator($e));
    }

    public function testPreparesResponseWithDefaultMessageOnlyWhenNoRendererPresentAndNotInDebugMode()
    {
        $stream = $this->prophesize(StreamInterface::class);
        $stream->write('An unexpected error occurred')->shouldBeCalled();

        $this->response->withStatus(422)->will([$this->response, 'reveal']);
        $this->response->getBody()->will([$stream, 'reveal']);
        $this->response->getStatusCode()->shouldNotBeCalled();
        $this->response->getReasonPhrase()->shouldNotBeCalled();

        $e = new RuntimeException('This is the exception message', 422);

        $generator = new ServerRequestErrorResponseGenerator($this->responseFactory);

        $this->assertSame($this->response->reveal(), $generator($e));
    }

    public function testPreparesResponseWithDefaultMessageAndStackTraceWhenNoRendererPresentAndInDebugMode()
    {
        $stream = $this->prophesize(StreamInterface::class);
        $stream
            ->write(Argument::that(function ($message) {
                if (! preg_match('/^An unexpected error occurred; stack trace:/', $message)) {
                    echo "Failed first assertion: $message\n";
                    return false;
                }
                if (false === strpos($message, 'Stack Trace:')) {
                    echo "Failed second assertion: $message\n";
                    return false;
                }
                return $message;
            }))
            ->shouldBeCalled();

        $this->response->withStatus(422)->will([$this->response, 'reveal']);
        $this->response->getBody()->will([$stream, 'reveal']);
        $this->response->getStatusCode()->shouldNotBeCalled();
        $this->response->getReasonPhrase()->shouldNotBeCalled();

        $e = new RuntimeException('This is the exception message', 422);

        $generator = new ServerRequestErrorResponseGenerator($this->responseFactory, true);

        $this->assertSame($this->response->reveal(), $generator($e));
    }
}
