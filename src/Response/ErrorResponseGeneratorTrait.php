<?php
/**
 * @see       https://github.com/zendframework/zend-expressive for the canonical source repository
 * @copyright Copyright (c) 2018 Zend Technologies USA Inc. (https://www.zend.com)
 * @license   https://github.com/zendframework/zend-expressive/blob/master/LICENSE.md New BSD License
 */

declare(strict_types=1);

namespace Zend\Expressive\Response;

use Psr\Http\Message\ResponseInterface;
use Throwable;
use Zend\Expressive\Template\TemplateRendererInterface;

use function get_class;
use function sprintf;

trait ErrorResponseGeneratorTrait
{
    /**
     * Whether or not we are in debug/development mode.
     *
     * @var bool
     */
    private $debug;

    /**
     * @var TemplateRendererInterface
     */
    private $renderer;

    /**
     * @var string
     */
    private $stackTraceTemplate = <<< 'EOT'
%s raised in file %s line %d:
Message: %s
Stack Trace:
%s

EOT;

    /**
     * Name of the template to render.
     *
     * @var string
     */
    private $template;

    /**
     * Name of the layout to render.
     *
     * @var string
     */
    private $layout;

    private function prepareTemplatedResponse(
        Throwable $e,
        TemplateRendererInterface $renderer,
        array $templateData,
        bool $debug,
        ResponseInterface $response
    ) : ResponseInterface {
        if ($debug) {
            $templateData['error'] = $e;
        }

        $response->getBody()
            ->write($renderer->render($this->template, $templateData));

        return $response;
    }

    private function prepareDefaultResponse(
        Throwable $e,
        bool $debug,
        ResponseInterface $response
    ) : ResponseInterface {
        $message = 'An unexpected error occurred';

        if ($debug) {
            $message .= "; stack trace:\n\n" . $this->prepareStackTrace($e);
        }

        $response->getBody()->write($message);

        return $response;
    }

    /**
     * Prepares a stack trace to display.
     */
    private function prepareStackTrace(Throwable $e) : string
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
