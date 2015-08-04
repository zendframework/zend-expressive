<?php
namespace ZendTest\Expressive\TestAsset;

class InvokableMiddleware
{
    public function __invoke($request, $response, $next)
    {
        return $response->withHeader('X-Invoked', __CLASS__);
    }
}
