<?php
/**
 * @see       https://github.com/zendframework/zend-expressive for the canonical source repository
 * @copyright Copyright (c) 2015-2017 Zend Technologies USA Inc. (http://www.zend.com)
 * @license   https://github.com/zendframework/zend-expressive/blob/master/LICENSE.md New BSD License
 */

namespace ZendTest\Expressive\TestAsset;

class InvokableMiddleware
{
    public function __invoke($request, $response, $next)
    {
        return self::staticallyCallableMiddleware($request, $response, $next);
    }

    public static function staticallyCallableMiddleware($request, $response, $next)
    {
        return $response->withHeader('X-Invoked', __CLASS__);
    }
}
