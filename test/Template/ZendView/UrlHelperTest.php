<?php
/**
 * Zend Framework (http://framework.zend.com/)
 *
 * @see       https://github.com/zendframework/zend-expressive for the canonical source repository
 * @copyright Copyright (c) 2015 Zend Technologies USA Inc. (http://www.zend.com)
 * @license   https://github.com/zendframework/zend-expressive/blob/master/LICENSE.md New BSD License
 */

namespace ZendTest\Expressive\Template\ZendView;

use ArrayObject;
use PHPUnit_Framework_TestCase as TestCase;
use Zend\Expressive\Exception;
use Zend\Expressive\Router\RouterInterface;
use Zend\Expressive\Router\RouteResult;
use Zend\Expressive\Template\ZendView\UrlHelper;

class UrlHelperTest extends TestCase
{
    public function setUp()
    {
        $this->router = $this->prophesize(RouterInterface::class);
    }

    public function createHelper()
    {
        return new UrlHelper($this->router->reveal());
    }

    public function testRaisesExceptionOnInvocationIfNoRouteProvidedAndNoResultPresent()
    {
        $helper = $this->createHelper();
        $this->setExpectedException(Exception\RenderingException::class, 'use matched result');
        $helper();
    }

    public function testRaisesExceptionOnInvocationIfNoRouteProvidedAndResultIndicatesFailure()
    {
        $result = $this->prophesize(RouteResult::class);
        $result->isFailure()->willReturn(true);
        $helper = $this->createHelper();
        $helper->setRouteResult($result->reveal());
        $this->setExpectedException(Exception\RenderingException::class, 'routing failed');
        $helper();
    }

    public function testRaisesExceptionOnInvocationIfRouterCannotGenerateUriForRouteProvided()
    {
        $this->router->generateUri('foo', [])->willThrow(Exception\RuntimeException::class);
        $helper = $this->createHelper();
        $this->setExpectedException(Exception\RuntimeException::class);
        $helper('foo');
    }

    public function testWhenNoRouteProvidedTheHelperUsesComposedResultToGenerateUrl()
    {
        $result = $this->prophesize(RouteResult::class);
        $result->isFailure()->willReturn(false);
        $result->getMatchedRouteName()->willReturn('foo');
        $result->getMatchedParams()->willReturn(['bar' => 'baz']);

        $this->router->generateUri('foo', ['bar' => 'baz'])->willReturn('URL');

        $helper = $this->createHelper();
        $helper->setRouteResult($result->reveal());

        $this->assertEquals('URL', $helper());
    }

    public function testWhenNoRouteProvidedTheHelperMergesPassedParametersWithResultParametersToGenerateUrl()
    {
        $result = $this->prophesize(RouteResult::class);
        $result->isFailure()->willReturn(false);
        $result->getMatchedRouteName()->willReturn('foo');
        $result->getMatchedParams()->willReturn(['bar' => 'baz']);

        $this->router->generateUri('foo', ['bar' => 'baz', 'baz' => 'bat'])->willReturn('URL');

        $helper = $this->createHelper();
        $helper->setRouteResult($result->reveal());

        $this->assertEquals('URL', $helper(null, ['baz' => 'bat']));
    }

    public function testWhenRouteProvidedTheHelperDelegatesToTheRouterToGenerateUrl()
    {
        $this->router->generateUri('foo', ['bar' => 'baz'])->willReturn('URL');
        $helper = $this->createHelper();
        $this->assertEquals('URL', $helper('foo', ['bar' => 'baz']));
    }
}
