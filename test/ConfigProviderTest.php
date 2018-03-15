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
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\StreamInterface;
use Zend\Expressive\Application;
use Zend\Expressive\ApplicationPipeline;
use Zend\Expressive\ConfigProvider;
use Zend\Expressive\Handler\NotFoundHandler;
use Zend\Expressive\Middleware;
use Zend\Expressive\MiddlewareContainer;
use Zend\Expressive\MiddlewareFactory;
use Zend\Expressive\Response\ServerRequestErrorResponseGenerator;
use Zend\Expressive\Router\RouterInterface;
use Zend\HttpHandlerRunner\Emitter\EmitterInterface;
use Zend\HttpHandlerRunner\RequestHandlerRunner;
use Zend\ServiceManager\Config;
use Zend\ServiceManager\ServiceManager;
use Zend\Stratigility\Middleware\ErrorHandler;

use function array_merge_recursive;
use function file_get_contents;
use function json_decode;
use function sprintf;

use const Zend\Expressive\DEFAULT_DELEGATE;
use const Zend\Expressive\DISPATCH_MIDDLEWARE;
use const Zend\Expressive\IMPLICIT_HEAD_MIDDLEWARE;
use const Zend\Expressive\IMPLICIT_OPTIONS_MIDDLEWARE;
use const Zend\Expressive\NOT_FOUND_MIDDLEWARE;
use const Zend\Expressive\ROUTE_MIDDLEWARE;

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
        $this->assertArrayHasKey(IMPLICIT_HEAD_MIDDLEWARE, $aliases);
        $this->assertArrayHasKey(IMPLICIT_OPTIONS_MIDDLEWARE, $aliases);
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
        $this->assertArrayHasKey(MiddlewareContainer::class, $factories);
        $this->assertArrayHasKey(MiddlewareFactory::class, $factories);
        $this->assertArrayHasKey(Middleware\ErrorResponseGenerator::class, $factories);
        $this->assertArrayHasKey(NotFoundHandler::class, $factories);
        $this->assertArrayHasKey(RequestHandlerRunner::class, $factories);
        $this->assertArrayHasKey(ResponseInterface::class, $factories);
        $this->assertArrayHasKey(ServerRequestInterface::class, $factories);
        $this->assertArrayHasKey(ServerRequestErrorResponseGenerator::class, $factories);
        $this->assertArrayHasKey(StreamInterface::class, $factories);
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
        $config = ($this->provider)();

        $json = json_decode(
            file_get_contents(__DIR__ . '/../composer.lock'),
            true
        );
        foreach ($json['packages'] as $package) {
            if (isset($package['extra']['zf']['config-provider'])) {
                $configProvider = new $package['extra']['zf']['config-provider']();
                $config = array_merge_recursive($config, $configProvider());
            }
        }

        $routerInterface = $this->prophesize(RouterInterface::class)->reveal();
        $config['dependencies']['services'][RouterInterface::class] = $routerInterface;
        $container = $this->getContainer($config['dependencies']);

        $dependencies = $this->provider->getDependencies();
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

    private function getContainer(array $dependencies) : ServiceManager
    {
        $container = new ServiceManager();
        (new Config($dependencies))->configureServiceManager($container);

        return $container;
    }
}
