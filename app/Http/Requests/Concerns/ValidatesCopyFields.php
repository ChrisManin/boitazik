<?php

namespace App\Http\Requests\Concerns;

use App\Enums\ItemCondition;
use App\Enums\ReleaseFormat;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Enum;

// Fields the user owns on a copy, shared by the create and edit forms
trait ValidatesCopyFields
{
    /**
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    protected function copyRules(): array
    {
        return [
            'format' => ['nullable', Rule::enum(ReleaseFormat::class)],
            'condition' => ['nullable', Rule::enum(ItemCondition::class)],
            'acquired_at' => ['nullable', 'date', 'before_or_equal:today'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ];
    }

    /**
     * The app locale is English: messages are written here in French.
     *
     * @return array<string, string>
     */
    protected function copyMessages(): array
    {
        return [
            // Rule objects are keyed by their class name
            'format.'.Enum::class => "Le format choisi n'est pas valide.",
            'condition.'.Enum::class => "L'état choisi n'est pas valide.",
            'acquired_at.date' => "La date d'acquisition n'est pas valide.",
            'acquired_at.before_or_equal' => "La date d'acquisition ne peut pas être dans le futur.",
            'notes.max' => 'Les notes ne peuvent pas dépasser 2000 caractères.',
        ];
    }
}
