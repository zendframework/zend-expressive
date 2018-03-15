<?php
/**
 * @see       https://github.com/zendframework/zend-expressive for the canonical source repository
 * @copyright Copyright (c) 2018 Zend Technologies USA Inc. (https://www.zend.com)
 * @license   https://github.com/zendframework/zend-expressive/blob/master/LICENSE.md New BSD License
 */

declare(strict_types=1);

namespace ZendTest\Expressive\Container;

use PHPUnit\Framework\TestCase;
use Prophecy\Prophecy\ObjectProphecy;
use Psr\Container\ContainerInterface;
use Zend\Expressive\Container\Exception\InvalidServiceException;
use Zend\Expressive\Container\ResponseFactoryFactory;

use function class_exists;
use function spl_autoload_functions;
use function spl_autoload_register;
use function spl_autoload_unregister;

class ResponseFactoryFactoryWithoutDiactorosTest extends TestCase
{
    /** @var ContainerInterface|ObjectProphecy */
    private $container;

    /** @var ResponseFactoryFactory */
    private $factory;

    /** @var array */
    private $autoloadFunctions = [];

    protected function setUp()
    {
        class_exists(InvalidServiceException::class);

        $this->container = $this->prophesize(ContainerInterface::class)->reveal();
        $this->factory = new ResponseFactoryFactory();

        $this->autoloadFunctions = spl_autoload_functions();
        foreach ($this->autoloadFunctions as $autoloader) {
            spl_autoload_unregister($autoloader);
        }
    }

    private function reloadAutoloaders()
    {
        foreach ($this->autoloadFunctions as $autoloader) {
            spl_autoload_register($autoloader);
        }
    }

    public function testFactoryRaisesAnExceptionIfDiactorosIsNotLoaded()
    {
        $this->expectException(InvalidServiceException::class);
        $this->expectExceptionMessage('zendframework/zend-diactoros');

        try {
            ($this->factory)($this->container);
        } finally {
            $this->reloadAutoloaders();
        }
    }
}
