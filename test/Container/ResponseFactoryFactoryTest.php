<?php
/**
 * @see       https://github.com/zendframework/zend-expressive for the canonical source repository
 * @copyright Copyright (c) 2018 Zend Technologies USA Inc. (https://www.zend.com)
 * @license   https://github.com/zendframework/zend-expressive/blob/master/LICENSE.md New BSD License
 */

namespace ZendTest\Expressive\Container;

use PHPUnit\Framework\TestCase;
use Psr\Container\ContainerInterface;
use Zend\Diactoros\Response;
use Zend\Expressive\Container\ResponseFactoryFactory;

class ResponseFactoryFactoryTest extends TestCase
{
    public function testFactoryProducesACallableCapableOfGeneratingAResponseWhenDiactorosIsInstalled()
    {
        $container = $this->prophesize(ContainerInterface::class)->reveal();
        $factory = new ResponseFactoryFactory();

        $result = $factory($container);

        $this->assertInternalType('callable', $result);

        $response = $result();
        $this->assertInstanceOf(Response::class, $response);
    }
}
