<?php
/**
 * Adds `class="img-responsive"` to images in generated HTML files.
 *
 * @license   http://opensource.org/licenses/BSD-3-Clause BSD-3-Clause
 * @copyright Copyright (c) 2016 Zend Technologies USA Inc. (http://www.zend.com)
 */

$rdi = new RecursiveDirectoryIterator(__DIR__ . '/html');
$rii = new RecursiveIteratorIterator($rdi, RecursiveIteratorIterator::SELF_FIRST);
$files = new RegexIterator($rii, '/\.html$/', RecursiveRegexIterator::GET_MATCH);

$process = function () use ($files) {
    $fileInfo = $files->getInnerIterator()->current();
    if (! $fileInfo->isFile()) {
        return true;
    }

    if ($fileInfo->getBasename('.html') === $fileInfo->getBasename()) {
        return true;
    }

    $file = $fileInfo->getRealPath();
    $html = file_get_contents($file);
    if (! preg_match('#<p><img alt="[^"]*" src="[^"]+" \/><\/p>#s', $html)) {
        return true;
    }
    $html = preg_replace(
        '#(<p><img alt="[^"]*" src="[^"]+" )(\/><\/p>)#s',
        '$1class="img-responsive"$2',
        $html
    );
    file_put_contents($file, $html);

    return true;
};

iterator_apply($files, $process);
