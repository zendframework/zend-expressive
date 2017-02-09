<?php
/**
 * @see       https://github.com/zendframework/zend-expressive for the canonical source repository
 * @copyright Copyright (c) 2015-2017 Zend Technologies USA Inc. (http://www.zend.com)
 * @license   https://github.com/zendframework/zend-expressive/blob/master/LICENSE.md New BSD License
 */

namespace ZendTest\Expressive\Container;

use PHPUnit\Framework\TestCase;
use Prophecy\Prophecy\ObjectProphecy;
use ReflectionProperty;
use Whoops\Handler\JsonResponseHandler;
use Whoops\Handler\PrettyPageHandler;
use Whoops\Run as Whoops;
use Zend\Expressive\Container\WhoopsFactory;
use ZendTest\Expressive\ContainerTrait;

/**
 * @covers Zend\Expressive\Container\WhoopsFactory
 */
class WhoopsFactoryTest extends TestCase
{
    use ContainerTrait;

    /** @var ObjectProphecy */
    protected $container;

    public function setUp()
    {
        $pageHandler     = $this->prophesize(PrettyPageHandler::class);
        $this->container = $this->mockContainerInterface();
        $this->injectServiceInContainer($this->container, 'Zend\Expressive\WhoopsPageHandler', $pageHandler->reveal());

        $this->factory = new WhoopsFactory();
    }

    public function assertWhoopsContainsHandler($type, Whoops $whoops, $message = null)
    {
        $message = $message ?: sprintf("Failed to assert whoops runtime composed handler of type %s", $type);
        $r       = new ReflectionProperty($whoops, 'handlerStack');
        $r->setAccessible(true);
        $stack = $r->getValue($whoops);

        $found = false;
        foreach ($stack as $handler) {
            if ($handler instanceof $type) {
                $found = true;
                break;
            }
        }

        $this->assertTrue($found, $message);
    }

    public function testReturnsAWhoopsRuntimeWithPageHandlerComposed()
    {
        $factory = $this->factory;
        $result  = $factory($this->container->reveal());
        $this->assertInstanceOf(Whoops::class, $result);
        $this->assertWhoopsContainsHandler(PrettyPageHandler::class, $result);
    }

    public function testWillInjectJsonResponseHandlerIfConfigurationExpectsIt()
    {
        $config = ['whoops' => ['json_exceptions' => ['display' => true]]];
        $this->injectServiceInContainer($this->container, 'config', $config);

        $factory = $this->factory;
        $result  = $factory($this->container->reveal());
        $this->assertInstanceOf(Whoops::class, $result);
        $this->assertWhoopsContainsHandler(PrettyPageHandler::class, $result);
        $this->assertWhoopsContainsHandler(JsonResponseHandler::class, $result);
    }

    /**
     * @depends testWillInjectJsonResponseHandlerIfConfigurationExpectsIt
     */
    public function testJsonResponseHandlerCanBeConfigured()
    {
        // Set for Whoops 2.x json handler detection
        $_SERVER['HTTP_X_REQUESTED_WITH'] = 'xmlhttprequest';

        $config = [
            'whoops' => [
                'json_exceptions' => [
                    'display'    => true,
                    'show_trace' => true,
                    'ajax_only'  => true,
                ],
            ],
        ];
        $this->injectServiceInContainer($this->container, 'config', $config);

        $factory = $this->factory;
        $whoops  = $factory($this->container->reveal());

        $jsonHandler = $whoops->popHandler();
        $this->assertInstanceOf(JsonResponseHandler::class, $jsonHandler);
        $this->assertAttributeSame(true, 'returnFrames', $jsonHandler);

        if (method_exists($jsonHandler, 'onlyForAjaxRequests')) {
            $this->assertAttributeSame(true, 'onlyForAjaxRequests', $jsonHandler);
        }
    }
}
