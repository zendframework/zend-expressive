<?php
/**
 * Zend Framework (http://framework.zend.com/)
 *
 * @see       http://github.com/zendframework/zend-expressive for the canonical source repository
 * @copyright Copyright (c) 2015-2016 Zend Technologies USA Inc. (http://www.zend.com)
 * @license   https://github.com/zendframework/zend-expressive/blob/master/LICENSE.md New BSD License
 */

namespace ZendTest\Expressive;

use Exception;
use PHPUnit_Framework_TestCase as TestCase;
use Prophecy\Argument;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\StreamInterface;
use Zend\Expressive\Template\TemplateRendererInterface;
use Zend\Expressive\TemplatedErrorHandler;

/**
 * @covers Zend\Expressive\TemplatedErrorHandler
 */
class TemplatedErrorHandlerTest extends TestCase
{
    public function getTemplateImplementation()
    {
        return $this->prophesize(TemplateRendererInterface::class);
    }

    public function getRequest($stream)
    {
        $request = $this->prophesize(ServerRequestInterface::class);
        $request->getBody()->will(function () use ($stream) {
            return $stream->reveal();
        });
        return $request;
    }

    public function getResponse($stream)
    {
        $response = $this->prophesize(ResponseInterface::class);
        $response->getBody()->will(function () use ($stream) {
            return $stream->reveal();
        });
        return $response;
    }

    public function getStream()
    {
        return $this->prophesize(StreamInterface::class);
    }

    public function testCanBeInstantiatedWithNoArguments()
    {
        $handler = new TemplatedErrorHandler();
        $this->assertAttributeSame(null, 'renderer', $handler);
    }

    public function testCanBeInstantiatedWithTemplateImplementation()
    {
        $renderer = $this->getTemplateImplementation()->reveal();
        $handler = new TemplatedErrorHandler($renderer);
        $this->assertAttributeSame($renderer, 'renderer', $handler);
    }

    public function testOriginalResponseIsNullByDefault()
    {
        $handler = new TemplatedErrorHandler();
        $this->assertAttributeSame(null, 'originalResponse', $handler);
    }

    public function testOriginalResponseCanBeInjectedAtInstantiation()
    {
        $stream = $this->getStream();
        $stream->getSize()->willReturn(100);
        $response = $this->getResponse($stream);

        $handler = new TemplatedErrorHandler(null, '', '', $response->reveal());
        $this->assertAttributeSame($response->reveal(), 'originalResponse', $handler);
        $this->assertAttributeEquals(100, 'bodySize', $handler);
    }

    /* Tests covering handlePotentialSuccess() paths; NO TEMPLATE */

    public function testInvocationWithoutErrorAndPositiveStreamSizeReturnsResponse()
    {
        $handler = new TemplatedErrorHandler();

        $stream   = $this->getStream();
        $stream->getSize()->willReturn(100);
        $response = $this->getResponse($stream);
        $response->getStatusCode()->willReturn(200);
        $request = $this->getRequest($this->getStream());

        $result = $handler($request->reveal(), $response->reveal());
        $this->assertSame($response->reveal(), $result);
    }

    public function testInvocationWithoutErrorAndEmptyResponseReturns404Response()
    {
        $handler = new TemplatedErrorHandler();

        $expected = $this->getResponse($this->getStream());

        $stream   = $this->getStream();
        $stream->getSize()->willReturn(0);
        $response = $this->getResponse($stream);
        $response->getStatusCode()->willReturn(200);
        $response->withStatus(404)->willReturn($expected->reveal());

        $request = $this->getRequest($this->getStream());

        $result = $handler($request->reveal(), $response->reveal());
        $this->assertSame($expected->reveal(), $result);
    }

    public function testInvocationWithoutErrorAndResponseSameAsOriginalReturns404Response()
    {
        $handler = new TemplatedErrorHandler();

        $expected = $this->getResponse($this->getStream());

        $stream   = $this->getStream();
        $stream->getSize()->willReturn(100);
        $response = $this->getResponse($stream);
        $response->getStatusCode()->willReturn(200);
        $response->withStatus(404)->willReturn($expected->reveal());

        $handler->setOriginalResponse($response->reveal());

        $request = $this->getRequest($this->getStream());
        $request
            ->getAttribute('originalResponse', Argument::that([$response, 'reveal']))
            ->will([$response, 'reveal']);

        $result = $handler($request->reveal(), $response->reveal());
        $this->assertSame($expected->reveal(), $result);
    }

