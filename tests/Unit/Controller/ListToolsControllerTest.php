<?php

declare(strict_types=1);

namespace Neika\SymfonyBundle\Tests\Unit\Controller;

use Neika\Sdk\Contract\ChatbotLocaleContextInterface;
use Neika\Sdk\DTO\ToolListHeaders;
use Neika\Sdk\Locale\LocaleMatcher;
use Neika\Sdk\Registry\ToolRegistry;
use Neika\Sdk\Schema\ArgumentsSchemaGenerator;
use Neika\Sdk\Registry\ToolChoiceLoaderRegistry;
use Neika\SymfonyBundle\Controller\ListToolsController;
use Neika\SymfonyBundle\Tests\Unit\Fixtures\AlphaTool;
use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\Container;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Contracts\Translation\TranslatorInterface;

class ListToolsControllerTest extends TestCase
{
    public function testALocaleServedByAnotherChannelIsTranslatedIntoItself(): void
    {
        $translator = $this->createTranslatorRecordingTheLocale($recorded);

        $this->listTools($translator, 'de_AT');

        self::assertSame('de_AT', $recorded);
    }

    public function testALocaleNoChannelServesFallsBackToTheChannelDefaultLocale(): void
    {
        $translator = $this->createTranslatorRecordingTheLocale($recorded);

        $this->listTools($translator, 'ja_JP');

        self::assertSame('cs_CZ', $recorded);
    }

    public function testEachToolListsTheArgumentsMarkedSensitive(): void
    {
        $translator = $this->createTranslatorRecordingTheLocale($recorded);

        $response = $this->listTools($translator, 'cs_CZ');

        $listedTools = json_decode((string) $response->getContent(), true)['tools'];
        self::assertSame(['note'], $listedTools[0]['sensitiveArguments']);
    }

    private function createTranslatorRecordingTheLocale(?string &$recorded): TranslatorInterface
    {
        $recorded = null;
        $translator = $this->createStub(TranslatorInterface::class);
        $translator->method('trans')->willReturnCallback(
            static function (string $id, array $parameters, ?string $domain, ?string $locale) use (&$recorded): string {
                $recorded = $locale;

                return $id;
            },
        );

        return $translator;
    }

    private function listTools(TranslatorInterface $translator, string $requestedLocale): JsonResponse
    {
        $localeContext = $this->createStub(ChatbotLocaleContextInterface::class);
        $localeContext->method('getCurrentLocale')->willReturn('de_AT');
        $localeContext->method('getDefaultLocale')->willReturn('cs_CZ');
        $localeContext->method('getChannelLocales')->willReturn(['cs_CZ']);
        $localeContext->method('getAllChannelLocales')->willReturn(['cs_CZ', 'de_AT']);

        $controller = new ListToolsController(
            new ToolRegistry([new AlphaTool()]),
            new ArgumentsSchemaGenerator(new ToolChoiceLoaderRegistry([])),
            $translator,
            $localeContext,
            new LocaleMatcher(),
        );
        $controller->setContainer(new Container());

        return $controller->__invoke(new ToolListHeaders($requestedLocale));
    }
}
