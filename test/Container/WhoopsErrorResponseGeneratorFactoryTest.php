<?php
/**
 * @link      http://github.com/zendframework/zend-expressive for the canonical source repository
 * @copyright Copyright (c) 2016 Zend Technologies USA Inc. (http://www.zend.com)
 * @license   http://framework.zend.com/license/new-bsd New BSD License
 */

namespace ZendTest\Expressive\Container;

use Interop\Container\ContainerInterface;
use PHPUnit_Framework_TestCase as TestCase;
use Whoops\Run;
use Whoops\RunInterface;
use Zend\Expressive\Container\WhoopsErrorResponseGeneratorFactory;
use Zend\Expressive\Middleware\WhoopsErrorResponseGenerator;

class WhoopsErrorResponseGeneratorFactoryTest extends TestCase
{
    public function setUp()
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
