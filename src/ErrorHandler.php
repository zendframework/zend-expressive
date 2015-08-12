<?php
/**
 * Zend Framework (http://framework.zend.com/)
 *
 * @see       http://github.com/zendframework/zend-expressive for the canonical source repository
 * @copyright Copyright (c) 2015 Zend Technologies USA Inc. (http://www.zend.com)
 * @license   https://github.com/zendframework/zend-expressive/blob/master/LICENSE.md New BSD License
 */

namespace Zend\Expressive;

use Psr\Http\Message\RequestInterface as Request;
use Psr\Http\Message\ResponseInterface as Response;
use Whoops\Handler\PrettyPageHandler;
use Whoops\Run as Whoops;
use Zend\Stratigility\Http\Request as StratigilityRequest;
use Zend\Stratigility\Utils;

class ErrorHandler
{
    /**
     * Body size on the original response; used to compare against received
     * response in order to determine if changes have been made.
     *
     * @var int
     */
    private $bodySize;

    /**
     * Original response against which to compare when determining if the
     * received response is a different instance, and thus should be directly
     * returned.
     *
     * @var Response
     */
    private $originalResponse;

    /**
     * Template renderer to use when rendering error pages; if not provided,
     * only the status will be updated.
     *
     * @var Template\TemplateInterface
     */
    private $template;

    /**
     * Name of 404 template to use when creating 404 response content with the
     * template renderer.
     *
     * @var string
     */
    private $template404;

    /**
     * Name of error template to use when creating response content for pages
     * with errors.
     *
     * @var string
     */
    private $templateError;

    /**
     * Whoops runner instance to use when returning exception details.
     *
     * @var Whoops
     */
    private $whoops;

    /**
     * Whoops PrettyPageHandler; injected to allow runtime configuration with
     * request information.
     *
     * @var PrettyPageHandler
     */
    private $whoopsHandler;

    /**
     * @param Whoops $whoops
     * @param null|Template\TemplateInterface $template
     * @param null|string $template404
     * @param null|string $templateError
     * @param null|Response $originalResponse
     */
    public function __construct(
        Whoops $whoops,
        PrettyPageHandler $whoopsHandler,
        Template\TemplateInterface $template = null,
        $template404 = 'error/404',
        $templateError = 'error/error',
        Response $originalResponse = null
    ) {
        $this->whoops        = $whoops;
        $this->whoopsHandler = $whoopsHandler;
        $this->template      = $template;
        $this->template404   = $template404;
        $this->templateError = $templateError;
        if ($originalResponse) {
            $this->setOriginalResponse($originalResponse);
        }
    }

    /**
     * Set the original response for comparisons.
     *
     * @param Response $originalResponse
     */
    public function setOriginalResponse(Response $response)
    {
        $this->bodySize = $response->getBody()->getSize();
        $this->originalResponse = $response;
    }

    /**
     * Final handler for an application.
     *
     * @param Request $request
     * @param Response $response
     * @param null|mixed $err
     * @return Response
     */
    public function __invoke(Request $request, Response $response, $err = null)
    {
        if (! $err) {
            return $this->handlePotentialSuccess($request, $response);
        }

        return $this->handleError($err, $request, $response);
    }

    /**
     * Handle a non-error condition.
     *
     * Non-error conditions mean either all middleware called $next(), and we
     * have a complete response, or no middleware was able to handle the
     * request.
     *
     * This method determines which occurred, returning the response in the
     * first instance, and returning a 404 response in the second.
     *
     * @param Request $request
     * @param Response $response
     * @return Response
     */
    private function handlePotentialSuccess(Request $request, Response $response)
    {
        if (! $this->originalResponse) {
            return $this->marshalReceivedResponse($request, $response);
        }

        if ($this->originalResponse !== $response) {
            return $response;
        }

        if ($this->bodySize !== $response->getBody()->getSize()) {
            return $response;
        }

        return $this->create404($request, $response);
    }

    /**
     * Determine whether to return the given response, or a 404.
     *
     * If no original response was present, we check to see if we have a 200
     * response with empty content; if so, we treat it as a 404.
     *
     * Otherwise, we return the response intact.
     *
     * @param Request $request
     * @param Response $response
     * @return Response
     */
    private function marshalReceivedResponse(Request $request, Response $response)
    {
        if ($response->getStatusCode() === 200
            && $response->getBody()->getSize() === 0
        ) {
            return $this->create404($request, $response);
        }

        return $response;
    }

    /**
     * Create a 404 response.
     *
     * If we have a template renderer composed, renders the 404 template into
     * the response.
     *
     * @param Request $request
     * @param Response $response
     * @return Response
     */
    private function create404(Request $request, Response $response)
    {
        if ($this->template) {
            $response->getBody()->write(
                $this->template->render($this->template404, [ 'uri' => $request->getUri() ])
            );
        }
        return $response->withStatus(404);
    }

    /**
     * Handle an error.
     *
     * Marshals the response status from the error.
     *
     * If the error is not an exception, and we have a template renderer,
     * renders the error template into the response.
     *
     * If the error is an exception, uses whoops to create the payload.
     *
     * @param mixed $error
     * @param Request $request
     * @param Response $response
     * @return Response
     */
    private function handleError($error, Request $request, Response $response)
    {
        $response = $response->withStatus(Utils::getStatusCode($error, $response));

        if (! $error instanceof \Exception) {
            if ($this->template) {
                $response->getBody()->write(
                    $this->template->render($this->templateError, [
                        'uri'    => $request->getUri(),
                        'error'  => $error,
                        'status' => $response->getStatusCode(),
                        'reason' => $response->getReasonPhrase(),
                    ])
                );
            }
            return $response;
        }

        $this->prepareWhoopsHandler($request);

        $content = $this->whoops->handleException($error);
        $response->getBody()->write($content);
        return $response;
    }

    /**
     * Prepare the Whoops page handler with a table displaying request information
     *
     * @param Request $request
     */
    private function prepareWhoopsHandler(Request $request)
    {
        if ($request instanceof StratigilityRequest) {
            $request = $request->getOriginalRequest();
        }

        $uri = $request->getUri();
        $this->whoopsHandler->addDataTable('Expressive Application Request', [
            'HTTP Method'            => $request->getMethod(),
            'URI'                    => $uri,
            'Script'                 => $request->getServerParams()['SCRIPT_NAME'],
            'Headers'                => $request->getHeaders(),
            'Cookies'                => $request->getCookieParams(),
            'Attributes'             => $request->getAttributes(),
            'Query String Arguments' => $request->getQueryParams(),
            'Body Params'            => $request->getParsedBody(),
        ]);
    }
}
