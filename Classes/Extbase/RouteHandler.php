<?php
declare(strict_types = 1);

namespace LMS\Routes\Extbase;

/* * *************************************************************
 *
 *  Copyright notice
 *
 *  All rights reserved
 *
 *  This script is part of the TYPO3 project. The TYPO3 project is
 *  free software; you can redistribute it and/or modify
 *  it under the terms of the GNU General Public License as published by
 *  the Free Software Foundation; either version 3 of the License, or
 *  (at your option) any later version.
 *
 *  The GNU General Public License can be found at
 *  http://www.gnu.org/copyleft/gpl.html.
 *
 *  This script is distributed in the hope that it will be useful,
 *  but WITHOUT ANY WARRANTY; without even the implied warranty of
 *  MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 *  GNU General Public License for more details.
 *
 *  This copyright notice MUST APPEAR in all copies of the script!
 * ************************************************************* */

use TYPO3\CMS\Extbase\Core\Bootstrap;
use Psr\Http\Message\ResponseInterface;
use LMS\Routes\{Domain\Model\Route, Service\RouteService};
use Symfony\Component\Routing\Exception\MethodNotAllowedException;
use LMS\Routes\Support\{ErrorBuilder, Extbase\Response, ServerRequest, ObjectManageable};

/**
 * @author Sergey Borulko <borulkosergey@icloud.com>
 */
class RouteHandler
{
    /**
     * @var string
     */
    private $output;

    /**
     * @param string $slug
     *
     * @throws \Symfony\Component\Routing\Exception\ResourceNotFoundException
     * @throws \Symfony\Component\Routing\Exception\NoConfigurationException
     */
    public function __construct(string $slug)
    {
        try {
            $route = $this->getRouteService()->findRouteFor($slug);

            $this->processMiddleware($slug, $route->getArguments());
        } catch (MethodNotAllowedException $exception) {
            $this->output = ErrorBuilder::messageFor($exception);
            return;
        }

        $this->createActionArgumentsFrom($route);

        $this->run([
            'vendorName' => $route->getVendor(),
            'pluginName' => $route->getPlugin(),
            'extensionName' => $route->getExtension()
        ]);
    }

    /**
     * Creates the PSR7 Response based on output that was retrieved from FrontendRequestHandler
     *
     * @return \Psr\Http\Message\ResponseInterface
     */
    public function generateResponse(): ResponseInterface
    {
        return Response::createWith($this->output);
    }

    /**
     * Check if the specific route has any middleware and execute them
     *
     * @param string $slug
     * @param array $arguments
     *
     * @throws \RuntimeException
     */
    private function processMiddleware(string $slug, array $arguments): void
    {
        foreach ($this->getRouteService()->findMiddlewareFor($slug) as $middleware) {
            (new $middleware())->process($arguments);
        }
    }

    /**
     * @param \LMS\Routes\Domain\Model\Route $route
     */
    private function createActionArgumentsFrom(Route $route): void
    {
        $plugin = $route->getPluginNamespace();

        ServerRequest::withParameter('action', $route->getAction(), $plugin);

        foreach ($route->getArguments() as $name => $value) {
            ServerRequest::withParameter($name, $value, $plugin);
        }
    }

    /**
     * Create the Route Service Instance
     *
     * @return \LMS\Routes\Service\RouteService
     */
    private function getRouteService(): RouteService
    {
        return ObjectManageable::createObject(RouteService::class);
    }

    /**
     * {@inheritdoc}
     */
    private function run(array $config): void
    {
        /** @var \TYPO3\CMS\Extbase\Core\Bootstrap $bootstrap */
        $bootstrap = ObjectManageable::createObject(Bootstrap::class);

        $this->output = $bootstrap->run('', $config);
    }
}
