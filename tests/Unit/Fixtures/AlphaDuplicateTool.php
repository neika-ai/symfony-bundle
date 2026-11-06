<?php

declare(strict_types=1);

namespace Neika\SymfonyBundle\Tests\Unit\Fixtures;

use Neika\Sdk\Contract\ChatbotToolInterface;
use Neika\Sdk\DTO\ToolCallContext;
use Neika\Sdk\DTO\ToolDefinition;
use Neika\Sdk\DTO\ToolResult;

class AlphaDuplicateTool implements ChatbotToolInterface
{
    public function getDefinition(): ToolDefinition
    {
        return new ToolDefinition('alpha_tool', 'fixture.alpha_duplicate_tool');
    }

    public function getArgumentsClass(): string
    {
        return NullableArguments::class;
    }

    public function execute(object $arguments, ToolCallContext $context): ToolResult
    {
        return new ToolResult([]);
    }
}
