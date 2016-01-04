<?php
/**
 * Zend Framework (http://framework.zend.com/)
 *
 * @see       https://github.com/zendframework/zend-expressive for the canonical source repository
 * @copyright Copyright (c) 2015-2016 Zend Technologies USA Inc. (http://www.zend.com)
 * @license   https://github.com/zendframework/zend-expressive/blob/master/LICENSE.md New BSD License
 */

namespace ZendTest\Expressive\Emitter;

use InvalidArgumentException;
use PHPUnit_Framework_TestCase as TestCase;
use Prophecy\Argument;
use Psr\Http\Message\ResponseInterface;
use SplStack;
use Zend\Diactoros\Response\EmitterInterface;
use Zend\Expressive\Emitter\EmitterStack;

/**
 * @covers Zend\Expressive\Emitter\EmitterStack
 */
class EmitterStackTest extends TestCase
{
    public function setUp()
    {
        $this->emitter = new EmitterStack();
    }

    public function testIsAnSplStack()
    {
        $this->assertInstanceOf(SplStack::class, $this->emitter);
    }

    public function testIsAnEmitterImplementation()
    {
        $this->assertInstanceOf(EmitterInterface::class, $this->emitter);
    }

    public function nonEmitterValues()
    {
        return [
            'null'       => [null],
            'true'       => [true],
            'false'      => [false],
            'zero'       => [0],
            'int'        => [1],
            'zero-float' => [0.0],
            'float'      => [1.1],
            'string'     => ['emitter'],
            'array'      => [[$this->prophesize(EmitterInterface::class)->reveal()]],
            'object'     => [(object)[]],
        ];
    }

    /**
     * @dataProvider nonEmitterValues
     */
    public function testCannotPushNonEmitterToStack($value)
    {
        $this->setExpectedException(InvalidArgumentException::class);
        $this->emitter->push($value);
    }

    /**
     * @dataProvider nonEmitterValues
     */
    public function testCannotUnshiftNonEmitterToStack($value)
    {
        $this->setExpectedException(InvalidArgumentException::class);
        $this->emitter->unshift($value);
    }

    /**
     * @dataProvider nonEmitterValues
     */
    public function testCannotSetNonEmitterToSpecificIndex($value)
    {
        $this->setExpectedException(InvalidArgumentException::class);
        $this->emitter->offsetSet(0, $value);
    }

    public function testEmitLoopsThroughEmittersUntilOneReturnsNonFalseValue()
    {
        $first = $this->prophesize(EmitterInterface::class);
        $first->emit()->shouldNotBeCalled();

        $second = $this->prophesize(EmitterInterface::class);
        $second->emit(Argument::type(ResponseInterface::class))
            ->willReturn(null);

        $third = $this->prophesize(EmitterInterface::class);
        $third->emit(Argument::type(ResponseInterface::class))
            ->willReturn(false);

        $this->emitter->push($first->reveal());
        $this->emitter->push($second->reveal());
        $this->emitter->push($third->reveal());

        $response = $this->prophesize(ResponseInterface::class);

        $this->assertNull($this->emitter->emit($response->reveal()));
    }
}
