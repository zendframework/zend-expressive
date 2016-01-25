<?php
/**
 * Swaps the generated HTML for hand-crafted HTML in the landing page.
 *
 * @license   http://opensource.org/licenses/BSD-3-Clause BSD-3-Clause
 * @copyright Copyright (c) 2016 Zend Technologies USA Inc. (http://www.zend.com)
 */

$target = file_get_contents(__DIR__ . '/html/index.html');
$source = file_get_contents(__DIR__ . '/book/index.html');

file_put_contents(
    __DIR__ . '/html/index.html',
    preg_replace('#\<\!-- content:begin --\>.*\<\!-- content:end --\>#s', $source, $target)
);
