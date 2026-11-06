<?php

declare(strict_types=1);

use Neika\SymfonyBundle\Controller\CallToolController;
use Neika\SymfonyBundle\Controller\ListSourcesController;
use Neika\SymfonyBundle\Controller\ListToolsController;
use Neika\SymfonyBundle\Controller\PairController;
use Neika\SymfonyBundle\Controller\ReadSourceController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Loader\Configurator\RoutingConfigurator;

return function (RoutingConfigurator $routes): void {
    $routes->add('neika_list_tools', '/chatbot/v1/tools')
        ->controller(ListToolsController::class)
        ->methods([Request::METHOD_GET]);

    $routes->add('neika_call_tool', '/chatbot/v1/tools/{name}')
        ->controller(CallToolController::class)
        ->methods([Request::METHOD_POST])
        ->requirements(['name' => '[a-z0-9_]+']);

    $routes->add('neika_list_sources', '/chatbot/v1/sources')
        ->controller(ListSourcesController::class)
        ->methods([Request::METHOD_GET]);

    $routes->add('neika_read_source', '/chatbot/v1/sources/{name}')
        ->controller(ReadSourceController::class)
        ->methods([Request::METHOD_GET])
        ->requirements(['name' => '[a-z0-9_]+']);

    $routes->add('neika_pair', '/chatbot/pair')
        ->controller(PairController::class)
        ->methods([Request::METHOD_GET]);
};
