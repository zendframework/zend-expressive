<?php
/**
 * Zend Framework (http://framework.zend.com/)
 *
 * @see       http://github.com/zendframework/zend-diactoros for the canonical source repository
 * @copyright Copyright (c) 2015 Zend Technologies USA Inc. (http://www.zend.com)
 * @license   https://github.com/zendframework/zend-diactoros/blob/master/LICENSE.md New BSD License
 */

namespace Zend\Expressive\Router;

interface RouterInterface
{
    /**
     * @param  string $patch
     * @param  array $params
     * @return boolean
     */
    public function match($path, $params);

    /**
     * @return array
     */
    public function getMatchedParams();

    /**
     * @return string
     */
    public function getMatchedName();

    /**
     * @return mixed
     */
    public function getMatchedCallable();

    /**
     * @param string $name
     * @param string $path
     * @param callable $callable
     * @param array $options
     */
    public function addRoute($name, $path, $callable, $options = []);
}
