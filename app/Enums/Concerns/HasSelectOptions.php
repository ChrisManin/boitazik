<?php

namespace App\Enums\Concerns;

// For backed enums with a label(): their cases as form select options
trait HasSelectOptions
{
    /**
     * @return list<array{value: string, label: string}>
     */
    public static function options(): array
    {
        return array_map(
            fn (self $case) => ['value' => $case->value, 'label' => $case->label()],
            self::cases(),
        );
    }
}
