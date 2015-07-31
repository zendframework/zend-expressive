<?php
/**
 * Zend Framework (http://framework.zend.com/)
 *
 * @see       http://github.com/zendframework/zend-stratigility for the canonical source repository
 * @copyright Copyright (c) 2015 Zend Technologies USA Inc. (http://www.zend.com)
 * @license   https://github.com/zendframework/zend-stratigility/blob/master/LICENSE.md New BSD License
 */

namespace ZendTest\Stratigility;

use PHPUnit_Framework_TestCase as TestCase;
use Zend\Expressive\App;
use Zend\Diactoros\ServerRequest as Request;
use Zend\Diactoros\Response as Response;
use Zend\Stratigility\MiddlewarePipe;

class AppTest extends TestCase
{
    public function setUp()
    {
        $this->app      = new App();
        $this->response = new Response();
    }

    public function testConstructor()
    {
        $this->assertTrue($this->app instanceof App);
    }

    public function testConstructorWithConfig()
    {
        $config = [ 'foo' => 'bar' ];
        $this->app = new App($config);
        $this->assertTrue($this->app instanceof App);
        $this->assertEquals($config, $this->app->getConfig());
    }

    public function testAddHttpMethods()
    {
        $methods = $this->app->getHttpMethods();
        $this->app->addHttpMethod('test');
        $this->assertEquals(array_merge($methods, ['TEST']), $this->app->getHttpMethods());
    }

    public function getHttpMethods()
    {
        $app     = new App();
        $methods = [];
        foreach ($app->getHttpMethods() as $method) {
            $methods[] = [ strtolower($method) ];
        }
        return $methods;
    }

    /**
     * @dataProvider getHttpMethods
     * @expectedException InvalidArgumentException
     */
    public function testHttpMethodsWithoutParams($method)
    {
        $this->app->$method();
    }

    /**
     * @expectedException BadMethodCallException
     */
    public function testUndefinedMethods()
    {
        $this->app->test();
    }

    /**
     * @dataProvider getHttpMethods
     */
    public function testHttpMethods($method)
    {
        $request = new Request(['REQUEST_METHOD' => strtoupper($method)], [], '/test');

        $this->app->$method('/test', function ($req, $res, $next) {
            return $res->getBody()->write('test');
        });
        $app    = $this->app;
        $result = $app($request, $this->response, function ($req, $res, $next) {
        });
        //$this->assertTrue($result instanceof Response);
        //$this->assertEquals($result->getStatusCode(), 200);

    }
    /*
    public function testAdd()
    {
        $this->app->addRoute('home', '/', function($req, $res, $next){
          return $res->getBody()->write('home');
        });
    }
    */
}
