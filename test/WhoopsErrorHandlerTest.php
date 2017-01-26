<?php
/**
 * @see       http://github.com/zendframework/zend-expressive for the canonical source repository
 * @copyright Copyright (c) 2015-2017 Zend Technologies USA Inc. (http://www.zend.com)
 * @license   https://github.com/zendframework/zend-expressive/blob/master/LICENSE.md New BSD License
 */

namespace ZendTest\Expressive;

use Exception;
use PHPUnit\Framework\TestCase;
use Prophecy\Argument;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\StreamInterface;
use ReflectionClass;
use Whoops\Handler\Handler;
use Whoops\Handler\PrettyPageHandler;
use Whoops\Run as Whoops;
use Zend\Diactoros\ServerRequest;
use Zend\Expressive\WhoopsErrorHandler;

/**
 * @covers Zend\Expressive\WhoopsErrorHandler
 */
class WhoopsErrorHandlerTest extends TestCase
{
    public function getPrettyPageHandler()
    {
        $handler = $this->prophesize(PrettyPageHandler::class);

        // whoops 2.1.5 introduced a new method, which the runner calls when
        // invoked; as such, we need to test if this method is present and mock
        // a request to it if present.
        $r = new ReflectionClass(PrettyPageHandler::class);
        if ($r->hasMethod('contentType')) {
            $handler->contentType()->willReturn('text/html');
        }

        return $handler;
    }

    public function testInstantiationRequiresWhoopsAndPageHandler()
    {
        $whoops = new Whoops();
        $whoops->allowQuit(false);
        $pageHandler = $this->getPrettyPageHandler();

        $handler = new WhoopsErrorHandler($whoops, $pageHandler->reveal());
        $this->assertAttributeSame($whoops, 'whoops', $handler);
        $this->assertAttributeSame($pageHandler->reveal(), 'whoopsHandler', $handler);
    }

    public function testExceptionErrorPreparesPageHandlerAndInvokesWhoops()
    {
        $exception = new Exception('Boom!');

        $pageHandler = $this->getPrettyPageHandler();
        $pageHandler->addDataTable('Expressive Application Request', Argument::type('array'))->shouldBeCalled();
        $pageHandler->setRun(Argument::any())->shouldBeCalled();
        $pageHandler->setInspector(Argument::any())->shouldBeCalled();
        $pageHandler->setException($exception)->shouldBeCalled();
        $pageHandler->handle(Argument::any())->willReturn(Handler::QUIT)->shouldBeCalled();

        $whoops = new Whoops();
        $whoops->allowQuit(false);

        $handler = new WhoopsErrorHandler($whoops, $pageHandler->reveal());

        $stream = $this->prophesize(StreamInterface::class);
        $stream->write('')->shouldBeCalled();

        $expected = $this->prophesize(ResponseInterface::class);
        $expected->getBody()->will(function () use ($stream) {
            return $stream->reveal();
        });

        $response = $this->prophesize(ResponseInterface::class);
        $response->getStatusCode()->willReturn(200);
        $response->withStatus(500)->will(function () use ($expected) {
            return $expected->reveal();
        });

        $request = $this->prophesize(ServerRequestInterface::class);
        $request->getUri()->willReturn('http://example.com');
        $request->getMethod()->shouldBeCalled();
        $request->getServerParams()->willReturn(['SCRIPT_NAME' => __FILE__])->shouldBeCalled();
        $request->getHeaders()->shouldBeCalled();
        $request->getCookieParams()->shouldBeCalled();
        $request->getAttributes()->shouldBeCalled();
        $request->getQueryParams()->shouldBeCalled();
        $request->getParsedBody()->shouldBeCalled();
        $request->getAttribute('originalUri', false)->willReturn(false);
        $request->getAttribute('originalRequest', false)->willReturn(false);

        $result = $handler($request->reveal(), $response->reveal(), $exception);
        $this->assertSame($expected->reveal(), $result);
    }

    public function testOriginalRequestIsPulledFromProvidedRequest()
    {
        $exception = new Exception('Boom!');

        $pageHandler = $this->getPrettyPageHandler();
        $pageHandler->addDataTable('Expressive Application Request', Argument::type('array'))->shouldBeCalled();
        $pageHandler->setRun(Argument::any())->shouldBeCalled();
        $pageHandler->setInspector(Argument::any())->shouldBeCalled();
        $pageHandler->setException($exception)->shouldBeCalled();
        $pageHandler->handle(Argument::any())->willReturn(Handler::QUIT)->shouldBeCalled();

        $whoops  = new Whoops();
        $whoops->allowQuit(false);

        $handler = new WhoopsErrorHandler($whoops, $pageHandler->reveal());

        $stream = $this->prophesize(StreamInterface::class);
        $stream->write('')->shouldBeCalled();

        $expected = $this->prophesize(ResponseInterface::class);
        $expected->getBody()->will(function () use ($stream) {
            return $stream->reveal();
        });

        $response = $this->prophesize(ResponseInterface::class);
        $response->getStatusCode()->willReturn(200);
        $response->withStatus(500)->will(function () use ($expected) {
            return $expected->reveal();
        });

        $request           = new ServerRequest(['SCRIPT_NAME' => __FILE__]);
        $decoratingRequest = $this->prophesize(ServerRequestInterface::class);
        $decoratingRequest->getAttribute('originalRequest', false)->willReturn($request);
        $decoratingRequest->getAttribute('originalUri', false)->willReturn($request->getUri());

        $result = $handler($decoratingRequest->reveal(), $response->reveal(), $exception);
        $this->assertSame($expected->reveal(), $result);
    }
}
