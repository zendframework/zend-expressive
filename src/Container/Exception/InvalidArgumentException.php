<?php
/**
 * @see       https://github.com/zendframework/zend-expressive for the canonical source repository
 * @copyright Copyright (c) 2015-2016 Zend Technologies USA Inc. (http://www.zend.com)
 * @license   https://github.com/zendframework/zend-expressive/blob/master/LICENSE.md New BSD License
 */

namespace Zend\Expressive\Container\Exception;

use Interop\Container\Exception\ContainerException;

/**
 * @deprecated since 1.1.0; to remove in 2.0.0. This exception is currently
 *     thrown by `Zend\Expressive\Container\ApplicationFactory`; starting
 *     in 2.0.0, that factory will instead throw
 *     `Zend\Expressive\Exception\InvalidArgumentException`.
 */
class InvalidArgumentException extends \InvalidArgumentException implements
    ContainerException,
    ExceptionInterface
{
}
