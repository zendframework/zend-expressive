<?php
/**
 * @link      http://github.com/zendframework/zend-expressive for the canonical source repository
 * @copyright Copyright (c) 2016-2016 Zend Technologies USA Inc. (http://www.zend.com)
 * @license   http://framework.zend.com/license/new-bsd New BSD License
 */

namespace Zend\Expressive\Middleware;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Whoops\Handler\PrettyPageHandler;
use Whoops\RunInterface as Whoops;

class WhoopsErrorResponseGenerator
{
    /**
     * @var Whoops
     */
    private $whoops;

    /**
     * @param Whoops $whoops
     */
    public function __construct(Whoops $whoops)
    {
        $this->whoops = $whoops;
    }

    /**
     * @param \Throwable|\Exception $e
     * @param ServerRequestInterface $request
     * @param ResponseInterface $response
     * @return $response
     */
    public function __invoke($e, ServerRequestInterface $request, ResponseInterface $response)
    {
        // Walk through all handlers
        foreach ($this->whoops->getHandlers() as $handler) {
            // Add fancy data for the PrettyPageHandler
            if ($handler instanceof PrettyPageHandler) {
                $this->prepareWhoopsHandler($request, $handler);
            }
        }

        $response
            ->getBody()
            ->write($this->whoops->handleException($e));

        return $response;
    }

    /**
     * Prepare the Whoops page handler with a table displaying request information
     *
     * @param ServerRequestInterface $request
     * @param PrettyPageHandler $handler
     */
    private function prepareWhoopsHandler(ServerRequestInterface $request, PrettyPageHandler $handler)
    {
        $uri = $request->getAttribute('originalUri', false) ?: $request->getUri();
        $request = $request->getAttribute('originalRequest', false) ?: $request;

        $handler->addDataTable('Expressive Application Request', [
            'HTTP Method'            => $request->getMethod(),
            'URI'                    => (string) $uri,
            'Script'                 => $request->getServerParams()['SCRIPT_NAME'],
            'Headers'                => $request->getHeaders(),
            'Cookies'                => $request->getCookieParams(),
            'Attributes'             => $request->getAttributes(),
            'Query String Arguments' => $request->getQueryParams(),
            'Body Params'            => $request->getParsedBody(),
        ]);
    }
}
