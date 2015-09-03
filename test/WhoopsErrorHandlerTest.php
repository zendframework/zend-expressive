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
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\StreamInterface;
use Whoops\Handler\PrettyPageHandler;
use Whoops\Run as Whoops;
use Zend\Expressive\WhoopsErrorHandler;

class WhoopsErrorHandlerTest extends TestCase
{
    public function getWhoops()
    {
        return $this->prophesize(Whoops::class);
    }

    public function getPrettyPageHandler()
    {
        return $this->prophesize(PrettyPageHandler::class);
    }

    public function testInstantiationRequiresWhoopsAndPageHandler()
    {
        $whoops = $this->getWhoops();
        $pageHandler = $this->getPrettyPageHandler();

        $handler = new WhoopsErrorHandler($whoops->reveal(), $pageHandler->reveal());
        $this->assertAttributeSame($whoops->reveal(), 'whoops', $handler);
        $this->assertAttributeSame($pageHandler->reveal(), 'whoopsHandler', $handler);
    }

    public function testExceptionErrorPreparesPageHandlerAndInvokesWhoops()
    {
        $exception = new Exception('Boom!');

        $whoops = $this->getWhoops();
        $whoops->handleException($exception)->willReturn('Whoops content');

        $pageHandler = $this->getPrettyPageHandler();
        $pageHandler->addDataTable('Expressive Application Request', Argument::type('array'))->shouldBeCalled();

        $handler = new WhoopsErrorHandler($whoops->reveal(), $pageHandler->reveal());

        $stream    = $this->prophesize(StreamInterface::class);
        $stream->write('Whoops content')->shouldBeCalled();

        $expected  = $this->prophesize(ResponseInterface::class);
        $expected->getBody()->will(function () use ($stream) {
            return $stream->reveal();
        });

        $response  = $this->prophesize(ResponseInterface::class);
        $response->getStatusCode()->willReturn(200);
        $response->withStatus(500)->will(function () use ($expected) {
            return $expected->reveal();
        });

        $request   = $this->prophesize(ServerRequestInterface::class);
        $request->getUri()->willReturn('http://example.com');
        $request->getMethod()->shouldBeCalled();
        $request->getServerParams()->willReturn(['SCRIPT_NAME' => __FILE__])->shouldBeCalled();
        $request->getHeaders()->shouldBeCalled();
        $request->getCookieParams()->shouldBeCalled();
        $request->getAttributes()->shouldBeCalled();
        $request->getQueryParams()->shouldBeCalled();
        $request->getParsedBody()->shouldBeCalled();

        $result = $handler($request->reveal(), $response->reveal(), $exception);
        $this->assertSame($expected->reveal(), $result);
    }
}