    public function testInvocationWithoutErrorAndResponseSameAsOriginalWithNewBodyContentsReturnsResponse()
    {
        $handler = new TemplatedErrorHandler();

        $count    = 0;
        $stream   = $this->getStream();
        $stream->getSize()->will(function () use (&$count) {
            ++$count;
            return 100 * $count;
        });
        $response = $this->getResponse($stream);
        $response->getStatusCode()->willReturn(200);

        $handler->setOriginalResponse($response->reveal());

        $request = $this->getRequest($this->getStream());
        $request
            ->getAttribute('originalResponse', Argument::that([$response, 'reveal']))
            ->will([$response, 'reveal']);

        $result = $handler($request->reveal(), $response->reveal());
        $this->assertSame($response->reveal(), $result);
    }

    public function testInvocationWithoutErrorAndResponseDifferentThanOriginalReturnsResponse()
    {
        $handler = new TemplatedErrorHandler();

        $originalStream = $this->getStream();
        $originalStream->getSize()->willReturn(0);
        $expected = $this->getResponse($originalStream);
        $handler->setOriginalResponse($expected->reveal());

        $response = $this->getResponse($this->getStream());
        $request  = $this->getRequest($this->getStream());
        $request
            ->getAttribute('originalResponse', Argument::that([$response, 'reveal']))
            ->will([$response, 'reveal']);

        $result = $handler($request->reveal(), $response->reveal());
        $this->assertSame($response->reveal(), $result);
    }

    /* Tests covering handlePotentialSuccess() paths; WITH TEMPLATE */

    /**
     * @group templated
     */
    public function testInvocationWithoutErrorAndEmptyResponseCanReturnTemplated404Response()
    {
        $renderer = $this->getTemplateImplementation();
        $renderer
            ->render(
                'error::404',
                Argument::type('array')
            )
            ->willReturn('Templated contents');

        $handler = new TemplatedErrorHandler(
            $renderer->reveal(),
            'error::404',
            'error::500'
        );

        $stream   = $this->getStream();
        $stream->getSize()->willReturn(0);

        $response = $this->prophesize(ResponseInterface::class);
        $response->getStatusCode()->willReturn(200);
        $response->getBody()->will([$stream, 'reveal']);
        $response
            ->withBody(Argument::that(function ($body) use ($stream) {
                if (! $body instanceof StreamInterface) {
                    return false;
                }

                if ($body === $stream) {
                    return false;
                }

                return 'Templated contents' === (string) $body;
            }))
            ->will([$response, 'reveal']);
        $response->withStatus(404)->will([$response, 'reveal']);

        $request = $this->getRequest($this->getStream());
        $request->getUri()->shouldBeCalled();

        $result = $handler($request->reveal(), $response->reveal());
        $this->assertSame($response->reveal(), $result);
    }

    /**
     * @group templated
     */
    public function testInvocationWithoutErrorAndResponseSameAsOriginalCanReturnTemplated404Response()
    {
        $renderer = $this->getTemplateImplementation();
        $renderer
            ->render(
                'error::404',
                Argument::type('array')
            )
            ->willReturn('Templated contents');

        $handler = new TemplatedErrorHandler(
            $renderer->reveal(),
            'error::404',
            'error::500'
        );

        $stream   = $this->getStream();
        $stream->getSize()->willReturn(100);

        $response = $this->prophesize(ResponseInterface::class);
        $response->getStatusCode()->willReturn(200);
        $response->getBody()->will([$stream, 'reveal']);
        $response
            ->withBody(Argument::that(function ($body) use ($stream) {
                if (! $body instanceof StreamInterface) {
                    return false;
                }

                if ($body === $stream) {
                    return false;
                }

                return 'Templated contents' === (string) $body;
            }))
            ->will([$response, 'reveal']);
        $response->withStatus(404)->will([$response, 'reveal']);

        $handler->setOriginalResponse($response->reveal());

        $request = $this->getRequest($this->getStream());
        $request->getUri()->shouldBeCalled();
        $request
            ->getAttribute('originalResponse', Argument::that([$response, 'reveal']))
            ->will([$response, 'reveal']);

        $result = $handler($request->reveal(), $response->reveal());
        $this->assertSame($response->reveal(), $result);
    }

    /* Tests covering handleErrorResponse() paths; NO TEMPLATE */

