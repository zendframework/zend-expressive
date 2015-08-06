<?php
/**
 * Zend Framework (http://framework.zend.com/)
 *
 * @see       https://github.com/zendframework/zend-expressive for the canonical source repository
 * @copyright Copyright (c) 2015 Zend Technologies USA Inc. (http://www.zend.com)
 * @license   https://github.com/zendframework/zend-expressive/blob/master/LICENSE.md New BSD License
 */

namespace ZendTest\Expressive\Router\TestAsset;

/**
 * Mock/stub/spy to use as a substitute for Aura.Route.
 *
 * Used for match results.
 */
class AuraRoute
{
    public $name;
    public $method;
    public $params;

    public function failedMethod()
    {
        return (null !== $this->method);
    }
}
