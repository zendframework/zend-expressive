<?php
/**
 * @see       https://github.com/zendframework/zend-expressive for the canonical source repository
 * @copyright Copyright (c) 2015-2017 Zend Technologies USA Inc. (https://www.zend.com)
 * @license   https://github.com/zendframework/zend-expressive/blob/master/LICENSE.md New BSD License
 */

declare(strict_types=1);

namespace Zend\Expressive\Exception;

use Interop\Http\Server\MiddlewareInterface;
use RuntimeException;

class InvalidMiddlewareException extends RuntimeException implements ExceptionInterface
{
    /**
     * @var mixed $middleware The middleware that does not fulfill the
     *     expectations of MiddlewarePipe::pipe
     */
    public static function forMiddleware($middleware) : self
    {
        return new self(sprintf(
            'Middleware "%s" is neither a string service name nor a %s instance',
            is_object($middleware) ? get_class($middleware) : gettype($middleware),
            MiddlewareInterface::class
        ));
    }

    /**
     * @var mixed $service The actual service created by the container.
     */
    public static function forMiddlewareService(string $name, $service) : self
    {
        return new self(sprintf(
            'Service "%s" did not to resolve to a %s instance; resolved to "%s"',
            $name,
            MiddlewareInterface::class,
            is_object($service) ? get_class($service) : gettype($service)
        ));
    }
}
