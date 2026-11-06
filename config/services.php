<?php

declare(strict_types=1);

use Neika\Sdk\Contract\ChatbotLocaleContextInterface;
use Neika\Sdk\Cursor\CursorCodec;
use Neika\Sdk\Ingest\CatalogIngestClient;
use Neika\Sdk\Locale\LocaleMatcher;
use Neika\Sdk\Pairing\HostMatcher;
use Neika\Sdk\Pairing\PairingClient;
use Neika\Sdk\Registry\DataSourceRegistry;
use Neika\Sdk\Registry\ToolChoiceLoaderRegistry;
use Neika\Sdk\Registry\ToolRegistry;
use Neika\Sdk\Schema\ArgumentsSchemaGenerator;
use Neika\Sdk\Telemetry\ClickId;
use Neika\Sdk\Telemetry\TelemetryClient;
use Neika\Sdk\Text\HtmlToText;
use Neika\Sdk\Validator\ToolChoiceValidator;
use Neika\Sdk\Widget\WidgetSnippet;
use Neika\SymfonyBundle\Contract\CredentialsProviderInterface;
use Neika\SymfonyBundle\Credentials\EnvCredentialsProvider;
use Neika\SymfonyBundle\Locale\DefaultLocaleContext;
use Neika\SymfonyBundle\Reporting\BackendReportGuard;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Component\HttpClient\HttpClient;
use Symfony\Component\HttpClient\Psr18Client;
use Symfony\Contracts\HttpClient\HttpClientInterface;

use function Symfony\Component\DependencyInjection\Loader\Configurator\param;
use function Symfony\Component\DependencyInjection\Loader\Configurator\service;
use function Symfony\Component\DependencyInjection\Loader\Configurator\tagged_iterator;

return function (ContainerConfigurator $configurator): void {
    $services = $configurator->services();

    $services->defaults()
        ->autowire()
        ->autoconfigure();

    $services->load('Neika\\SymfonyBundle\\', __DIR__ . '/../src/')
        ->exclude([
            __DIR__ . '/../src/DependencyInjection',
            __DIR__ . '/../src/Security/ChatbotBackendUser.php',
            __DIR__ . '/../src/NeikaSymfonyBundle.php',
        ]);

    $services->set(LocaleMatcher::class);
    $services->set(ArgumentsSchemaGenerator::class);
    $services->set(CursorCodec::class);
    $services->set(HtmlToText::class);

    $services->set(ToolChoiceLoaderRegistry::class)
        ->arg('$loaders', tagged_iterator('neika.tool_choice_loader'));

    $services->set(ToolRegistry::class)
        ->arg('$tools', tagged_iterator('neika.tool'));

    $services->set(DataSourceRegistry::class)
        ->arg('$sources', tagged_iterator('neika.source'));

    $services->set(ToolChoiceValidator::class)
        ->tag('validator.constraint_validator');

    $services->set('neika.ingest_http_client', HttpClientInterface::class)
        ->factory([HttpClient::class, 'create'])
        ->args([['timeout' => 2.0, 'max_duration' => 5.0, 'max_redirects' => 0]]);

    $services->set(Psr18Client::class)
        ->arg('$client', service('neika.ingest_http_client'));

    $services->set(CatalogIngestClient::class)
        ->public()
        ->arg('$httpClient', service(Psr18Client::class))
        ->arg('$requestFactory', service(Psr18Client::class))
        ->arg('$streamFactory', service(Psr18Client::class))
        ->arg('$backendUrl', param('neika.backend_url'));

    $services->set(TelemetryClient::class)
        ->public()
        ->arg('$httpClient', service(Psr18Client::class))
        ->arg('$requestFactory', service(Psr18Client::class))
        ->arg('$streamFactory', service(Psr18Client::class))
        ->arg('$backendUrl', param('neika.backend_url'));

    $services->set(PairingClient::class)
        ->arg('$httpClient', service(Psr18Client::class))
        ->arg('$requestFactory', service(Psr18Client::class))
        ->arg('$streamFactory', service(Psr18Client::class))
        ->arg('$backendUrl', param('neika.backend_url'));

    $services->set(HostMatcher::class);

    $services->set(ClickId::class);

    $services->set(BackendReportGuard::class)
        ->public();

    $services->set(WidgetSnippet::class)
        ->public();

    $services->alias(ChatbotLocaleContextInterface::class, DefaultLocaleContext::class);
    $services->alias(CredentialsProviderInterface::class, EnvCredentialsProvider::class);
};
