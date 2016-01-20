<?php
/**
 * Zend Framework (http://framework.zend.com/)
 *
 * @see       https://github.com/zendframework/zend-expressive for the canonical source repository
 * @copyright Copyright (c) 2015-2016 Zend Technologies USA Inc. (http://www.zend.com)
 * @license   https://github.com/zendframework/zend-expressive/blob/master/LICENSE.md New BSD License
 */

namespace Zend\Expressive;

use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Message\ResponseInterface as Response;
use ReflectionMethod;
use ReflectionProperty;
use Zend\Stratigility\FinalHandler;
use Zend\Stratigility\MiddlewarePipe;
use Zend\Stratigility\Next;

/**
 * MiddlewarePipe implementation that acts as error middleware.
 *
 * Normal MiddlewarePipe implementations implement Zend\Stratigility\MiddlewareInterface,
 * which can be consumed as normal middleware, but not as error middleware, as
 * the signature for error middleware differs.
 *
 * This class wraps a MiddlewarePipe, and consumes its internal pipeline
 * within a functor signature that works for error middleware.
 *
 * It is not implemented as an extension of MiddlewarePipe, as that class
 * implements the MiddlewareInterface, which prevents its use as error
 * middleware.
 */
class ErrorMiddlewarePipe
{
    /**
     * @var MiddlewarePipe
     */
    private $pipeline;

    /**
     * @param MiddlewarePipe $pipe
     */
    public function __construct(MiddlewarePipe $pipeline)
    {
        $this->pipeline = $pipeline;
    }

    /**
     * Handle an error request.
     *
     * This is essentially a version of the MiddlewarePipe that acts as a pipeline
     * for solely error middleware; it's primary use case is to allow configuring
     * arrays of error middleware as a single pipeline.
     *
     * Operation is identical to MiddlewarePipe, with the single exception that
     * $next is called with the $error argument.
     *
     * @param mixed $error
     * @param Request $request
     * @param Response $response
     * @param callable $out
     * @return Response
     */
    public function __invoke($error, Request $request, Response $response, callable $out)
    {
        // Decorate instances with Stratigility decorators; required to work
        // with Next implementation.
        $request = $this->decorateRequest($request);
        $response = $this->decorateResponse($response);

        $pipeline = $this->getInternalPipeline();
        $done = $out ?: new FinalHandler([], $response);
        $next = new Next($pipeline, $done);
        $result = $next($request, $response, $error);

        return ($result instanceof Response ? $result : $response);
    }

    /**
     * Retrieve the internal pipeline from the composed MiddlewarePipe.
     *
     * Uses reflection to retrieve the internal pipeline from the composed
     * MiddlewarePipe, in order to allow using it to create a Next instance.
     *
     * @return \SplQueue
     */
    private function getInternalPipeline()
    {
        $r = new ReflectionProperty($this->pipeline, 'pipeline');
        $r->setAccessible(true);
        return $r->getValue($this->pipeline);
    }

    /**
     * Decorate the request with the Stratigility decorator.
     *
     * Proxies to the composed MiddlewarePipe's equivalent method.
     *
     * @param Request $request
     * @return \Zend\Stratigility\Http\Request
     */
    private function decorateRequest(Request $request)
    {
        $r = new ReflectionMethod($this->pipeline, 'decorateRequest');
        $r->setAccessible(true);
        return $r->invoke($this->pipeline, $request);
    }

    /**
     * Decorate the response with the Stratigility decorator.
     *
     * Proxies to the composed MiddlewarePipe's equivalent method.
     *
     * @param Response $response
     * @return \Zend\Stratigility\Http\Response
     */
    private function decorateResponse(Response $response)
    {
        $r = new ReflectionMethod($this->pipeline, 'decorateResponse');
        $r->setAccessible(true);
        return $r->invoke($this->pipeline, $response);
    }
}
