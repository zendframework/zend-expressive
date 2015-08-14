<?php
/**
 * Zend Framework (http://framework.zend.com/)
 *
 * @see       http://github.com/zendframework/zend-expressive for the canonical source repository
 * @copyright Copyright (c) 2015 Zend Technologies USA Inc. (http://www.zend.com)
 * @license   https://github.com/zendframework/zend-expressive/blob/master/LICENSE.md New BSD License
 */

namespace ZendTest\Expressive;

use Exception;
use PHPUnit_Framework_TestCase as TestCase;
use Prophecy\Argument;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\StreamInterface;
use Zend\Expressive\TemplatedErrorHandler;
use Zend\Expressive\Template\TemplateInterface;

class TemplatedErrorHandlerTest extends TestCase
{
    public function getTemplateImplementation()
    {
        return $this->prophesize(TemplateInterface::class);
    }

    public function getRequest($stream)
    {
        $request = $this->prophesize(RequestInterface::class);
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
        $this->assertAttributeSame(null, 'template', $handler);
    }

    public function testCanBeInstantiatedWithTemplateImplementation()
    {
        $template = $this->getTemplateImplementation()->reveal();
        $handler = new TemplatedErrorHandler($template);
        $this->assertAttributeSame($template, 'template', $handler);
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

        $result = $handler($request->reveal(), $response->reveal());
        $this->assertSame($response->reveal(), $result);
    }

    /* Tests covering handlePotentialSuccess() paths; WITH TEMPLATE */

    /**
     * @group templated
     */
    public function testInvocationWithoutErrorAndEmptyResponseCanReturnTemplated404Response()
    {
        $template = $this->getTemplateImplementation();
        $template
            ->render(
                'error::404',
                Argument::type('array')
            )
            ->willReturn('Templated contents');

        $handler = new TemplatedErrorHandler(
            $template->reveal(),
            'error::404',
            'error::500'
        );

        $expected = $this->getResponse($this->getStream());

        $stream   = $this->getStream();
        $stream->getSize()->willReturn(0);
        $stream->write('Templated contents')->shouldBeCalled();

        $response = $this->getResponse($stream);
        $response->getStatusCode()->willReturn(200);
        $response->withStatus(404)->willReturn($expected->reveal());

        $request = $this->getRequest($this->getStream());
        $request->getUri()->shouldBeCalled();

        $result = $handler($request->reveal(), $response->reveal());
        $this->assertSame($expected->reveal(), $result);
    }

    /**
     * @group templated
     */
    public function testInvocationWithoutErrorAndResponseSameAsOriginalCanReturnTemplated404Response()
    {
        $template = $this->getTemplateImplementation();
        $template
            ->render(
                'error::404',
                Argument::type('array')
            )
            ->willReturn('Templated contents');

        $handler = new TemplatedErrorHandler(
            $template->reveal(),
            'error::404',
            'error::500'
        );

        $expected = $this->getResponse($this->getStream());

        $stream   = $this->getStream();
        $stream->getSize()->willReturn(100);
        $stream->write('Templated contents')->shouldBeCalled();

        $response = $this->getResponse($stream);
        $response->getStatusCode()->willReturn(200);
        $response->withStatus(404)->willReturn($expected->reveal());

        $handler->setOriginalResponse($response->reveal());

        $request = $this->getRequest($this->getStream());
        $request->getUri()->shouldBeCalled();

        $result = $handler($request->reveal(), $response->reveal());
        $this->assertSame($expected->reveal(), $result);
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
        for ($i = 400; $i < 600; $i += 1) {
            yield [$i];
        }
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
        for ($i = 0; $i < 400; $i += 1) {
            yield [$i];
        }

        for ($i = 600; $i < 700; $i += 1) {
            yield [$i];
        }
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

    /**
     * @group templated
     */
    public function testNonExceptionErrorReturnsResponseWith500StatusAndTemplateResultsWhenTemplatingInjected()
    {
        $template = $this->getTemplateImplementation();
        $template
            ->render(
                'error::500',
                Argument::type('array')
            )
            ->willReturn('Templated contents');

        $handler = new TemplatedErrorHandler(
            $template->reveal(),
            'error::404',
            'error::500'
        );

        $stream   = $this->getStream();
        $stream->getSize()->willReturn(0);
        $stream->write('Templated contents')->shouldBeCalled();

        $expected = $this->getResponse($stream);
        $expected->getStatusCode()->willReturn(500)->shouldBeCalled();
        $expected->getReasonPhrase()->shouldBeCalled();

        $response = $this->getResponse($stream);
        $response->getStatusCode()->willReturn(200);
        $response->withStatus(500)->willReturn($expected->reveal());

        $request = $this->getRequest($this->getStream());
        $request->getUri()->shouldBeCalled();

        $result = $handler($request->reveal(), $response->reveal(), 'error');
        $this->assertSame($expected->reveal(), $result);
    }

    /**
     * @group templated
     */
    public function testExceptionErrorReturnsResponseWith500StatusAndTemplateResultsWhenTemplatingInjected()
    {
        $template = $this->getTemplateImplementation();
        $template
            ->render(
                'error::500',
                Argument::type('array')
            )
            ->willReturn('Templated contents');

        $handler = new TemplatedErrorHandler(
            $template->reveal(),
            'error::404',
            'error::500'
        );

        $stream   = $this->getStream();
        $stream->getSize()->willReturn(0);
        $stream->write('Templated contents')->shouldBeCalled();

        $expected = $this->getResponse($stream);
        $expected->getStatusCode()->willReturn(500)->shouldBeCalled();
        $expected->getReasonPhrase()->shouldBeCalled();

        $response = $this->getResponse($stream);
        $response->getStatusCode()->willReturn(200);
        $response->withStatus(500)->willReturn($expected->reveal());

        $request = $this->getRequest($this->getStream());
        $request->getUri()->shouldBeCalled();

        $result = $handler($request->reveal(), $response->reveal(), new Exception());
        $this->assertSame($expected->reveal(), $result);
    }
}
