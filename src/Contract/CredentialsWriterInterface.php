<?php

declare(strict_types=1);

namespace Neika\SymfonyBundle\Contract;

use Neika\Sdk\Exception\PairingException;
use Neika\SymfonyBundle\Pairing\PairedCredentials;
use Symfony\Component\HttpFoundation\Request;

interface CredentialsWriterInterface
{
    public function findTrustedHost(Request $request): ?string;

    /**
     * @throws PairingException
     */
    public function save(PairedCredentials $credentials, Request $request): void;
}
