<?php

declare(strict_types=1);

namespace Neika\SymfonyBundle\Tests\Integration;

use Neika\Sdk\Ingest\CatalogIngestClient;
use Neika\Sdk\Registry\DataSourceRegistry;
use Neika\Sdk\Registry\ToolRegistry;
use Neika\Sdk\Schema\ArgumentsSchemaGenerator;
use Neika\Sdk\Telemetry\TelemetryClient;
use Neika\Sdk\Widget\WidgetSnippet;
use Neika\SymfonyBundle\Contract\CredentialsProviderInterface;
use Neika\SymfonyBundle\Controller\PairController;
use Neika\SymfonyBundle\Credentials\EnvCredentialsProvider;
use Neika\SymfonyBundle\EventListener\LinkVisitBeacon;
use Neika\SymfonyBundle\Reporting\BackendReportGuard;
use Neika\SymfonyBundle\Tests\Unit\Fixtures\AlphaTool;
use Neika\SymfonyBundle\Tests\Unit\Fixtures\CursorConsumingDataSource;
use Neika\SymfonyBundle\Tests\Unit\Fixtures\FixedCredentialsProvider;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Config\Definition\Exception\InvalidConfigurationException;

class ContainerTest extends TestCase
{
    public function testTheBundleWiresTaggedToolsIntoTheRegistry(): void
    {
        $kernel = new NeikaTestKernel('test', true);
        $kernel->boot();
        $container = $kernel->getContainer()->get('test.service_container');

        $registry = $container->get(ToolRegistry::class);

        $tool = $registry->get('alpha_tool');
        self::assertInstanceOf(AlphaTool::class, $tool);

        $tools = iterator_to_array($registry->all(), false);
        self::assertCount(1, $tools);
        self::assertInstanceOf(AlphaTool::class, $tools[0]);
    }

    public function testTheBundleWiresTheSchemaGenerator(): void
    {
        $kernel = new NeikaTestKernel('test', true);
        $kernel->boot();
        $container = $kernel->getContainer()->get('test.service_container');

        $generator = $container->get(ArgumentsSchemaGenerator::class);
        $tool = $container->get(ToolRegistry::class)->get('alpha_tool');

        $schema = $generator->generate($tool->getArgumentsClass());

        self::assertSame('object', $schema['type']);
    }

    public function testTheOutboundClientAndWidgetBuilderAreWired(): void
    {
        $kernel = new NeikaTestKernel('test', true);
        $kernel->boot();
        $container = $kernel->getContainer()->get('test.service_container');

        self::assertInstanceOf(CatalogIngestClient::class, $container->get(CatalogIngestClient::class));
        self::assertInstanceOf(TelemetryClient::class, $container->get(TelemetryClient::class));
        self::assertInstanceOf(WidgetSnippet::class, $container->get(WidgetSnippet::class));
        self::assertInstanceOf(EnvCredentialsProvider::class, $container->get(CredentialsProviderInterface::class));
        self::assertInstanceOf(LinkVisitBeacon::class, $container->get(LinkVisitBeacon::class));
        self::assertInstanceOf(PairController::class, $container->get(PairController::class));
    }

    public function testTheCredentialsProviderAliasCanBeOverridden(): void
    {
        $kernel = new NeikaTestKernel('test', true, credentialsProviderClass: FixedCredentialsProvider::class);
        $kernel->boot();
        $container = $kernel->getContainer()->get('test.service_container');

        self::assertInstanceOf(FixedCredentialsProvider::class, $container->get(CredentialsProviderInterface::class));
    }

    public function testTheWidgetIsDeferredByDefault(): void
    {
        $kernel = new NeikaTestKernel('test', true);
        $kernel->boot();

        self::assertTrue($kernel->getContainer()->getParameter('neika.widget.defer'));
    }

    public function testTheWidgetDeferCanBeTurnedOff(): void
    {
        $kernel = new NeikaTestKernel('test', true, ['widget' => ['defer' => false]]);
        $kernel->boot();

        self::assertFalse($kernel->getContainer()->getParameter('neika.widget.defer'));
    }

    /**
     * @return iterable<string, array{array<string, mixed>}>
     */
    public static function provideRemovedConfiguration(): iterable
    {
        yield 'api_secret' => [['api_secret' => 'top-secret']];
        yield 'ingest_secret' => [['ingest_secret' => 'ingest-secret']];
        yield 'widget.site_key' => [['widget' => ['site_key' => 'site-a']]];
    }

    /**
     * @param array<string, mixed> $neikaConfig
     */
    #[DataProvider('provideRemovedConfiguration')]
    public function testRemovedConfigurationIsRejected(array $neikaConfig): void
    {
        $kernel = new NeikaTestKernel('test', true, $neikaConfig);

        $this->expectException(InvalidConfigurationException::class);

        $kernel->boot();
    }

    public function testTheBackendReportGuardIsPublic(): void
    {
        $kernel = new NeikaTestKernel('test', true);
        $kernel->boot();

        $backendReportGuard = $kernel->getContainer()->get(BackendReportGuard::class);

        self::assertInstanceOf(BackendReportGuard::class, $backendReportGuard);
    }

    public function testADataSourceCanAutowireTheSdkCursorAndTextUtilities(): void
    {
        $kernel = new NeikaTestKernel('test', true);
        $kernel->boot();
        $container = $kernel->getContainer()->get('test.service_container');

        $dataSource = $container->get(DataSourceRegistry::class)->get('cursor_consuming');

        self::assertInstanceOf(CursorConsumingDataSource::class, $dataSource);
    }

    protected function tearDown(): void
    {
        $cacheDir = sys_get_temp_dir() . '/neika_bundle_test';
        if (is_dir($cacheDir)) {
            exec('rm -rf ' . escapeshellarg($cacheDir));
        }
    }
}
