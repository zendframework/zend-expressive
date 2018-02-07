<?php
/**
 * @see       https://github.com/zendframework/zend-expressive for the canonical source repository
 * @copyright Copyright (c) 2016-2017 Zend Technologies USA Inc. (https://www.zend.com)
 * @license   https://github.com/zendframework/zend-expressive/blob/master/LICENSE.md New BSD License
 */

declare(strict_types=1);

namespace ZendTest\Expressive\Container;

use PHPUnit\Framework\TestCase;
use Prophecy\Prophecy\ObjectProphecy;
use Psr\Container\ContainerInterface;
use Psr\Http\Message\ResponseInterface;
use Zend\Expressive\Container\NotFoundMiddlewareFactory;
use Zend\Expressive\Middleware\NotFoundMiddleware;
use Zend\Expressive\NotFoundResponseInterface;
use Zend\Expressive\Template\TemplateRendererInterface;

class NotFoundMiddlewareFactoryTest extends TestCase
{
    /** @var ContainerInterface|ObjectProphecy */
    private $container;

    /** @var ResponseInterface|ObjectProphecy */
    private $response;

    protected function setUp()
    {
        $this->response = $this->prophesize(ResponseInterface::class)->reveal();
        $this->container = $this->prophesize(ContainerInterface::class);
        $this->container->get(NotFoundResponseInterface::class)->willReturn($this->response);
    }

    public function testFactoryCreatesInstanceWithoutRendererIfRendererServiceIsMissing()
    {
        $this->container->has('config')->willReturn(false);
        $this->container->has(TemplateRendererInterface::class)->willReturn(false);
        $factory = new NotFoundMiddlewareFactory();

        $middleware = $factory($this->container->reveal());
        $this->assertInstanceOf(NotFoundMiddleware::class, $middleware);
        $this->assertAttributeSame($this->response, 'responsePrototype', $middleware);
        $this->assertAttributeEmpty('renderer', $middleware);
    }

    public function testFactoryCreatesInstanceUsingRendererServiceWhenPresent()
    {
        $renderer = $this->prophesize(TemplateRendererInterface::class)->reveal();
        $this->container->has('config')->willReturn(false);
        $this->container->has(TemplateRendererInterface::class)->willReturn(true);
        $this->container->get(TemplateRendererInterface::class)->willReturn($renderer);
        $factory = new NotFoundMiddlewareFactory();

        $middleware = $factory($this->container->reveal());
        $this->assertAttributeSame($renderer, 'renderer', $middleware);
    }

    public function testFactoryUsesConfigured404TemplateWhenPresent()
    {
        $config = [
            'zend-expressive' => [
                'error_handler' => [
                    'layout' => 'layout::error',
                    'template_404' => 'foo::bar',
                ],
            ],
        ];
        $this->container->has('config')->willReturn(true);
        $this->container->get('config')->willReturn($config);
        $this->container->has(TemplateRendererInterface::class)->willReturn(false);
        $factory = new NotFoundMiddlewareFactory();

        $middleware = $factory($this->container->reveal());
        $this->assertAttributeEquals(
            $config['zend-expressive']['error_handler']['layout'],
            'layout',
            $middleware
        );
        $this->assertAttributeEquals(
            $config['zend-expressive']['error_handler']['template_404'],
            'template',
            $middleware
        );
    }
}
