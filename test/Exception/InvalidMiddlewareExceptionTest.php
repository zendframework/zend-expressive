<?php
/**
 * @see       https://github.com/zendframework/zend-expressive for the canonical source repository
 * @copyright Copyright (c) 2016 Zend Technologies USA Inc. (http://www.zend.com)
 * @license   https://github.com/zendframework/zend-expressive/blob/master/LICENSE.md New BSD License
 */

namespace ZendTest\Expressive;

use Zend\Expressive\Exception\InvalidMiddlewareException;
use Zend\Expressive\Exception\ExceptionInterface;

final class InvalidMiddlewareExceptionTest extends \PHPUnit_Framework_TestCase
{
    public function testException()
    {
        $exception = new InvalidMiddlewareException();

        $this->assertInstanceOf(ExceptionInterface::class, $exception);
        $this->assertInstanceOf(InvalidMiddlewareException::class, $exception);

        $this->setExpectedException(InvalidMiddlewareException::class);

        throw $exception;
    }
}
