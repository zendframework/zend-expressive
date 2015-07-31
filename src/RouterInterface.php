<?php
namespace Zend\Expressive;

use Zend\Stratigility\Dispatch\Router\RouterInterface as BaseRouterInterface;

interface RouterInterface extends BaseRouterInterface
{
    /**
     * @var Route[] $routes
     */
    public function injectRoutes(array $routes);
}
