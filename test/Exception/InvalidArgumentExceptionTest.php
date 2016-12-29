<?php
/**
 * @see       https://github.com/zendframework/zend-expressive for the canonical source repository
 * @copyright Copyright (c) 2016 Zend Technologies USA Inc. (http://www.zend.com)
 * @license   https://github.com/zendframework/zend-expressive/blob/master/LICENSE.md New BSD License
 */

namespace ZendTest\Expressive;

use Zend\Expressive\Exception\InvalidArgumentException;
use Zend\Expressive\Exception\ExceptionInterface;

final class InvalidArgumentExceptionTest extends \PHPUnit_Framework_TestCase
{
    public function testException()
    {
        $exception = new InvalidArgumentException();

        $this->assertInstanceOf(ExceptionInterface::class, $exception);

        $this->setExpectedException(InvalidArgumentException::class);

        throw $exception;
    }
}
