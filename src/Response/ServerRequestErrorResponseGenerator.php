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
use Zend\Stratigility\Utils;

/**
 * Generates a response for use when the server request factory fails.
 */
class ServerRequestErrorResponseGenerator
{
    use ErrorResponseGeneratorTrait;

    public const TEMPLATE_DEFAULT = 'error::error';

    /**
     * Factory capable of generating a ResponseInterface instance.
     *
     * @var callable
     */
    private $responseFactory;

    public function __construct(
        callable $responseFactory,
        bool $isDevelopmentMode = false,
        TemplateRendererInterface $renderer = null,
        string $template = self::TEMPLATE_DEFAULT
    ) {
        $this->responseFactory = function () use ($responseFactory) : ResponseInterface {
            return $responseFactory();
        };

        $this->debug     = $isDevelopmentMode;
        $this->renderer  = $renderer;
        $this->template  = $template;
    }

    public function __invoke(Throwable $e) : ResponseInterface
    {
        $response = ($this->responseFactory)();
        $response = $response->withStatus(Utils::getStatusCode($e, $response));

        if ($this->renderer) {
            return $this->prepareTemplatedResponse(
                $e,
                $this->renderer,
                [
                    'response' => $response,
                    'status'   => $response->getStatusCode(),
                    'reason'   => $response->getReasonPhrase(),
                ],
                $this->debug,
                $response
            );
        }

        return $this->prepareDefaultResponse($e, $this->debug, $response);
    }
}
