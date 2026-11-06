<?php

declare(strict_types=1);

namespace Neika\SymfonyBundle\Tests\Unit\DependencyInjection;

use Neika\SymfonyBundle\DependencyInjection\Compiler\ChatbotDefinitionNamePass;
use Neika\SymfonyBundle\Tests\Unit\Fixtures\AlphaDuplicateTool;
use Neika\SymfonyBundle\Tests\Unit\Fixtures\AlphaTool;
use Neika\SymfonyBundle\Tests\Unit\Fixtures\BadNameTool;
use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Exception\LogicException;

class ChatbotDefinitionNamePassTest extends TestCase
{
    public function testAcceptsValidUniqueNames(): void
    {
        $container = new ContainerBuilder();
        $container->register(AlphaTool::class, AlphaTool::class)->addTag('neika.tool');

        (new ChatbotDefinitionNamePass())->process($container);

        $this->expectNotToPerformAssertions();
    }

    public function testDuplicateNamesFailCompilation(): void
    {
        $container = new ContainerBuilder();
        $container->register(AlphaTool::class, AlphaTool::class)->addTag('neika.tool');
        $container->register(AlphaDuplicateTool::class, AlphaDuplicateTool::class)->addTag('neika.tool');

        $this->expectException(LogicException::class);
        $this->expectExceptionMessageMatches('/Duplicate chatbot definition name "alpha_tool"/');

        (new ChatbotDefinitionNamePass())->process($container);
    }

    public function testInvalidNameFailsCompilation(): void
    {
        $container = new ContainerBuilder();
        $container->register(BadNameTool::class, BadNameTool::class)->addTag('neika.tool');

        $this->expectException(LogicException::class);

        (new ChatbotDefinitionNamePass())->process($container);
    }
}
