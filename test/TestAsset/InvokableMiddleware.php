<?php
/**
 * Zend Framework (http://framework.zend.com/)
 *
 * @see       https://github.com/zendframework/zend-diactoros for the canonical source repository
 * @copyright Copyright (c) 2015 Zend Technologies USA Inc. (http://www.zend.com)
 * @license   https://github.com/zendframework/zend-diactoros/blob/master/LICENSE.md New BSD License
 */

namespace ZendTest\Expressive\TestAsset;

class InvokableMiddleware
{
    public function __invoke($request, $response, $next)
    {
        return $response->withHeader('X-Invoked', __CLASS__);
    }
}
