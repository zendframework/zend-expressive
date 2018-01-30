<?php
/**
 * @see       https://github.com/zendframework/zend-expressive for the canonical source repository
 * @copyright Copyright (c) 2018 Zend Technologies USA Inc. (https://www.zend.com)
 * @license   https://github.com/zendframework/zend-expressive/blob/master/LICENSE.md New BSD License
 */

declare(strict_types=1);

namespace Zend\Expressive\Container;

use PHPUnit\Framework\TestCase;
use Psr\Container\ContainerInterface;
use Zend\Diactoros\Response\SapiEmitter;
use Zend\Expressive\Container\EmitterFactory;
use Zend\Expressive\Emitter\EmitterStack;

class EmitterFactoryTest extends TestCase
{
    public function testFactoryProducesEmitterStackWithSapiEmitterComposed()
    {
        $container = $this->prophesize(ContainerInterface::class)->reveal();
        $factory = new EmitterFactory();

        $emitter = $factory($container);

        $this->assertInstanceOf(EmitterStack::class, $emitter);

        $emitters = iterator_to_array($emitter);
        $this->assertCount(1, $emitters);

        $emitter = array_shift($emitters);
        $this->assertInstanceOf(SapiEmitter::class, $emitter);
    }
}
