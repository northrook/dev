<?php

namespace _Dev\Attribute;

use Attribute;

#[Attribute( Attribute::TARGET_ALL )]
final readonly class Placeholder
{
    /**
     * @param callable-string|class-string|string $for
     * @param ?string                             $note
     */
    public function __construct(
        public string  $for,
        public ?string $note = null,
    ) {}
}
