<?php
namespace ZendTest\Expressive\TestAsset;

use Interop\Container\Exception\ContainerException as BaseException;
use RuntimeException;

class ContainerException extends RuntimeException implements BaseException
{
}
