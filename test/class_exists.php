<?php
/**
 * @see       https://github.com/zendframework/zend-expressive for the canonical source repository
 * @copyright Copyright (c) 2017 Zend Technologies USA Inc. (http://www.zend.com)
 * @license   https://github.com/zendframework/zend-expressive/blob/master/LICENSE.md New BSD License
 */

namespace Zend\Expressive;

use ZendTest\Expressive\AppFactoryTest;

function class_exists($classname)
{
    if (AppFactoryTest::$existingClasses === null) {
        return \class_exists($classname);
    }
    return in_array($classname, AppFactoryTest::$existingClasses, true);
}
