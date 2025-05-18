<?php

declare(strict_types=1);

namespace _Dev\Attribute;

use Attribute;

#[Attribute( Attribute::TARGET_ALL )]
final readonly class TODO
{
    /** @var array<array-key, string> */
    public array $list;

    public function __construct( mixed ...$list )
    {
        $this->list = \array_map( '\Support\as_string', $list );
    }
}
