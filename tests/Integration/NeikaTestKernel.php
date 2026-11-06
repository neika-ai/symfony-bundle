<?php

declare(strict_types=1);

namespace Neika\SymfonyBundle\Tests\Integration;

use Neika\SymfonyBundle\Contract\CredentialsProviderInterface;
use Neika\SymfonyBundle\Contract\CredentialsWriterInterface;
use Neika\SymfonyBundle\NeikaSymfonyBundle;
use Neika\SymfonyBundle\Tests\Unit\Fixtures\AlphaTool;
use Neika\SymfonyBundle\Tests\Unit\Fixtures\CursorConsumingDataSource;
use Neika\SymfonyBundle\Tests\Unit\Fixtures\RecordingCredentialsWriter;
use Symfony\Bundle\FrameworkBundle\FrameworkBundle;
use Symfony\Bundle\FrameworkBundle\Kernel\MicroKernelTrait;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Component\HttpKernel\Bundle\BundleInterface;
use Symfony\Component\HttpKernel\Kernel;
use Symfony\Component\Routing\Loader\Configurator\RoutingConfigurator;

class NeikaTestKernel extends Kernel
{
    use MicroKernelTrait;

    /**
     * @param array<string, mixed> $neikaConfig
     * @param ?class-string        $credentialsProviderClass
     */
    public function __construct(
        string                   $environment,
        bool                     $debug,
        private readonly array   $neikaConfig = [],
        private readonly ?string $credentialsProviderClass = null,
        private readonly bool    $withCredentialsWriter = false,
    ) {
        parent::__construct($environment, $debug);
    }

    /**
     * @return iterable<BundleInterface>
     */
    public function registerBundles(): iterable
    {
        return [
            new FrameworkBundle(),
            new NeikaSymfonyBundle(),
        ];
    }

    protected function configureContainer(ContainerConfigurator $container): void
    {
        $container->extension('framework', [
            'secret' => 'test',
            'test' => true,
            'http_method_override' => false,
            'handle_all_throwables' => true,
            'php_errors' => ['log' => true],
        ]);
        $container->extension('neika', $this->neikaConfig);

        $services = $container->services();

        if ($this->credentialsProviderClass !== null) {
            $services->set($this->credentialsProviderClass);
            $services->alias(CredentialsProviderInterface::class, $this->credentialsProviderClass);
        }

        if ($this->withCredentialsWriter) {
            $services->set(RecordingCredentialsWriter::class);
            $services->alias(CredentialsWriterInterface::class, RecordingCredentialsWriter::class);
        }

        $services
            ->set(AlphaTool::class)
            ->autoconfigure()
            ->autowire();

        $services
            ->set(CursorConsumingDataSource::class)
            ->autoconfigure()
            ->autowire();
    }

    protected function configureRoutes(RoutingConfigurator $routes): void
    {
        $routes->import(__DIR__ . '/../../config/routes.php');
    }

    public function getCacheDir(): string
    {
        return sys_get_temp_dir() . '/neika_bundle_test/cache';
    }

    public function getLogDir(): string
    {
        return sys_get_temp_dir() . '/neika_bundle_test/log';
    }
}
