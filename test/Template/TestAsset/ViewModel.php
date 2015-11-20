<?php
/**
 * @license   http://opensource.org/licenses/BSD-3-Clause BSD-3-Clause
 * @copyright Copyright (c) 2015 Zend Technologies USA Inc. (http://www.zend.com)
 */

namespace ZendTest\Expressive\Template\TestAsset;

class ViewModel
{
    private $variables;

    public function __construct(array $variables)
    {
        $this->variables = $variables;
    }

    public function getVariables()
    {
        return $this->variables;
    }
}
