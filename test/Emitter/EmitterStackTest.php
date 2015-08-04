<?php
namespace ZendTest\Expressive\Emitter;

use PHPUnit_Framework_TestCase as TestCase;
use Prophecy\Argument;
use Zend\Expressive\Emitter\EmitterStack;

class EmitterStackTest extends TestCase
{
    public function setUp()
    {
        $this->emitter = new EmitterStack();
    }

    public function testIsAnSplStack()
    {
        $this->assertInstanceOf('SplStack', $this->emitter);
    }

    public function testIsAnEmitterImplementation()
    {
        $this->assertInstanceOf('Zend\Diactoros\Response\EmitterInterface', $this->emitter);
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
            'array'      => [[$this->prophesize('Zend\Diactoros\Response\EmitterInterface')->reveal()]],
            'object'     => [(object)[]],
        ];
    }

    /**
     * @dataProvider nonEmitterValues
     */
    public function testCannotPushNonEmitterToStack($value)
    {
        $this->setExpectedException('InvalidArgumentException');
        $this->emitter->push($value);
    }

    /**
     * @dataProvider nonEmitterValues
     */
    public function testCannotUnshiftNonEmitterToStack($value)
    {
        $this->setExpectedException('InvalidArgumentException');
        $this->emitter->unshift($value);
    }

    /**
     * @dataProvider nonEmitterValues
     */
    public function testCannotSetNonEmitterToSpecificIndex($value)
    {
        $this->setExpectedException('InvalidArgumentException');
        $this->emitter->offsetSet(0, $value);
    }

    public function testEmitLoopsThroughEmittersUntilOneReturnsNonFalseValue()
    {
        $first = $this->prophesize('Zend\Diactoros\Response\EmitterInterface');
        $first->emit()->shouldNotBeCalled();

        $second = $this->prophesize('Zend\Diactoros\Response\EmitterInterface');
        $second->emit(Argument::type('Psr\Http\Message\ResponseInterface'))
            ->willReturn(null);

        $third = $this->prophesize('Zend\Diactoros\Response\EmitterInterface');
        $third->emit(Argument::type('Psr\Http\Message\ResponseInterface'))
            ->willReturn(false);

        $this->emitter->push($first->reveal());
        $this->emitter->push($second->reveal());
        $this->emitter->push($third->reveal());

        $response = $this->prophesize('Psr\Http\Message\ResponseInterface');

        $this->assertNull($this->emitter->emit($response->reveal()));
    }
}
