<?php

declare(strict_types=1);

namespace Neika\SymfonyBundle\Tests\Unit\Fixtures;

use Neika\Sdk\Contract\ChatbotToolInterface;
use Neika\Sdk\DTO\ToolCallContext;
use Neika\Sdk\DTO\ToolDefinition;
use Neika\Sdk\DTO\ToolResult;

class ThrowingTool implements ChatbotToolInterface
{
    public function getDefinition(): ToolDefinition
    {
        return new ToolDefinition('throwing_tool', 'fixture.throwing_tool');
    }

    public function getArgumentsClass(): string
    {
        return NullableArguments::class;
    }

    public function execute(object $arguments, ToolCallContext $context): ToolResult
    {
        throw new \RuntimeException('boom');
    }
}
