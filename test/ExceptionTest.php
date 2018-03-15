<?php
/**
 * @see       https://github.com/zendframework/zend-expressive for the canonical source repository
 * @copyright Copyright (c) 2018 Zend Technologies USA Inc. (https://www.zend.com)
 * @license   https://github.com/zendframework/zend-expressive/blob/master/LICENSE.md New BSD License
 */

declare(strict_types=1);

namespace ZendTest\Expressive;

use Generator;
use PHPUnit\Framework\TestCase;
use Psr\Container\ContainerExceptionInterface;
use Zend\Expressive\Exception\ExceptionInterface;
use Zend\Expressive\Exception\InvalidMiddlewareException;
use Zend\Expressive\Exception\MissingDependencyException;

use function basename;
use function glob;
use function is_a;
use function strrpos;
use function substr;

class ExceptionTest extends TestCase
{
    public function exception() : Generator
    {
        $namespace = substr(ExceptionInterface::class, 0, strrpos(ExceptionInterface::class, '\\') + 1);

        $exceptions = glob(__DIR__ . '/../src/Exception/*.php');
        foreach ($exceptions as $exception) {
            $class = substr(basename($exception), 0, -4);

            yield $class => [$namespace . $class];
        }
    }

    /**
     * @dataProvider exception
     */
    public function testExceptionIsInstanceOfExceptionInterface(string $exception) : void
    {
        $this->assertContains('Exception', $exception);
        $this->assertTrue(is_a($exception, ExceptionInterface::class, true));
    }

    public function containerException() : Generator
    {
        yield InvalidMiddlewareException::class => [InvalidMiddlewareException::class];
        yield MissingDependencyException::class => [MissingDependencyException::class];
    }

    /**
     * @dataProvider containerException
     */
    public function testExceptionIsInstanceOfContainerExceptionInterface(string $exception) : void
    {
        $this->assertTrue(is_a($exception, ContainerExceptionInterface::class, true));
    }
}
