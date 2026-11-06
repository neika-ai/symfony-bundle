<?php

declare(strict_types=1);

namespace Neika\SymfonyBundle\Tests\Unit\Fixtures;

use Neika\Sdk\Contract\ChatbotDataSourceInterface;
use Neika\Sdk\Cursor\CursorCodec;
use Neika\Sdk\DTO\DocumentPage;
use Neika\Sdk\DTO\SourceDefinition;
use Neika\Sdk\DTO\SourceQuery;
use Neika\Sdk\Text\HtmlToText;

class CursorConsumingDataSource implements ChatbotDataSourceInterface
{
    public function __construct(
        private readonly CursorCodec $cursorCodec,
        private readonly HtmlToText  $htmlToText,
    ) {
    }

    public function getDefinition(): SourceDefinition
    {
        return new SourceDefinition('cursor_consuming', 'fixture.cursor_consuming');
    }

    public function getDocuments(SourceQuery $query): DocumentPage
    {
        return new DocumentPage([]);
    }
}
