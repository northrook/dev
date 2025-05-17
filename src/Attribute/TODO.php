<?php

namespace _Dev\Attribute;

use Attribute;

/**
 * Indicate this feature is experimental.
 */
#[Attribute( Attribute::TARGET_ALL )]
final readonly class TODO
{
    public function __construct( public ?string $note = null ) {}
}
