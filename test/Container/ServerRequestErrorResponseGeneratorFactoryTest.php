<?php
/**
 * @see       https://github.com/zendframework/zend-expressive for the canonical source repository
 * @copyright Copyright (c) 2018 Zend Technologies USA Inc. (https://www.zend.com)
 * @license   https://github.com/zendframework/zend-expressive/blob/master/LICENSE.md New BSD License
 */

declare(strict_types=1);

namespace ZendTest\Expressive\Container;

use Exception;
use PHPUnit\Framework\Assert;
use PHPUnit\Framework\TestCase;
use Psr\Container\ContainerInterface;
use Throwable;
use Zend\Diactoros\Response;
use Zend\Diactoros\ServerRequest;
use Zend\Expressive\Container\ServerRequestErrorResponseGeneratorFactory;
use Zend\Expressive\Middleware\ErrorResponseGenerator;

use function array_shift;

class ServerRequestErrorResponseGeneratorFactoryTest extends TestCase
{
    public function testFactoryGeneratesCallable() : array
    {
        $container = $this->prophesize(ContainerInterface::class);
        $factory = new ServerRequestErrorResponseGeneratorFactory();

        $generator = $factory($container->reveal());

        $this->assertInternalType('callable', $generator);

        return [$generator, $container];
    }

    /**
     * @depends testFactoryGeneratesCallable
     */
    public function testGeneratedCallableWrapsErrorResponseGeneratorService(array $deps)
    {
        $generator = array_shift($deps);
        $container = array_shift($deps);

        $exception = new Exception();

        $proxiedGenerator = function (Throwable $e, ServerRequest $request, Response $response) use ($exception) {
            Assert::assertSame($exception, $e);
            return $response;
        };

        $container->get(ErrorResponseGenerator::class)->willReturn($proxiedGenerator);

        $this->assertInstanceOf(Response::class, $generator($exception));
    }
}
