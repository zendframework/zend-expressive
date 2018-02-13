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
use Zend\Expressive\Container\StreamFactory;

class StreamFactoryTest extends TestCase
{
    public function testFactoryProducesAStreamWhenDiactorosIsInstalled()
    {
        $container = $this->prophesize(ContainerInterface::class)->reveal();
        $factory = new StreamFactory();

        $response = $factory($container);

        $this->assertInstanceOf(Stream::class, $response);
    }
}
