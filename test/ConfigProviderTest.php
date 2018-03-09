<?php
/**
 * @see       https://github.com/zendframework/zend-expressive for the canonical source repository
 * @copyright Copyright (c) 2018 Zend Technologies USA Inc. (https://www.zend.com)
 * @license   https://github.com/zendframework/zend-expressive/blob/master/LICENSE.md New BSD License
 */

namespace ZendTest\Expressive;

use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\StreamInterface;
use Zend\Expressive\Application;
use Zend\Expressive\ConfigProvider;
use Zend\Expressive\Delegate\NotFoundDelegate;
use Zend\Expressive\Handler\NotFoundHandler;
use Zend\Expressive\Middleware;
use Zend\Expressive\Router\Middleware as RouterMiddleware;
use Zend\Expressive\Router\RouterInterface;
use Zend\ServiceManager\Config;
use Zend\ServiceManager\ServiceManager;
use Zend\Stratigility\Middleware\ErrorHandler;

class ConfigProviderTest extends TestCase
{
    /** @var ConfigProvider */
    private $provider;

    public function setUp()
    {
        $this->provider = new ConfigProvider();
    }

    public function testProviderDefinesExpectedAliases()
    {
        $config = $this->provider->getDependencies();
        $aliases = $config['aliases'];
        $this->assertArrayHasKey(Middleware\DispatchMiddleware::class, $aliases);
        $this->assertArrayHasKey(Middleware\ImplicitHeadMiddleware::class, $aliases);
        $this->assertArrayHasKey(Middleware\ImplicitOptionsMiddleware::class, $aliases);
        $this->assertArrayHasKey(Middleware\RouteMiddleware::class, $aliases);
        $this->assertArrayHasKey(NotFoundDelegate::class, $aliases);
        $this->assertArrayHasKey('Zend\Expressive\Delegate\DefaultDelegate', $aliases);
    }

    public function testProviderDefinesExpectedInvokableServices()
    {
        $config = $this->provider->getDependencies();
        $invokables = $config['invokables'];
        $this->assertArrayHasKey(RouterMiddleware\DispatchMiddleware::class, $invokables);
    }

    public function testProviderDefinesExpectedFactoryServices()
    {
        $config = $this->provider->getDependencies();
        $factories = $config['factories'];

        $this->assertArrayHasKey(Application::class, $factories);
        $this->assertArrayHasKey(ErrorHandler::class, $factories);
        $this->assertArrayHasKey(Middleware\ErrorResponseGenerator::class, $factories);
        $this->assertArrayHasKey(Middleware\NotFoundHandler::class, $factories);
        $this->assertArrayHasKey(NotFoundHandler::class, $factories);
        $this->assertArrayHasKey(ResponseInterface::class, $factories);
        $this->assertArrayHasKey(StreamInterface::class, $factories);
        $this->assertArrayHasKey(RouterMiddleware\ImplicitHeadMiddleware::class, $factories);
        $this->assertArrayHasKey(RouterMiddleware\ImplicitOptionsMiddleware::class, $factories);
        $this->assertArrayHasKey(RouterMiddleware\RouteMiddleware::class, $factories);
    }

    public function testInvocationReturnsArrayWithDependencies()
    {
        $config = ($this->provider)();
        $this->assertInternalType('array', $config);
        $this->assertArrayHasKey('dependencies', $config);
        $this->assertArrayHasKey('aliases', $config['dependencies']);
        $this->assertArrayHasKey('invokables', $config['dependencies']);
        $this->assertArrayHasKey('factories', $config['dependencies']);
    }
}
