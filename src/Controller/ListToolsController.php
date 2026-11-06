<?php

declare(strict_types=1);

namespace Neika\SymfonyBundle\Controller;

use Neika\Sdk\Contract\ChatbotLocaleContextInterface;
use Neika\Sdk\DTO\ToolListHeaders;
use Neika\Sdk\Locale\LocaleMatcher;
use Neika\Sdk\Registry\ToolRegistry;
use Neika\Sdk\Schema\ArgumentsSchemaGenerator;
use Neika\SymfonyBundle\Translation\ChannelFallbackTranslator;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Contracts\Translation\TranslatorInterface;

class ListToolsController extends AbstractController
{
    public function __construct(
        private readonly ToolRegistry                  $toolRegistry,
        private readonly ArgumentsSchemaGenerator      $schemaGenerator,

        #[Autowire(service: ChannelFallbackTranslator::class)]
        private readonly TranslatorInterface           $translator,
        private readonly ChatbotLocaleContextInterface $localeContext,
        private readonly LocaleMatcher                 $localeMatcher,
    ) {
    }

    public function __invoke(ToolListHeaders $headers): JsonResponse
    {
        $locale = $this->resolveLocale($headers->locale);

        $tools = [];
        foreach ($this->toolRegistry->all() as $tool) {
            $argumentsClass = $tool->getArgumentsClass();
            $definition = $tool->getDefinition()
                ->withInputSchema($this->schemaGenerator->generate($argumentsClass))
                ->withSensitiveArguments($this->schemaGenerator->listSensitiveArguments($argumentsClass))
                ->translated($this->translator, $locale);
            $tools[] = $definition->jsonSerialize();
        }

        return $this->json(['tools' => $tools]);
    }

    /**
     * @invariant Matches across every channel, not the one the request resolved. A tool is the
     *            same code on all of them, so a backend driving several channels through one host
     *            must get its descriptions in the locale it asked for, not the host channel's.
     */
    private function resolveLocale(?string $requested): string
    {
        $defaultLocale = $this->localeContext->getDefaultLocale();

        if ($requested === null) {
            return $defaultLocale;
        }

        $servedLocales = $this->localeContext->getAllChannelLocales();
        $servedLocale = $this->localeMatcher->resolveServedLocale($requested, $servedLocales);

        return $servedLocale ?? $defaultLocale;
    }
}
