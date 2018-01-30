<?php
/**
 * @see       https://github.com/zendframework/zend-expressive for the canonical source repository
 * @copyright Copyright (c) 2018 Zend Technologies USA Inc. (https://www.zend.com)
 * @license   https://github.com/zendframework/zend-expressive/blob/master/LICENSE.md New BSD License
 */

declare(strict_types=1);

namespace Zend\Expressive;

use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Throwable;
use Zend\Diactoros\Response\EmitterInterface;

/**
 * Run an application.
 *
 * For purposes of this class, any PSR-15 RequestHandlerInterface is considered
 * an "application".
 *
 * The ApplicationRunner will marshal a request using the composed factory, and
 * then pass the request to the composed handler. Finally, it emits the response
 * returned by the handler using the composed emitter.
 *
 * If the factory for generating the request raises an exception or throwable,
 * then the runner will use the composed error response generator to generate a
 * response, based on the exception or throwable raised.
 */
class ApplicationRunner
{
    /**
     * @var EmitterInterface
     */
    private $emitter;

    /**
     * A request handler to run as the application.
     *
     * @var RequestHandlerInterface
     */
    private $handler;

    /**
     * A factory capable of generating an error response in the scenario that
     * the $serverRequestFactory raises an exception during generation of the
     * request instance.
     *
     * The factory will receive the Throwable or Exception that caused the error,
     * and must return a Psr\Http\Message\ResponseInterface instance.
     *
     * @var callable
     */
    private $serverRequestErrorResponseGenerator;

    /**
     * A factory capable of generating a Psr\Http\Message\ServerRequestInterface instance.
     * The factory will not receive any arguments.
     *
     * @var callable
     */
    private $serverRequestFactory;

    public function __construct(
        RequestHandlerInterface $handler,
        EmitterInterface $emitter,
        callable $serverRequestFactory,
        callable $serverRequestErrorResponseGenerator
    ) {
        $this->handler = $handler;
        $this->emitter = $emitter;
        $this->serverRequestFactory = $serverRequestFactory;
        $this->serverRequestErrorResponseGenerator = $serverRequestErrorResponseGenerator;
    }

    /**
     * Run the application
     */
    public function run(ServerRequestInterface $request = null) : void
    {
        try {
            $request = $request ?: ($this->serverRequestFactory)();
        } catch (Throwable $e) {
            // Error in generating the request
            $this->emitMarshalServerRequestException($e);
            return;
        }

        $response = $this->handler->handle($request);

        $this->emitter->emit($response);
    }

    private function emitMarshalServerRequestException(Throwable $exception) : void
    {
        $response = ($this->serverRequestErrorResponseGenerator)($exception);
        $this->emitter->emit($response);
    }
}
