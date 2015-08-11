<?php
/**
 * Zend Framework (http://framework.zend.com/)
 *
 * @see       https://github.com/zendframework/zend-expressive for the canonical source repository
 * @copyright Copyright (c) 2015 Zend Technologies USA Inc. (http://www.zend.com)
 * @license   https://github.com/zendframework/zend-expressive/blob/master/LICENSE.md New BSD License
 */

namespace Zend\Expressive\Template;

use Traversable;
use Zend\Expressive\Exception;

trait ArrayParametersTrait
{
    /**
     * Cast params to an array, if possible.
     *
     * @param mixed $params
     * @return array
     * @throws Exception\InvalidArgumentException for non-array, non-object parameters.
     */
    private function normalizeParams($params)
    {
        if (null === $params) {
            return [];
        }

        if (is_array($params)) {
            return $params;
        }

        if ($params instanceof Traversable) {
            return iterator_to_array($params);
        }

        if (is_object($params)) {
            return (array) $params;
        }

        throw new Exception\InvalidArgumentException(sprintf(
            'Twig template adapter can only handle arrays, Traversables, and objects '
            . 'when rendering; received %s',
            gettype($params)
        ));
    }
}
