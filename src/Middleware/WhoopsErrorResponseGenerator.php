<?php
/**
 * @see       https://github.com/zendframework/zend-expressive for the canonical source repository
 * @copyright Copyright (c) 2016-2017 Zend Technologies USA Inc. (https://www.zend.com)
 * @license   https://github.com/zendframework/zend-expressive/blob/master/LICENSE.md New BSD License
 */

declare(strict_types=1);

namespace Zend\Expressive\Middleware;

use InvalidArgumentException;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Throwable;
use Whoops\Handler\JsonResponseHandler;
use Whoops\Handler\PrettyPageHandler;
use Whoops\Run;
use Whoops\RunInterface;
use Zend\Stratigility\Utils;

use function get_class;
use function gettype;
use function is_object;
use function method_exists;
use function sprintf;

class WhoopsErrorResponseGenerator
{
    /**
     * @var Run|RunInterface
     */
    private $whoops;

    /**
     * @param Run|RunInterface $whoops
     * @throws InvalidArgumentException if $whoops is not a Run or RunInterface
     *     instance.
     */
    public function __construct($whoops)
    {
        if (! ($whoops instanceof RunInterface || $whoops instanceof Run)) {
            throw new InvalidArgumentException(sprintf(
                '%s expects a %s or %s instance; received %s',
                get_class($this),
                Run::class,
                RunInterface::class,
                is_object($whoops) ? get_class($whoops) : gettype($whoops)
            ));
        }

        $this->whoops = $whoops;
    }

    public function __invoke(
        Throwable $e,
        ServerRequestInterface $request,
        ResponseInterface $response
    ) : ResponseInterface {
        // Walk through all handlers
        foreach ($this->whoops->getHandlers() as $handler) {
            // Add fancy data for the PrettyPageHandler
            if ($handler instanceof PrettyPageHandler) {
                $this->prepareWhoopsHandler($request, $handler);
            }

            // Set Json content type header
            if ($handler instanceof JsonResponseHandler) {
                $contentType = 'application/json';

                // Whoops < 2.1.5 does not provide contentType method
                if (method_exists($handler, 'contentType')) {
                    $contentType = $handler->contentType();
                }

                $response = $response->withHeader('Content-Type', $contentType);
            }
        }

        $response = $response->withStatus(Utils::getStatusCode($e, $response));

        $sendOutputFlag = $this->whoops->writeToOutput();
        $this->whoops->writeToOutput(false);
        $response
            ->getBody()
            ->write($this->whoops->handleException($e));
        $this->whoops->writeToOutput($sendOutputFlag);

        return $response;
    }

    /**
     * Prepare the Whoops page handler with a table displaying request information
     */
    private function prepareWhoopsHandler(ServerRequestInterface $request, PrettyPageHandler $handler) : void
    {
        $uri = $request->getAttribute('originalUri', false) ?: $request->getUri();
        $request = $request->getAttribute('originalRequest', false) ?: $request;

        $serverParams = $request->getServerParams();
        $scriptName = $serverParams['SCRIPT_NAME'] ?? '';

        $handler->addDataTable('Expressive Application Request', [
            'HTTP Method'            => $request->getMethod(),
            'URI'                    => (string) $uri,
            'Script'                 => $scriptName,
            'Headers'                => $request->getHeaders(),
            'Cookies'                => $request->getCookieParams(),
            'Attributes'             => $request->getAttributes(),
            'Query String Arguments' => $request->getQueryParams(),
            'Body Params'            => $request->getParsedBody(),
        ]);
    }
}
