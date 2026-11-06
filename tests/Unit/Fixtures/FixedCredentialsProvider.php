<?php

declare(strict_types=1);

namespace Neika\SymfonyBundle\Tests\Unit\Fixtures;

use Neika\Sdk\DTO\SiteCredentials;
use Neika\SymfonyBundle\Contract\CredentialsProviderInterface;

class FixedCredentialsProvider implements CredentialsProviderInterface
{
    public function __construct(
        private readonly ?SiteCredentials $site = new SiteCredentials('site-a', 'ingest-secret'),
    ) {
    }

    public function isApiSecretValid(#[\SensitiveParameter] string $token): bool
    {
        return $token === 'fixed-secret';
    }

    public function findCurrentSite(): ?SiteCredentials
    {
        return $this->site;
    }
}
