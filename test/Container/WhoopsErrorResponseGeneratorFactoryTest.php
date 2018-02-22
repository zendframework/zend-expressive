<?php
/**
 * @see       https://github.com/zendframework/zend-expressive for the canonical source repository
 * @copyright Copyright (c) 2016-2017 Zend Technologies USA Inc. (https://www.zend.com)
 * @license   https://github.com/zendframework/zend-expressive/blob/master/LICENSE.md New BSD License
 */

declare(strict_types=1);

namespace ZendTest\Expressive\Container;

use PHPUnit\Framework\TestCase;
use Prophecy\Prophecy\ObjectProphecy;
use Psr\Container\ContainerInterface;
use Whoops\Run;
use Whoops\RunInterface;
use Zend\Expressive\Container\WhoopsErrorResponseGeneratorFactory;
use Zend\Expressive\Middleware\WhoopsErrorResponseGenerator;

use function interface_exists;

class WhoopsErrorResponseGeneratorFactoryTest extends TestCase
{
    /** @var ContainerInterface|ObjectProphecy */
    private $container;

    /** @var Run|RunInterface|ObjectProphecy */
    private $whoops;

    protected function setUp()
    {
        $this->container = $this->prophesize(ContainerInterface::class);

        $this->whoops = interface_exists(RunInterface::class)
            ? $this->prophesize(RunInterface::class)
            : $this->prophesize(Run::class);
    }

    public function testCreatesInstanceWithConfiguredWhoopsService()
    {
        $this->container->get('Zend\Expressive\Whoops')->will([$this->whoops, 'reveal']);

        $factory = new WhoopsErrorResponseGeneratorFactory();

        $generator = $factory($this->container->reveal());

        $this->assertInstanceOf(WhoopsErrorResponseGenerator::class, $generator);
        $this->assertAttributeSame($this->whoops->reveal(), 'whoops', $generator);
    }
}
