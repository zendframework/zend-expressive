<?php
/**
 * Zend Framework (http://framework.zend.com/)
 *
 * @see       http://github.com/zendframework/zend-expressive for the canonical source repository
 * @copyright Copyright (c) 2015-2016 Zend Technologies USA Inc. (http://www.zend.com)
 * @license   https://github.com/zendframework/zend-expressive/blob/master/LICENSE.md New BSD License
 */

namespace Zend\Expressive;

use Psr\Http\Message\RequestInterface as Request;
use Psr\Http\Message\ResponseInterface as Response;
use Zend\Stratigility\Http\Response as StratigilityResponse;
use Zend\Stratigility\Utils;

/**
 * Final handler with templated page capabilities.
 *
 * Provides the optional ability to render a template for each of 404 and
 * general error conditions. If no template renderer is provided, returns
 * empty responses with appropriate status codes.
 */
class TemplatedErrorHandler
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
     * @var Template\TemplateRendererInterface
     */
    private $renderer;

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
     * @param null|Template\TemplateRendererInterface $renderer Template renderer.
     * @param null|string $template404 Template to use for 404 responses.
     * @param null|string $templateError Template to use for general errors.
     * @param null|Response $originalResponse Original response (used to
     *     calculate if the response has changed during middleware
     *     execution).
     */
    public function __construct(
        Template\TemplateRendererInterface $renderer = null,
        $template404 = 'error::404',
        $templateError = 'error::error',
        Response $originalResponse = null
    ) {
        $this->renderer      = $renderer;
        $this->template404   = $template404;
        $this->templateError = $templateError;
        if ($originalResponse) {
            $this->setOriginalResponse($originalResponse);
        }
    }

    /**
     * Set the original response for comparisons.
     *
     * @param Response $response
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

        return $this->handleErrorResponse($err, $request, $response);
    }

    /**
     * Handle a non-exception error.
     *
     * If a template renderer is present, passes the following to the template
     * specified in the $templateError property:
     *
     * - error (the error itself)
     * - uri
     * - status (response status)
     * - reason (reason associated with response status)
     * - request (full PSR-7 request instance)
     * - response (full PSR-7 response instance)
     *
     * The results of rendering are then written to the response body.
     *
     * This method may be used as an extension point.
     *
     * @param mixed $error
     * @param Request $request
     * @param Response $response
     * @return Response
     */
    protected function handleError($error, Request $request, Response $response)
    {
        if ($this->renderer) {
            $response->getBody()->write(
                $this->renderer->render($this->templateError, [
                    'uri'      => $request->getUri(),
                    'error'    => $error,
                    'status'   => $response->getStatusCode(),
                    'reason'   => $response->getReasonPhrase(),
                    'request'  => $request,
                    'response' => $response,
                ])
            );
        }

        return $response;
    }

    /**
     * Prepare the exception for display.
     *
     * Proxies to `handleError()`; exists primarily to as an extension point
     * for other handlers.
     *
     * @param \Throwable $exception
     * @param Request    $request
     * @param Response   $response
     * @return Response
     */
    protected function handleException($exception, Request $request, Response $response)
    {
        return $this->handleError($exception, $request, $response);
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
            // No original response detected; decide whether we have a
            // response to return
            return $this->marshalReceivedResponse($request, $response);
        }

        $originalResponse  = $this->originalResponse;
        $decoratedResponse = $response instanceof StratigilityResponse
            ? $response->getOriginalResponse()
            : $response;

        if ($originalResponse !== $response
            && $originalResponse !== $decoratedResponse
        ) {
            // Response does not match either the original response or the
            // decorated response; return it verbatim.
            return $response;
        }

        if (($originalResponse === $response || $decoratedResponse === $response)
            && $this->bodySize !== $response->getBody()->getSize()
        ) {
            // Response matches either the original response or the
            // decorated response; but the body size has changed; return it
            // verbatim.
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
        if ($this->renderer) {
            $response->getBody()->write(
                $this->renderer->render($this->template404, [ 'uri' => $request->getUri() ])
            );
        }
        return $response->withStatus(404);
    }

    /**
     * Handle an error response.
     *
     * Marshals the response status from the error.
     *
     * If the error is not an exception, it then proxies to handleError();
     * otherwise, it proxies to handleException().
     *
     * @param mixed $error
     * @param Request $request
     * @param Response $response
     * @return Response
     */
    private function handleErrorResponse($error, Request $request, Response $response)
    {
        $response = $response->withStatus(Utils::getStatusCode($error, $response));

        if (! $error instanceof \Exception && ! $error instanceof \Throwable) {
            return $this->handleError($error, $request, $response);
        }


        return $this->handleException($error, $request, $response);
    }
}
