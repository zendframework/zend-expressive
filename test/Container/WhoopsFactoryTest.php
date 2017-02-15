<?php
/**
 * @see       https://github.com/zendframework/zend-expressive for the canonical source repository
 * @copyright Copyright (c) 2015-2017 Zend Technologies USA Inc. (http://www.zend.com)
 * @license   https://github.com/zendframework/zend-expressive/blob/master/LICENSE.md New BSD License
 */

namespace ZendTest\Expressive\Container;

use PHPUnit\Framework\TestCase;
use Prophecy\Prophecy\ObjectProphecy;
use Psr\Container\ContainerInterface;
use ReflectionProperty;
use Traversable;
use Whoops\Handler\JsonResponseHandler;
use Whoops\Handler\PrettyPageHandler;
use Whoops\Run as Whoops;
use Whoops\Util\Misc as WhoopsUtil;
use Zend\Expressive\Container\WhoopsFactory;
use ZendTest\Expressive\ContainerTrait;

/**
 * @covers Zend\Expressive\Container\WhoopsFactory
 */
class WhoopsFactoryTest extends TestCase
{
    use ContainerTrait;

    /** @var ContainerInterface|ObjectProphecy */
    private $container;

    /** @var WhoopsFactory */
    private $factory;

    public function setUp()
    {
        $pageHandler     = $this->prophesize(PrettyPageHandler::class);
        $this->container = $this->mockContainerInterface();
        $this->injectServiceInContainer($this->container, 'Zend\Expressive\WhoopsPageHandler', $pageHandler->reveal());

        $this->factory = new WhoopsFactory();
    }

    public function assertWhoopsContainsHandler($type, Whoops $whoops, $message = null)
    {
        $message = $message ?: sprintf('Failed to assert whoops runtime composed handler of type %s', $type);
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
     * @backupGlobals enabled
     * @depends       testWillInjectJsonResponseHandlerIfConfigurationExpectsIt
     * @dataProvider  provideConfig
     *
     * @param bool  $showsTrace
     * @param bool  $isAjaxOnly
     * @param bool  $requestIsAjax
     */
    public function testJsonResponseHandlerCanBeConfigured($showsTrace, $isAjaxOnly, $requestIsAjax)
    {
        // Set for Whoops 2.x json handler detection
        if ($requestIsAjax) {
            $_SERVER['HTTP_X_REQUESTED_WITH'] = 'xmlhttprequest';
        }

        $config = [
            'whoops' => [
                'json_exceptions' => [
                    'display'    => true,
                    'show_trace' => $showsTrace,
                    'ajax_only'  => $isAjaxOnly,
                ],
            ],
        ];

        $this->injectServiceInContainer($this->container, 'config', $config);

        $factory = $this->factory;
        $whoops  = $factory($this->container->reveal());
        $handler = $whoops->popHandler();

        // If ajax only, not ajax request and Whoops 2, it does not inject JsonResponseHandler
        if ($isAjaxOnly
            && ! $requestIsAjax
            && method_exists(WhoopsUtil::class, 'isAjaxRequest')
        ) {
            $this->assertInstanceOf(PrettyPageHandler::class, $handler);

            // Skip remaining assertions
            return;
        }

        $this->assertAttributeSame($showsTrace, 'returnFrames', $handler);

        if (method_exists($handler, 'onlyForAjaxRequests')) {
            $this->assertAttributeSame($isAjaxOnly, 'onlyForAjaxRequests', $handler);
        }
    }

    /**
     * @return Traversable
     */
    public function provideConfig()
    {
        // @codingStandardsIgnoreStart
        //    test case                        => showsTrace, isAjaxOnly, requestIsAjax
        yield 'Shows trace'                    => [true,      true,       true];
        yield 'Does not show trace'            => [false,     true,       true];

        yield 'Ajax only, request is ajax'     => [true,      true,       true];
        yield 'Ajax only, request is not ajax' => [true,      true,       false];

        yield 'Not ajax only'                  => [true,      false,      false];
        // @codingStandardsIgnoreEnd
    }
}
