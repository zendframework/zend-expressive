<?php
/**
 * @see       https://github.com/zendframework/zend-expressive for the canonical source repository
 * @copyright Copyright (c) 2015-2016 Zend Technologies USA Inc. (http://www.zend.com)
 * @license   https://github.com/zendframework/zend-expressive/blob/master/LICENSE.md New BSD License
 */

namespace Zend\Expressive;

use Interop\Http\Middleware\DelegateInterface;
use Interop\Http\Middleware\ServerMiddlewareInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Zend\Expressive\Template\TemplateRendererInterface;
use Zend\Stratigility\Delegate\CallableDelegateDecorator;

class NotFoundHandler implements ServerMiddlewareInterface
{
    private $renderer;

    private $responsePrototype;

    private $template;

    /**
     * NotFoundHandler constructor.
     *
     * @param TemplateRendererInterface $renderer
     * @param ResponseInterface         $responsePrototype
     */
    public function __construct(TemplateRendererInterface $renderer, ResponseInterface $responsePrototype, $template)
    {
        $this->renderer          = $renderer;
        $this->responsePrototype = $responsePrototype;
        $this->template          = $template;
    }

    /**
     * Proxy to process()
     *
     * Proxies to process, after first wrapping the `$next` argument using the
     * CallableDelegateDecorator.
     *
     * @param ServerRequestInterface $request
     * @param ResponseInterface      $response
     * @param callable               $next
     *
     * @return ResponseInterface
     */
    public function __invoke(ServerRequestInterface $request, ResponseInterface $response, callable $next)
    {
        return $this->process($request, new CallableDelegateDecorator($next, $response));
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
        $response = $this->responsePrototype->withStatus(404);
        $response->getBody()->write(
            $this->renderer->render($this->template)
        );

        return $response;
    }
}
