<?php
/**
 * @see       https://github.com/zendframework/zend-expressive for the canonical source repository
 * @copyright Copyright (c) 2018 Zend Technologies USA Inc. (https://www.zend.com)
 * @license   https://github.com/zendframework/zend-expressive/blob/master/LICENSE.md New BSD License
 */

declare(strict_types=1);

namespace ZendTest\Expressive;

use PHPUnit\Framework\TestCase;
use Zend\Expressive\Application;
use Zend\Expressive\ApplicationPipeline;
use Zend\Expressive\ConfigProvider;
use Zend\Expressive\Delegate\DefaultDelegate;
use Zend\Expressive\Handler\NotFoundHandler;
use Zend\Expressive\Middleware;
use Zend\Expressive\MiddlewareContainer;
use Zend\Expressive\MiddlewareFactory;
use Zend\Expressive\Router\ConfigProvider as RouterConfigProvider;
use Zend\Expressive\Router\RouterInterface;
use Zend\Expressive\ServerRequestErrorResponseGenerator;
use Zend\Expressive\ServerRequestFactory;
use Zend\HttpHandlerRunner\Emitter\EmitterInterface;
use Zend\HttpHandlerRunner\RequestHandlerRunner;
use Zend\ServiceManager\ServiceManager;
use Zend\Stratigility\Middleware\ErrorHandler;

use const Zend\Expressive\DEFAULT_DELEGATE;
use const Zend\Expressive\DISPATCH_MIDDLEWARE;
use const Zend\Expressive\NOT_FOUND_MIDDLEWARE;
use const Zend\Expressive\NOT_FOUND_RESPONSE;
use const Zend\Expressive\ROUTE_MIDDLEWARE;
use const Zend\Expressive\SERVER_REQUEST_ERROR_RESPONSE_GENERATOR;
use const Zend\Expressive\SERVER_REQUEST_FACTORY;
use const Zend\Expressive\Router\IMPLICIT_HEAD_MIDDLEWARE_RESPONSE;
use const Zend\Expressive\Router\IMPLICIT_HEAD_MIDDLEWARE_STREAM_FACTORY;
use const Zend\Expressive\Router\IMPLICIT_OPTIONS_MIDDLEWARE_RESPONSE;
use const Zend\Expressive\Router\METHOD_NOT_ALLOWED_MIDDLEWARE_RESPONSE;

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
        $this->assertArrayHasKey(DEFAULT_DELEGATE, $aliases);
        $this->assertArrayHasKey(DISPATCH_MIDDLEWARE, $aliases);
        $this->assertArrayHasKey(NOT_FOUND_MIDDLEWARE, $aliases);
        $this->assertArrayHasKey(ROUTE_MIDDLEWARE, $aliases);
    }

    public function testProviderDefinesExpectedFactoryServices()
    {
        $config = $this->provider->getDependencies();
        $factories = $config['factories'];

        $this->assertArrayHasKey(Application::class, $factories);
        $this->assertArrayHasKey(ApplicationPipeline::class, $factories);
        $this->assertArrayHasKey(EmitterInterface::class, $factories);
        $this->assertArrayHasKey(ErrorHandler::class, $factories);
        $this->assertArrayHasKey(IMPLICIT_HEAD_MIDDLEWARE_RESPONSE, $factories);
        $this->assertArrayHasKey(IMPLICIT_HEAD_MIDDLEWARE_STREAM_FACTORY, $factories);
        $this->assertArrayHasKey(IMPLICIT_OPTIONS_MIDDLEWARE_RESPONSE, $factories);
        $this->assertArrayHasKey(METHOD_NOT_ALLOWED_MIDDLEWARE_RESPONSE, $factories);
        $this->assertArrayHasKey(MiddlewareContainer::class, $factories);
        $this->assertArrayHasKey(MiddlewareFactory::class, $factories);
        $this->assertArrayHasKey(Middleware\ErrorResponseGenerator::class, $factories);
        $this->assertArrayHasKey(NotFoundHandler::class, $factories);
        $this->assertArrayHasKey(NOT_FOUND_RESPONSE, $factories);
        $this->assertArrayHasKey(RequestHandlerRunner::class, $factories);
        $this->assertArrayHasKey(SERVER_REQUEST_ERROR_RESPONSE_GENERATOR, $factories);
        $this->assertArrayHasKey(SERVER_REQUEST_FACTORY, $factories);
    }

    public function testInvocationReturnsArrayWithDependencies()
    {
        $config = ($this->provider)();
        $this->assertInternalType('array', $config);
        $this->assertArrayHasKey('dependencies', $config);
        $this->assertArrayHasKey('aliases', $config['dependencies']);
        $this->assertArrayHasKey('factories', $config['dependencies']);
    }

    public function testServicesDefinedInConfigProvider()
    {
        $routerConfigProvider = new RouterConfigProvider();
        $dependencies = $this->provider->getDependencies();

        $config = array_merge_recursive($dependencies, $routerConfigProvider->getDependencies());
        $config['services'][RouterInterface::class] = $this->prophesize(RouterInterface::class)->reveal();
        $container = new ServiceManager($config);

        foreach ($dependencies['factories'] as $name => $factory) {
            $this->assertTrue($container->has($name), sprintf('Container does not contain service %s', $name));
            $this->assertInternalType(
                'object',
                $container->get($name),
                sprintf('Cannot get service %s from container using factory %s', $name, $factory)
            );
        }

        foreach ($dependencies['aliases'] as $alias => $dependency) {
            $this->assertTrue(
                $container->has($alias),
                sprintf('Container does not contain service with alias %s', $alias)
            );
            $this->assertInternalType(
                'object',
                $container->get($alias),
                sprintf('Cannot get service %s using alias %s', $dependency, $alias)
            );
        }
    }
}
