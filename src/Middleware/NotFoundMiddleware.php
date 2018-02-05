<?php
/**
 * @see       https://github.com/zendframework/zend-expressive for the canonical source repository
 * @copyright Copyright (c) 2016-2017 Zend Technologies USA Inc. (https://www.zend.com)
 * @license   https://github.com/zendframework/zend-expressive/blob/master/LICENSE.md New BSD License
 */

declare(strict_types=1);

namespace Zend\Expressive\Middleware;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Zend\Expressive\Handler\NotFoundHandler;

class NotFoundMiddleware implements MiddlewareInterface
{
    /**
     * @var NotFoundHandler
     */
    private $internalHandler;

    /**
     * @param NotFoundHandler $internalHandler
     */
    public function __construct(NotFoundHandler $internalHandler)
    {
        $this->internalHandler = $internalHandler;
    }

    /**
     * Creates and returns a 404 response.
     *
     * @param ServerRequestInterface $request Passed to internal handler
     * @param RequestHandlerInterface $handler Ignored.
     */
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler) : ResponseInterface
    {
        return $this->internalHandler->handle($request);
    }
}
