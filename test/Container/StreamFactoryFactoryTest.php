<?php
/**
 * @see       https://github.com/zendframework/zend-expressive for the canonical source repository
 * @copyright Copyright (c) 2018 Zend Technologies USA Inc. (https://www.zend.com)
 * @license   https://github.com/zendframework/zend-expressive/blob/master/LICENSE.md New BSD License
 */

declare(strict_types=1);

namespace ZendTest\Expressive\Container;

use PHPUnit\Framework\TestCase;
use Psr\Container\ContainerInterface;
use Zend\Diactoros\Stream;
use Zend\Expressive\Container\StreamFactoryFactory;

class StreamFactoryFactoryTest extends TestCase
{
    public function testFactoryProducesACallableCapableOfGeneratingAStreamWhenDiactorosIsInstalled()
    {
        $container = $this->prophesize(ContainerInterface::class)->reveal();
        $factory = new StreamFactoryFactory();

        $result = $factory($container);

        $this->assertInternalType('callable', $result);

        $stream = $result();
        $this->assertInstanceOf(Stream::class, $stream);
    }
}
