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
use Psr\Http\Message\ServerRequestInterface;
use RuntimeException;
use Zend\Diactoros\Response\EmitterInterface;
use Zend\Diactoros\Response;
use Zend\Diactoros\ServerRequest;
use Zend\Expressive\Application;
use Zend\Expressive\Router\RouterInterface;
use Zend\Expressive\Router\RouteResult;

class ApplicationMarshalMiddlewarePipeTest extends TestCase
{
    /**
     * This test demonstrates that if the raise_throwables flag is set on the
     * application, the flag will be set on any middleware pipelines that the
     * application creates via marshalMiddlewarePipe().
     *
     * This is necessary to provide a predictable workflow when the flag is
     * enabled at the application level; otherwise, dispatchers in nested
     * middleware may end up invoking error middleware, leading to unexpected
     * state.
     */
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

    public function testNestedMiddlewarePipeWithApplicationMiddlewareShouldPipeThemAsCallables()
    {
        $request  = new ServerRequest([], [], 'https://example.com/foo', 'GET', 'php://temp');
        $response = new Response();
        $expected = clone $response;

        $routeResult = $this->prophesize(RouteResult::class);
        $routeResult->isFailure()->willReturn(true);
        $routeResult->isMethodFailure()->willReturn(false);

        $router = $this->prophesize(RouterInterface::class);
        $router
            ->match(Argument::type(ServerRequestInterface::class))
            ->will([$routeResult, 'reveal']);

        $emitter  = $this->prophesize(EmitterInterface::class);
        $emitter->emit(Argument::that(function ($response) use ($expected) {
            $original = method_exists($response, 'getOriginalResponse')
                ? $response->getOriginalResponse()
                : $response;

            $this->assertSame($expected, $response);
            return true;
        }))->shouldBeCalled();

        $app = new Application(
            $router->reveal(),
            null,
            $this->createFinalHandler(),
            $emitter->reveal()
        );

        $app->pipe([
            Application::ROUTING_MIDDLEWARE,
            Application::DISPATCH_MIDDLEWARE,
            $this->createTerminalMiddlewareForDispatchPipeline($expected),
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

    public function createTerminalMiddlewareForDispatchPipeline(ResponseInterface $expected)
    {
        return function ($request, $response, $next) use ($expected) {
            $this->assertNull(
                $request->getAttribute(RouteResult::class, null),
                'Request contains a RouteResult but should not'
            );
            return $expected;
        };
    }
}
