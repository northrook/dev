<?php

declare(strict_types=1);

namespace _Dev\Exception;

use RuntimeException;
use Throwable;

final class ToDoException extends RuntimeException
{
    /** @var string[] */
    protected array $context = [];

    public function __construct(
        string|bool|int|float|Throwable ...$context,
    ) {
        $previous = null;

        foreach ( $context as $label => $value ) {
            if ( $value instanceof Throwable ) {
                if ( $previous === null ) {
                    $previous = $value;
                    unset( $context[$label] );

                    continue;
                }
            }

            if ( \is_bool( $value ) ) {
                $value = $value ? 'true' : 'false';
            }
            else {
                $value = (string) $value;
            }

            if ( \is_string( $label ) && $label ) {
                $value = \trim( $label ).': '.\trim( $value );
            }

            $this->context[] = $value;
        }

        $message = match ( \count( $this->context ) ) {
            0       => 'Unknown '.__CLASS__.' thrown',
            1       => \implode( $this->context ),
            default => '- '.\implode( "\n- ", $this->context )."\n",
        };

        parent::__construct( $message, 0, $previous );
    }
}
