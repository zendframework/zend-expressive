<?php
/**
 * Zend Framework (http://framework.zend.com/)
 *
 * @see       http://github.com/zendframework/zend-expressive for the canonical source repository
 * @copyright Copyright (c) 2015-2016 Zend Technologies USA Inc. (http://www.zend.com)
 * @license   https://github.com/zendframework/zend-expressive/blob/master/LICENSE.md New BSD License
 */

namespace Zend\Expressive\Container\Exception;

use Interop\Container\Exception\ContainerException;

class InvalidArgumentException extends \InvalidArgumentException implements
    ContainerException,
    ExceptionInterface
{
}
