<?php
/**
 * @see       https://github.com/zendframework/zend-expressive for the canonical source repository
 * @copyright Copyright (c) 2016-2017 Zend Technologies USA Inc. (https://www.zend.com)
 * @license   https://github.com/zendframework/zend-expressive/blob/master/LICENSE.md New BSD License
 */

declare(strict_types=1);

namespace Zend\Expressive\Exception;

use Psr\Container\ContainerExceptionInterface;
use RuntimeException;

use function sprintf;

class MissingDependencyException extends RuntimeException implements
    ContainerExceptionInterface,
    ExceptionInterface
{
    public static function forMiddlewareService(string $service) : self
    {
        return new self(sprintf(
            'Cannot fetch middleware service "%s"; service not registered,'
            . ' or does not resolve to an autoloadable class name',
            $service
        ));
    }
}
