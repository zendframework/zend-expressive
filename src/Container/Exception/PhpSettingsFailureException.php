<?php
/**
 * Zend Framework (http://framework.zend.com/)
 *
 * @see       http://github.com/zendframework/zend-expressive for the canonical source repository
 * @copyright Copyright (c) 2015 Zend Technologies USA Inc. (http://www.zend.com)
 * @license   https://github.com/zendframework/zend-expressive/blob/master/LICENSE.md New BSD License
 */

namespace Zend\Expressive\Container\Exception;

use RuntimeException;

/**
 * Exception indicating that setting of some PHP configuration option has failed.
 */
class PhpSettingsFailureException extends RuntimeException implements ExceptionInterface
{
    public static function forOption($name, $value)
    {
        return new self("Setting PHP configuration '$name' with a value of '$value' has failed");
    }
}
