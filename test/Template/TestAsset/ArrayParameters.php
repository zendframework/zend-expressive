<?php
/**
 * @license   http://opensource.org/licenses/BSD-3-Clause BSD-3-Clause
 * @copyright Copyright (c) 2015 Zend Technologies USA Inc. (http://www.zend.com)
 */

namespace ZendTest\Expressive\Template\TestAsset;

use Zend\Expressive\Template\ArrayParametersTrait;

class ArrayParameters
{
    use ArrayParametersTrait;

    public function normalize($params)
    {
        return $this->normalizeParams($params);
    }
}
