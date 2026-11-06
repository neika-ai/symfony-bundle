<?php

declare(strict_types=1);

namespace Neika\SymfonyBundle\Contract;

use Neika\Sdk\DTO\SiteCredentials;

interface CredentialsProviderInterface
{
    public function isApiSecretValid(#[\SensitiveParameter] string $token): bool;

    public function findCurrentSite(): ?SiteCredentials;
}
