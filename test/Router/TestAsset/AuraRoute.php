<?php
namespace ZendTest\Expressive\Router\TestAsset;

/**
 * Mock/stub/spy to use as a substitute for Aura.Route.
 *
 * Used for match results
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
