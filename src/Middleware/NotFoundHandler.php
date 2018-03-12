<?php
/**
 * @see       https://github.com/zendframework/zend-expressive for the canonical source repository
 * @copyright Copyright (c) 2016-2017 Zend Technologies USA Inc. (http://www.zend.com)
 * @license   https://github.com/zendframework/zend-expressive/blob/master/LICENSE.md New BSD License
 */

namespace Zend\Expressive\Middleware;

use Interop\Http\ServerMiddleware\DelegateInterface;
use Interop\Http\ServerMiddleware\MiddlewareInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Zend\Expressive\Delegate\NotFoundDelegate;

/**
 * @deprecated since 2.2.0; to be removed in 3.0.0. Version 3.0.0 will reuse
 *     re-use the Zend\Expressive\Handler\NotFoundHandler directly within a
 *     middleware pipeline instead.
 */
class NotFoundHandler implements MiddlewareInterface
{
    /**
     * @var NotFoundDelegate
     */
    private $internalDelegate;

    /**
     * @param NotFoundDelegate $internalDelegate
     */
    public function __construct(NotFoundDelegate $internalDelegate)
    {
        $this->internalDelegate = $internalDelegate;
    }

    /**
     * Creates and returns a 404 response.
     *
     * @param ServerRequestInterface $request Passed to internal delegate
     * @param DelegateInterface $delegate Ignored.
     * @return ResponseInterface
     */
    public function process(ServerRequestInterface $request, DelegateInterface $delegate)
    {
        return $this->internalDelegate->process($request);
    }
}
