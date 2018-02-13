<?php
/**
 * @see       https://github.com/zendframework/zend-expressive for the canonical source repository
 * @copyright Copyright (c) 2018 Zend Technologies USA Inc. (https://www.zend.com)
 * @license   https://github.com/zendframework/zend-expressive/blob/master/LICENSE.md New BSD License
 */

declare(strict_types=1);

namespace Zend\Expressive;

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
 * Legacy/transitional service name for the NotFoundMiddleware introduced in
 * 3.0.0alpha2. Should resolve to the Handler\NotFoundHandler class.
 *
 * @deprecated To remove in version 4.0.0.
 * @var string
 */
const NOT_FOUND_MIDDLEWARE = __NAMESPACE__ . '\Middleware\NotFoundMiddleware';

/**
 * Virtual service name that should resolve to a service returning a PSR-7
 * ResponseInterface instance for use with the Handler\NotFoundHandler class.
 *
 * @var string
 */
const NOT_FOUND_RESPONSE = __NAMESPACE__ . '\Response\NotFoundResponseInterface';

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
 * Virtual service name that should resolve to a service returning a response
 * based on a `Throwable` argument produced when generating the application
 * request.
 *
 * @var string
 */
const SERVER_REQUEST_ERROR_RESPONSE_GENERATOR = __NAMESPACE__ . '\ServerRequestErrorResponseGenerator';

/**
 * Virtual service name that should resolve to a service capable of producing
 * a PSR-7 ServerRequestInterface instance for the application.
 *
 * @var string
 */
const SERVER_REQUEST_FACTORY = __NAMESPACE__ . '\ServerRequestFactory';
