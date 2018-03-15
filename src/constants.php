<?php
/**
 * @see       https://github.com/zendframework/zend-expressive for the canonical source repository
 * @copyright Copyright (c) 2018 Zend Technologies USA Inc. (https://www.zend.com)
 * @license   https://github.com/zendframework/zend-expressive/blob/master/LICENSE.md New BSD License
 */

declare(strict_types=1);

namespace Zend\Expressive;

use Psr\Http\Message\ServerRequestInterface;

/**
 * Legacy service name for the default delegate referenced in version 2.
 * Should resolve to the Handler\NotFoundHandler class.
 *
 * @deprecated To remove in version 4.0.0.
 * @var string
 */
const DEFAULT_DELEGATE = __NAMESPACE__ . '\Delegate\DefaultDelegate';

/**
 * Legacy service name for the DispatchMiddleware referenced in version 2.
 * Should resolve to the Zend\Expressive\Router\Middleware\DispatchMiddleware
 * service.
 *
 * @deprecated To remove in version 4.0.0.
 * @var string
 */
const DISPATCH_MIDDLEWARE = __NAMESPACE__ . '\Middleware\DispatchMiddleware';

/**
 * Legacy service name for the ImplicitHeadMiddleware referenced in version 2.
 * Should resolve to the Zend\Expressive\Router\Middleware\ImplicitHeadMiddleware
 * service.
 *
 * @deprecated To remove in version 4.0.0.
 * @var string
 */
const IMPLICIT_HEAD_MIDDLEWARE = __NAMESPACE__ . '\Middleware\ImplicitHeadMiddleware';

/**
 * Legacy service name for the ImplicitOptionsMiddleware referenced in version 2.
 * Should resolve to the Zend\Expressive\Router\Middleware\ImplicitOptionsMiddleware
 * service.
 *
 * @deprecated To remove in version 4.0.0.
 * @var string
 */
const IMPLICIT_OPTIONS_MIDDLEWARE = __NAMESPACE__ . '\Middleware\ImplicitOptionsMiddleware';

/**
 * Legacy/transitional service name for the NotFoundMiddleware introduced in
 * 3.0.0alpha2. Should resolve to the Handler\NotFoundHandler class.
 *
 * @deprecated To remove in version 4.0.0.
 * @var string
 */
const NOT_FOUND_MIDDLEWARE = __NAMESPACE__ . '\Middleware\NotFoundMiddleware';

/**
 * Legacy service name for the RouteMiddleware referenced in version 2.
 * Should resolve to the Zend\Expressive\Router\Middleware\PathBasedRoutingMiddleware
 * service.
 *
 * @deprecated To remove in version 4.0.0.
 * @var string
 */
const ROUTE_MIDDLEWARE = __NAMESPACE__ . '\Middleware\RouteMiddleware';

/**
 * Legacy/transitional service name for the ServerRequestFactory virtual
 * service introduced in 3.0.0alpha6. Should resolve to the
 * Psr\Http\Message\ServerRequestInterface service.
 *
 * @deprecated To remove in version 4.0.0.
 * @var string
 */
const SERVER_REQUEST_FACTORY = ServerRequestInterface::class;
