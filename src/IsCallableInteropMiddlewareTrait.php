<?php
/**
 * @see       https://github.com/zendframework/zend-expressive for the canonical source repository
 * @copyright Copyright (c) 2017 Zend Technologies USA Inc. (http://www.zend.com)
 * @license   https://github.com/zendframework/zend-expressive/blob/master/LICENSE.md New BSD License
 */
declare(strict_types=1);

namespace Zend\Expressive;

use Closure;
use ReflectionFunction;
use ReflectionFunctionAbstract;
use ReflectionMethod;

trait IsCallableInteropMiddlewareTrait
{
    /**
     * Is callable middleware interop middleware?
     *
     * @param mixed $middleware
     */
    private function isCallableInteropMiddleware($middleware) : bool
    {
        if (! is_callable($middleware)) {
            return false;
        }

        $r = $this->reflectMiddleware($middleware);
        $paramsCount = $r->getNumberOfParameters();

        return $paramsCount === 2;
    }

    /**
     * Reflect a callable middleware.
     *
     * Duplicates MiddlewarePipe::getReflectionFunction, but that method is not
     * callable due to private visibility.
     */
    private function reflectMiddleware(callable $middleware) : ReflectionFunctionAbstract
    {
        if (is_array($middleware)) {
            $class = array_shift($middleware);
            $method = array_shift($middleware);
            return new ReflectionMethod($class, $method);
        }

        if ($middleware instanceof Closure || ! is_object($middleware)) {
            return new ReflectionFunction($middleware);
        }

        return new ReflectionMethod($middleware, '__invoke');
    }
}
