<?php
/**
 * @see       https://github.com/zendframework/zend-expressive for the canonical source repository
 * @copyright Copyright (c) 2016 Zend Technologies USA Inc. (http://www.zend.com)
 * @license   https://github.com/zendframework/zend-expressive/blob/master/LICENSE.md New BSD License
 */

namespace Zend\Expressive\Middleware;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Zend\Expressive\Template\TemplateRendererInterface;
use Zend\Stratigility\Utils;

class ErrorResponseGenerator
{
    const TEMPLATE_DEFAULT = 'error::error';

    /**
     * @var bool
     */
    private $debug = false;

    /**
     * @var TemplateRendererInterface
     */
    private $renderer;

    /**
     * @var string
     */
    private $stackTraceTemplate = <<<'EOT'
%s raised in file %s line %d:
Message: %s
Stack Trace:
%s

EOT;

    /**
     * @var string
     */
    private $template;

    /**
     * @param bool $isDevelopmentMode
     * @param null|TemplateRendererInterface $renderer
     * @param string $template
     */
    public function __construct(
        $isDevelopmentMode = false,
        TemplateRendererInterface $renderer = null,
        $template = self::TEMPLATE_DEFAULT
    ) {
        $this->debug     = (bool) $isDevelopmentMode;
        $this->renderer  = $renderer;
        $this->template  = $template;
    }

    /**
     * @param \Throwable|\Exception $e
     * @param ServerRequestInterface $request
     * @param ResponseInterface $response
     * @return ResponseInterface
     */
    public function __invoke($e, ServerRequestInterface $request, ResponseInterface $response)
    {
        $response = $response->withStatus(Utils::getStatusCode($e, $response));

        if ($this->renderer) {
            return $this->prepareTemplatedResponse($e, $request, $response);
        }

        return $this->prepareDefaultResponse($e, $response);
    }

    /**
     * @param \Throwable|\Exception $e
     * @param ServerRequestInterface $request
     * @param ResponseInterface $response
     * @return ResponseInterface
     */
    private function prepareTemplatedResponse($e, ServerRequestInterface $request, ResponseInterface $response)
    {
        $templateData = [
            'response' => $response,
            'request'  => $request,
            'uri'      => (string) $request->getUri(),
            'status'   => $response->getStatusCode(),
            'reason'   => $response->getReasonPhrase(),
        ];

        if ($this->debug) {
            $templateData['error'] = $e;
        }

        $response->getBody()->write(
            $this->renderer->render($this->template, $templateData)
        );

        return $response;
    }

    /**
     * @param \Throwable|\Exception $e
     * @param ResponseInterface $response
     * @return ResponseInterface
     */
    private function prepareDefaultResponse($e, ResponseInterface $response)
    {
        $message = 'An unexpected error occurred';

        if ($this->debug) {
            $message .= "; strack trace:\n\n" . $this->prepareStackTrace($e);
        }

        $response->getBody()->write($message);

        return $response;
    }

    /**
     * Prepares a stack trace to display.
     *
     * @param \Throwable|\Exception $e
     * @return string
     */
    private function prepareStackTrace($e)
    {
        $message = '';
        do {
            $message .= sprintf(
                $this->stackTraceTemplate,
                get_class($e),
                $e->getFile(),
                $e->getLine(),
                $e->getMessage(),
                $e->getTraceAsString()
            );
        } while ($e = $e->getPrevious());

        return $message;
    }
}
