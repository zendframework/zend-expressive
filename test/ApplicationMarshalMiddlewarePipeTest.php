<?php
/**
 * @see       https://github.com/zendframework/zend-expressive for the canonical source repository
 * @copyright Copyright (c) 2016 Zend Technologies USA Inc. (http://www.zend.com)
 * @license   https://github.com/zendframework/zend-expressive/blob/master/LICENSE.md New BSD License
 */

namespace ZendTest\Expressive;

use PHPUnit_Framework_TestCase as TestCase;
use Prophecy\Argument;
use Psr\Http\Message\ResponseInterface;
use RuntimeException;
use Zend\Diactoros\Response\EmitterInterface;
use Zend\Diactoros\Response;
use Zend\Diactoros\ServerRequest;
use Zend\Expressive\Application;
use Zend\Expressive\Router\RouterInterface;

/**
 * This test demonstrates that if the raise_throwables flag is set on the
 * application, the flag will be set on any middleware pipelines that the
 * application creates via marshalMiddlewarePipe(). This is necessary to
 * provide a predictable workflow when the flag is enabled at the application
 * level; otherwise, dispatchers in nested middleware may end up invoking
 * error middleware, leading to unexpected state.
 */
class ApplicationMarshalMiddlewarePipeTest extends TestCase
{
    public function testNestedMiddlewarePipeShouldHonorApplicationRaiseThrowablesFlag()
    {
        $expected = 'exceptional';
        $request  = new ServerRequest([], [], 'https://example.com/foo', 'GET', 'php://temp');
        $response = new Response();
        $router   = $this->prophesize(RouterInterface::class)->reveal();
        $emitter  = $this->prophesize(EmitterInterface::class);
        $emitter->emit(Argument::type(ResponseInterface::class))->shouldBeCalled();

        $app = new Application(
            $router,
            null,
            $this->createFinalHandler(),
            $emitter->reveal()
        );
        $app->raiseThrowables();

        $app->pipe($this->createErrorHandler($expected));
        $app->pipe([
            $this->createPassthroughMiddleware(),
            $this->createExceptionMiddleware('exceptional'),
            $this->createTerminalMiddleware(),
            $this->createErrorMiddleware(),
        ]);

        $app->run($request, $response);
    }

    public function createErrorHandler($expected)
    {
        return function ($request, $response, $next) use ($expected) {
            try {
                $response = $next($request, $response);
                return $response;
            } catch (\Throwable $e) {
                // fall-through
            } catch (\Exception $e) {
                // fall-through
            }

            $this->assertContains($expected, $e->getMessage());
            return $response->withStatus(500);
        };
    }

    public function createPassthroughMiddleware()
    {
        return function ($request, $response, $next) {
            return $next($request, $response);
        };
    }

    public function createTerminalMiddleware()
    {
        return function ($request, $response, $next) {
            return $response;
        };
    }

    public function createExceptionMiddleware($message)
    {
        return function ($request, $response, $next) use ($message) {
            throw new RuntimeException($message);
        };
    }

    public function createErrorMiddleware()
    {
        return function ($err, $request, $response, $next) {
            $this->fail('Error middleware was invoked, and should not have been');
        };
    }

    public function createFinalHandler()
    {
        return function ($request, $response, $err = null) {
            $this->fail('Final handler was invoked, and should not have been');
        };
    }
}
