<?php
/**
 * @see       https://github.com/zendframework/zend-expressive for the canonical source repository
 * @copyright Copyright (c) 2016-2017 Zend Technologies USA Inc. (http://www.zend.com)
 * @license   https://github.com/zendframework/zend-expressive/blob/master/LICENSE.md New BSD License
 */

namespace ZendTest\Expressive\Middleware;

use PHPUnit\Framework\TestCase;
use Zend\Expressive\Middleware\ImplicitOptionsMiddleware;
use Zend\Expressive\Router\Middleware\ImplicitOptionsMiddleware as BaseImplicitOptionsMiddleware;

class ImplicitOptionsMiddlewareTest extends TestCase
{
    public function testConstructorTriggersDeprecationNotice()
    {
        $test = (object) ['message' => false];
        set_error_handler(function ($errno, $errstr) use ($test) {
            $test->message = $errstr;
            return true;
        }, E_USER_DEPRECATED);

        $middleware = new ImplicitOptionsMiddleware();
        restore_error_handler();

        $this->assertInstanceOf(BaseImplicitOptionsMiddleware::class, $middleware);
        $this->assertInternalType('string', $test->message);
        $this->assertContains('deprecated starting with zend-expressive 2.2', $test->message);
    }
}
