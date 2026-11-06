<?php

declare(strict_types=1);

namespace Neika\SymfonyBundle\Tests\Unit\Fixtures;

use Neika\Sdk\Contract\ChatbotDataSourceInterface;
use Neika\Sdk\DTO\DocumentPage;
use Neika\Sdk\DTO\SourceDefinition;
use Neika\Sdk\DTO\SourceQuery;

class PlainDataSource implements ChatbotDataSourceInterface
{
    /**
     * @param ?list<string> $locales
     */
    public function __construct(
        private readonly string $name = 'plain',
        private readonly ?array $locales = null,
    ) {
    }

    public function getDefinition(): SourceDefinition
    {
        return new SourceDefinition($this->name, 'fixture.' . $this->name, $this->locales);
    }

    public function getDocuments(SourceQuery $query): DocumentPage
    {
        return new DocumentPage([]);
    }
}
