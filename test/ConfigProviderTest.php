<?php
/**
 * @see       https://github.com/zendframework/zend-expressive for the canonical source repository
 * @copyright Copyright (c) 2018 Zend Technologies USA Inc. (https://www.zend.com)
 * @license   https://github.com/zendframework/zend-expressive/blob/master/LICENSE.md New BSD License
 */

declare(strict_types=1);

namespace ZendTest\Expressive;

use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseInterface;
use Zend\Diactoros\Response\EmitterInterface;
use Zend\Expressive\Application;
use Zend\Expressive\ApplicationPipeline;
use Zend\Expressive\ConfigProvider;
use Zend\Expressive\Handler;
use Zend\Expressive\MiddlewareContainer;
use Zend\Expressive\MiddlewareFactory;
use Zend\Expressive\Middleware;
use Zend\Expressive\Router\DispatchMiddleware;
use Zend\Expressive\Router\PathBasedRoutingMiddleware;
use Zend\Expressive\ServerRequestErrorResponseGenerator;
use Zend\Expressive\ServerRequestFactory;
use Zend\Stratigility\Middleware\ErrorHandler;
use Zend\Stratigility\Middleware\ErrorResponseGenerator;
use Zend\HttpHandlerRunner\RequestHandlerRunner;

class ConfigProviderTest extends TestCase
{
    public function setUp()
    {
        $this->provider = new ConfigProvider();
    }

    public function testProviderDefinesExpectedAliases()
    {
        $config = $this->provider->getDependencies();
        $aliases = $config['aliases'];
        $this->assertArrayHasKey(Handler\DefaultHandler::class, $aliases);
        $this->assertArrayHasKey(Middleware\DispatchMiddleware::class, $aliases);
        $this->assertArrayHasKey(Middleware\RouteMiddleware::class, $aliases);
    }

    public function testProviderDefinesExpectedFactoryServices()
    {
        $config = $this->provider->getDependencies();
        $factories = $config['factories'];

        $this->assertArrayHasKey(Application::class, $factories);
        $this->assertArrayHasKey(ApplicationPipeline::class, $factories);
        $this->assertArrayHasKey(EmitterInterface::class, $factories);
        $this->assertArrayHasKey(ErrorHandler::class, $factories);
        $this->assertArrayHasKey(ErrorResponseGenerator::class, $factories);
        $this->assertArrayHasKey(Handler\NotFoundHandler::class, $factories);
        $this->assertArrayHasKey(MiddlewareContainer::class, $factories);
        $this->assertArrayHasKey(MiddlewareFactory::class, $factories);
        $this->assertArrayHasKey(DispatchMiddleware::class, $factories);
        $this->assertArrayHasKey(Middleware\NotFoundMiddleware::class, $factories);
        $this->assertArrayHasKey(PathBasedRoutingMiddleware::class, $factories);
        $this->assertArrayHasKey(RequestHandlerRunner::class, $factories);
        $this->assertArrayHasKey(ResponseInterface::class, $factories);
        $this->assertArrayHasKey(ServerRequestErrorResponseGenerator::class, $factories);
        $this->assertArrayHasKey(ServerRequestFactory::class, $factories);
    }

    public function testResponseIsMarkedAsUnshared()
    {
        $config = $this->provider->getDependencies();
        $shared = $config['shared'];

        $this->assertArrayHasKey(ResponseInterface::class, $shared);
        $this->assertFalse($shared[ResponseInterface::class]);
    }

    public function testInvocationReturnsArrayWithDependencies()
    {
        $config = ($this->provider)();
        $this->assertInternalType('array', $config);
        $this->assertArrayHasKey('dependencies', $config);
        $this->assertArrayHasKey('aliases', $config['dependencies']);
        $this->assertArrayHasKey('factories', $config['dependencies']);
        $this->assertArrayHasKey('shared', $config['dependencies']);
    }
}
