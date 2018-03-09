<?php
/**
 * @see       https://github.com/zendframework/zend-expressive for the canonical source repository
 * @copyright Copyright (c) 2016-2017 Zend Technologies USA Inc. (http://www.zend.com)
 * @license   https://github.com/zendframework/zend-expressive/blob/master/LICENSE.md New BSD License
 */

namespace Zend\Expressive\Middleware;

use Psr\Http\Message\ResponseInterface;
use Zend\Diactoros\Response;
use Zend\Expressive\Router\Middleware\ImplicitOptionsMiddleware as BaseImplicitOptionsMiddleware;

/**
 * Handle implicit OPTIONS requests.
 *
 * This is an extension to the canonical version provided in
 * zend-expressive-router v2.4 and up, and is deprecated in favor of that
 * version starting in zend-expressive 2.2.
 *
 * @deprecated since 2.2.0; to be removed in 3.0.0. Please use the version
 *     provided in zend-expressive-router 2.4+, and use the factory from
 *     that component to create an instance.
 */
class ImplicitOptionsMiddleware extends BaseImplicitOptionsMiddleware
{
    /**
     * @param null|ResponseInterface $response Response prototype to use for
     *     implicit OPTIONS requests; if not provided a zend-diactoros Response
     *     instance will be created and used.
     */
    public function __construct(ResponseInterface $response = null)
    {
        trigger_error(sprintf(
            '%s is deprecated starting with zend-expressive 2.2.0; please use the %s class'
            . ' provided in zend-expressive-router 2.4.0 and later. That class has required'
            . ' dependencies, so please also add Zend\Expressive\Router\ConfigProvider to'
            . ' your config/config.php file as well.',
            __CLASS__,
            BaseImplicitOptionsMiddleware::class
        ), E_USER_DEPRECATED);

        parent::__construct($response ?: new Response());
    }
}
