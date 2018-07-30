<?php
/**
 * @see       https://github.com/zendframework/zend-expressive for the canonical source repository
 * @copyright Copyright (c) 2016-2017 Zend Technologies USA Inc. (https://www.zend.com)
 * @license   https://github.com/zendframework/zend-expressive/blob/master/LICENSE.md New BSD License
 */

declare(strict_types=1);

namespace Zend\Expressive\Middleware;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Throwable;
use Zend\Expressive\Response\ErrorResponseGeneratorTrait;
use Zend\Expressive\Template\TemplateRendererInterface;
use Zend\Stratigility\Utils;

class ErrorResponseGenerator
{
    use ErrorResponseGeneratorTrait;

    public const TEMPLATE_DEFAULT = 'error::error';
    public const LAYOUT_DEFAULT = 'layout::default';

    public function __construct(
        bool $isDevelopmentMode = false,
        TemplateRendererInterface $renderer = null,
        string $template = self::TEMPLATE_DEFAULT,
        string $layout = self::LAYOUT_DEFAULT
    ) {
        $this->debug     = $isDevelopmentMode;
        $this->renderer  = $renderer;
        $this->template  = $template;
        $this->layout    = $layout;
    }

    public function __invoke(
        Throwable $e,
        ServerRequestInterface $request,
        ResponseInterface $response
    ) : ResponseInterface {
        $response = $response->withStatus(Utils::getStatusCode($e, $response));

        if ($this->renderer) {
            return $this->prepareTemplatedResponse(
                $e,
                $this->renderer,
                [
                    'response' => $response,
                    'request'  => $request,
                    'uri'      => (string) $request->getUri(),
                    'status'   => $response->getStatusCode(),
                    'reason'   => $response->getReasonPhrase(),
                    'layout'   => $this->layout,
                ],
                $this->debug,
                $response
            );
        }

        return $this->prepareDefaultResponse($e, $this->debug, $response);
    }
}
