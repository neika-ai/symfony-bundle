<?php

declare(strict_types=1);

namespace Neika\SymfonyBundle\Tests\Unit\Security;

use Neika\SymfonyBundle\Credentials\EnvCredentialsProvider;
use Neika\SymfonyBundle\Security\ApiSecretAuthenticator;
use Neika\SymfonyBundle\Security\ChatbotBackendUser;
use Neika\SymfonyBundle\Tests\Unit\Fixtures\FixedCredentialsProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Security\Core\Exception\BadCredentialsException;

class ApiSecretAuthenticatorTest extends TestCase
{
    public function testValidSecretReturnsBackendUserBadge(): void
    {
        $authenticator = $this->createAuthenticator('top-secret');

        $badge = $authenticator->getUserBadgeFrom('top-secret');

        self::assertSame(ChatbotBackendUser::IDENTIFIER, $badge->getUserIdentifier());
        $user = ($badge->getUserLoader())(ChatbotBackendUser::IDENTIFIER);
        self::assertInstanceOf(ChatbotBackendUser::class, $user);
        self::assertSame([ChatbotBackendUser::ROLE], $user->getRoles());
    }

    public function testWrongSecretThrowsBadCredentials(): void
    {
        $authenticator = $this->createAuthenticator('top-secret');

        $this->expectException(BadCredentialsException::class);

        $authenticator->getUserBadgeFrom('wrong-secret');
    }

    public function testEmptyConfiguredSecretAlwaysThrows(): void
    {
        $authenticator = $this->createAuthenticator('');

        $this->expectException(BadCredentialsException::class);

        $authenticator->getUserBadgeFrom('');
    }

    public function testTheCredentialsProviderDecides(): void
    {
        $authenticator = new ApiSecretAuthenticator(new FixedCredentialsProvider());

        $badge = $authenticator->getUserBadgeFrom('fixed-secret');

        self::assertSame(ChatbotBackendUser::IDENTIFIER, $badge->getUserIdentifier());
    }

    private function createAuthenticator(string $apiSecret): ApiSecretAuthenticator
    {
        $credentialsProvider = new EnvCredentialsProvider($apiSecret, 'ingest-secret', 'site-a');

        return new ApiSecretAuthenticator($credentialsProvider);
    }
}
