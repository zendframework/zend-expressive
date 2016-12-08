<?php
/**
 * @see       https://github.com/zendframework/zend-expressive for the canonical source repository
 * @copyright Copyright (c) 2015-2016 Zend Technologies USA Inc. (http://www.zend.com)
 * @license   https://github.com/zendframework/zend-expressive/blob/master/LICENSE.md New BSD License
 */

namespace Zend\Expressive;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Zend\Expressive\Template\TemplateRendererInterface;

class TemplatedErrorResponseGenerator
{
    private $isDevelopmentMode;

    private $renderer;

    private $template;

    public function __construct(TemplateRendererInterface $renderer, $template, $isDevelopmentMode = false)
    {
        $this->renderer          = $renderer;
        $this->isDevelopmentMode = $isDevelopmentMode;
        $this->template          = $template;
    }

    public function __invoke($e, ServerRequestInterface $request, ResponseInterface $response)
    {
        $response = $response->withStatus(500);
        $response->getBody()->write($this->renderer->render('error::error', [
            'exception'        => $e,
            'development_mode' => $this->isDevelopmentMode,
            'status'           => $response->getStatusCode(),
            'reason'           => $response->getReasonPhrase(),
        ]));

        return $response;
    }
}
