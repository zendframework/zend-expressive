<?php
/**
 * Zend Framework (http://framework.zend.com/)
 *
 * @see       https://github.com/zendframework/zend-expressive for the canonical source repository
 * @copyright Copyright (c) 2015 Zend Technologies USA Inc. (http://www.zend.com)
 * @license   https://github.com/zendframework/zend-expressive/blob/master/LICENSE.md New BSD License
 */

namespace ZendTest\Expressive;

use Exception;
use PHPUnit_Framework_TestCase;
use Psr\Http\Message\ServerRequestInterface;
use Zend\Diactoros\Response;
use Zend\Expressive\FinalHandlerFactory;
use Zend\Stratigility\FinalHandler;

class FinalHandlerFactoryTest extends PHPUnit_Framework_TestCase
{
    use ContainerTrait;

    protected $factory;
    protected $container;

    protected function setUp()
    {
        $this->container = $this->mockContainerInterface();
        $this->factory = new FinalHandlerFactory();
    }

    public function testCreateWithoutConfig()
    {
        $result = $this->factory->__invoke($this->container->reveal());

        $this->assertInstanceOf(FinalHandler::class, $result);
    }

    public function testProductionConfig()
    {
        $config = [
            'final_handler' => [
                'options' => [
                    'env' => 'production',
                ],
            ],
        ];
        $this->injectServiceInContainer($this->container, 'config', $config);
        $result = $this->factory->__invoke($this->container->reveal());

        $this->assertInstanceOf(FinalHandler::class, $result);
        $request = $this->prophesize(ServerRequestInterface::class)->reveal();
        $response = $result->__invoke($request, new Response(), new Exception('boofoo'));

        $this->assertNotContains('boofoo', (string) $response->getBody());
    }

    public function testNonProductionConfig()
    {
        $config = [
            'final_handler' => [
                'options' => [
                    'env' => 'development',
                ],
            ],
        ];
        $this->injectServiceInContainer($this->container, 'config', $config);
        $result = $this->factory->__invoke($this->container->reveal());

        $this->assertInstanceOf(FinalHandler::class, $result);
        $request = $this->prophesize(ServerRequestInterface::class)->reveal();
        $response = $result->__invoke($request, new Response(), new Exception('boofoo'));

        $this->assertContains('boofoo', (string) $response->getBody());
    }
}
