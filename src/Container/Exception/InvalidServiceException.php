<?php
/**
 * @see       https://github.com/zendframework/zend-expressive for the canonical source repository
 * @copyright Copyright (c) 2015-2017 Zend Technologies USA Inc. (https://www.zend.com)
 * @license   https://github.com/zendframework/zend-expressive/blob/master/LICENSE.md New BSD License
 */

declare(strict_types=1);

namespace Zend\Expressive\Container\Exception;

use Psr\Container\ContainerExceptionInterface;
use RuntimeException;

/**
 * Exception indicating a service type is invalid or un-fetchable.
 */
class InvalidServiceException extends RuntimeException implements
    ContainerExceptionInterface,
    ExceptionInterface
{
}