    public function testNonExceptionErrorReturnsResponseWith500StatusWhenNoTemplatingInjected()
    {
        $handler = new TemplatedErrorHandler();

        $response = $this->getResponse($this->getStream());
        $response->getStatusCode()->willReturn(200);
        $response->withStatus(500)->will(function () use ($response) {
            return $response->reveal();
        });

        $request = $this->getRequest($this->getStream());

        $result = $handler($request->reveal(), $response->reveal(), 'error');
        $this->assertSame($response->reveal(), $result);
    }

    public function testExceptionErrorReturnsResponseWith500StatusWhenNoTemplatingInjected()
    {
        $handler = new TemplatedErrorHandler();

        $response = $this->getResponse($this->getStream());
        $response->getStatusCode()->willReturn(200);
        $response->withStatus(500)->will(function () use ($response) {
            return $response->reveal();
        });

        $request   = $this->getRequest($this->getStream());
        $exception = new Exception();

        $result = $handler($request->reveal(), $response->reveal(), $exception);
        $this->assertSame($response->reveal(), $result);
    }

    public function validHttpErrorStatusCodes()
    {
        return [
            // Lower boundary
            // 399 is invalid
            '400' => [400],
            '401' => [401],

            // Upper boundary
            '598' => [598],
            '599' => [599],
            // 600 is valid
        ];
    }

    /**
     * @dataProvider validHttpErrorStatusCodes
     */
    public function testExceptionErrorUsesExceptionCodeAsStatusIfValidHTTPErrorStatus($code)
    {
        $handler = new TemplatedErrorHandler();

        $response = $this->getResponse($this->getStream());
        $response->getStatusCode()->willReturn(200);
        $response->withStatus($code)->will(function () use ($response) {
            return $response->reveal();
        });

        $request   = $this->getRequest($this->getStream());
        $exception = new Exception('Message', $code);
        $handler($request->reveal(), $response->reveal(), $exception);
    }

    public function invalidHttpErrorStatusCodes()
    {
        return [
            // Lower boundary
            '399' => [399],
            // 400 is valid

            // Upper boundary
            // 599 is valid
            '600' => [600],
        ];
    }

    /**
     * @dataProvider invalidHttpErrorStatusCodes
     */
    public function testInvalidExceptionErrorCodeDefaultsToHTTP500Status($code)
    {
        $handler = new TemplatedErrorHandler();

        $response = $this->getResponse($this->getStream());
        $response->getStatusCode()->willReturn(200);
        $response->withStatus(500)->will(function () use ($response) {
            return $response->reveal();
        });

        $request   = $this->getRequest($this->getStream());
        $exception = new Exception('Message', $code);
        $handler($request->reveal(), $response->reveal(), $exception);
    }

    /* Tests covering handleErrorResponse() paths; WITH TEMPLATE */

    public function errors()
    {
        return [
            'error'     => ['error'],
            'exception' => [new Exception('exception')],
        ];
    }

    /**
     * @group templated
     * @dataProvider errors
     */
    public function testReturns500ResponseWithNewStreamWhenReturningAnErrorResponse($error)
    {
        $renderer = $this->getTemplateImplementation();
        $renderer
            ->render(
                'error::500',
                Argument::that(function ($params) use ($error) {
                    if (! is_array($params)) {
                        return false;
                    }

                    if (empty($params['error'])) {
                        return false;
                    }

                    return $error === $params['error'];
                })
            )
            ->willReturn('Templated contents');

        $handler = new TemplatedErrorHandler(
            $renderer->reveal(),
            'error::404',
            'error::500'
        );

        $expected = $this->prophesize(ResponseInterface::class);
        $expected->getStatusCode()->willReturn(500)->shouldBeCalled();
        $expected->getReasonPhrase()->shouldBeCalled();
        $expected
            ->withBody(Argument::that(function ($stream) {
                if (! $stream instanceof StreamInterface) {
                    return false;
                }

                return 'Templated contents' === (string) $stream;
            }))
            ->will([$expected, 'reveal']);

        $response = $this->prophesize(ResponseInterface::class);
        $response->getStatusCode()->willReturn(200);
        $response->withStatus(500)->willReturn($expected->reveal());

        $request = $this->getRequest($this->getStream());
        $request->getUri()->shouldBeCalled();

        $result = $handler($request->reveal(), $response->reveal(), $error);
        $this->assertSame($expected->reveal(), $result);
    }
}
