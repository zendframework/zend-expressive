<?php
/**
 * @see       https://github.com/zendframework/zend-expressive for the canonical source repository
 * @copyright Copyright (c) 2018 Zend Technologies USA Inc. (http://www.zend.com)
 * @license   https://github.com/zendframework/zend-expressive/blob/master/LICENSE.md New BSD License
 */

namespace Zend\Expressive\Handler;

use Fig\Http\Message\StatusCodeInterface;
use Interop\Http\ServerMiddleware\DelegateInterface as RequestHandlerInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Zend\Expressive\Template\TemplateRendererInterface;

class NotFoundHandler implements RequestHandlerInterface
{
    const TEMPLATE_DEFAULT = 'error::404';
    const LAYOUT_DEFAULT = 'layout::default';

    /**
     * @var TemplateRendererInterface
     */
    private $renderer;

    /**
     * @var ResponseInterface
     */
    protected $responsePrototype;

    /**
     * @var string
     */
    private $template;

    /**
     * @var string
     */
    private $layout;

    /**
     * @param ResponseInterface $responsePrototype
     * @param TemplateRendererInterface $renderer
     * @param string $template
     * @param string $layout
     */
    public function __construct(
        ResponseInterface $responsePrototype,
        TemplateRendererInterface $renderer = null,
        $template = self::TEMPLATE_DEFAULT,
        $layout = self::LAYOUT_DEFAULT
    ) {
        $this->responsePrototype = $responsePrototype;
        $this->renderer = $renderer;
        $this->template = $template;
        $this->layout = $layout;
    }

    /**
     * Creates and returns a 404 response.
     *
     * @param ServerRequestInterface $request
     * @return ResponseInterface
     */
    public function process(ServerRequestInterface $request)
    {
        if (! $this->renderer) {
            return $this->generatePlainTextResponse($request);
        }

        return $this->generateTemplatedResponse($request);
    }

    /**
     * Generates a plain text response indicating the request method and URI.
     *
     * @param ServerRequestInterface $request
     * @return ResponseInterface
     */
    private function generatePlainTextResponse(ServerRequestInterface $request)
    {
        $response = $this->responsePrototype->withStatus(StatusCodeInterface::STATUS_NOT_FOUND);
        $response->getBody()
            ->write(sprintf(
                'Cannot %s %s',
                $request->getMethod(),
                (string) $request->getUri()
            ));
        return $response;
    }

    /**
     * Generates a response using a template.
     *
     * Template will receive the current request via the "request" variable.
     *
     * @param ServerRequestInterface $request
     * @return ResponseInterface
     */
    private function generateTemplatedResponse(ServerRequestInterface $request)
    {
        $response = $this->responsePrototype->withStatus(StatusCodeInterface::STATUS_NOT_FOUND);
        $response->getBody()->write(
            $this->renderer->render($this->template, ['request' => $request, 'layout' => $this->layout])
        );

        return $response;
    }
}
