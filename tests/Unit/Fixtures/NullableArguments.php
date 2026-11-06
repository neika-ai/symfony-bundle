<?php

declare(strict_types=1);

namespace Neika\SymfonyBundle\Tests\Unit\Fixtures;

use Neika\Sdk\Validator\Sensitive;
use Symfony\Component\Validator\Constraints as Assert;

class NullableArguments
{
    public function __construct(
        #[Assert\NotBlank]
        public readonly string $query = '',
        #[Sensitive]
        public readonly ?string $note = null,
        #[Assert\Choice(choices: ['relevance', 'price'])]
        public readonly string $sort = 'relevance',
    ) {
    }
}
