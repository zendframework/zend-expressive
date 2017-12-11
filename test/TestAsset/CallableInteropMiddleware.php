<?php
/**
 * @see       https://github.com/zendframework/zend-expressive for the canonical source repository
 * @copyright Copyright (c) 2017 Zend Technologies USA Inc. (http://www.zend.com)
 * @license   https://github.com/zendframework/zend-expressive/blob/master/LICENSE.md New BSD License
 */

namespace ZendTest\Expressive\TestAsset;

use Interop\Http\ServerMiddleware\DelegateInterface;
use Psr\Http\Message\ServerRequestInterface;

class CallableInteropMiddleware
{
    public function __invoke(ServerRequestInterface $request, DelegateInterface $delegate)
    {
        $response = $delegate->process($request);
        return $response->withHeader('X-Callable-Interop-Middleware', __CLASS__);
    }
}
