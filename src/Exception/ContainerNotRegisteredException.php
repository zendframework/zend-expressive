<?php
/**
 * @see       https://github.com/zendframework/zend-expressive for the canonical source repository
 * @copyright Copyright (c) 2015-2017 Zend Technologies USA Inc. (https://www.zend.com)
 * @license   https://github.com/zendframework/zend-expressive/blob/master/LICENSE.md New BSD License
 */

declare(strict_types=1);

namespace Zend\Expressive\Exception;

use RuntimeException;

use function sprintf;

class ContainerNotRegisteredException extends RuntimeException implements ExceptionInterface
{
    public static function forMiddlewareService(string $middleware) : self
    {
        return new self(sprintf(
            'Cannot marshal middleware by service name "%s"; no container registered',
            $middleware
        ));
    }
}
