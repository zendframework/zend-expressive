<?php
/**
 * @see       https://github.com/zendframework/zend-expressive for the canonical source repository
 * @copyright Copyright (c) 2016-2017 Zend Technologies USA Inc. (http://www.zend.com)
 * @license   https://github.com/zendframework/zend-expressive/blob/master/LICENSE.md New BSD License
 */
declare(strict_types=1);

namespace Zend\Expressive\Middleware;

use Interop\Http\Server\MiddlewareInterface;
use Interop\Http\Server\RequestHandlerInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Zend\Expressive\Delegate\NotFoundDelegate;

class NotFoundHandler implements MiddlewareInterface
{
    /**
     * @var NotFoundDelegate
     */
    private $internalDelegate;

    public function __construct(NotFoundDelegate $internalDelegate)
    {
        $this->internalDelegate = $internalDelegate;
    }

    /**
     * Creates and returns a 404 response.
     */
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler) : ResponseInterface
    {
        return $this->internalDelegate->handle($request);
    }
}
