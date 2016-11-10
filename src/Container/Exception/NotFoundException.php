<?php
/**
 * @see       https://github.com/zendframework/zend-expressive for the canonical source repository
 * @copyright Copyright (c) 2015-2016 Zend Technologies USA Inc. (http://www.zend.com)
 * @license   https://github.com/zendframework/zend-expressive/blob/master/LICENSE.md New BSD License
 */

namespace Zend\Expressive\Container\Exception;

use Interop\Container\Exception\NotFoundException as InteropNotFoundException;
use RuntimeException;

/**
 * Exception indicating a service was not found in the container.
 */
class NotFoundException extends RuntimeException implements
    ExceptionInterface,
    InteropNotFoundException
{
}
