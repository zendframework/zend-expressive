<?php
/**
 * @see       https://github.com/zendframework/zend-expressive for the canonical source repository
 * @copyright Copyright (c) 2016-2016 Zend Technologies USA Inc. (http://www.zend.com)
 * @license   https://github.com/zendframework/zend-expressive/blob/master/LICENSE.md New BSD License
 */

namespace Zend\Expressive\Middleware;

use Interop\Http\Middleware\DelegateInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Zend\Expressive\Template\TemplateRendererInterface;
use Zend\Stratigility\Middleware\NotFoundHandler as StratigilityNotFoundHandler;

class NotFoundHandler extends StratigilityNotFoundHandler
{
    const TEMPLATE_DEFAULT = 'error::404';

    /**
     * @var TemplateRendererInterface
     */
    private $renderer;

    /**
     * This duplicates the property in StratigilityNotFoundHandler, but is done
     * to ensure that we have access to the value in the methods we override.
     *
     * @var ResponseInterface
     */
    protected $responsePrototype;

    /**
     * @var string
     */
    private $template;

    /**
     * @param ResponseInterface $responsePrototype
     * @param TemplateRendererInterface $renderer
     * @param string $template
     */
    public function __construct(
        ResponseInterface $responsePrototype,
        TemplateRendererInterface $renderer = null,
        $template = self::TEMPLATE_DEFAULT
    ) {
        parent::__construct($responsePrototype);
        $this->responsePrototype = $responsePrototype;
        $this->renderer          = $renderer;
        $this->template          = $template;
    }

    /**
     * Creates and returns a 404 response.
     *
     * @param ServerRequestInterface $request  Ignored.
     * @param DelegateInterface      $delegate Ignored.
     *
     * @return ResponseInterface
     */
    public function process(ServerRequestInterface $request, DelegateInterface $delegate)
    {
        if (! $this->renderer) {
            return parent::process($request, $delegate);
        }

        $response = $this->responsePrototype->withStatus(404);
        $response->getBody()->write(
            $this->renderer->render($this->template, ['request' => $request])
        );

        return $response;
    }
}
