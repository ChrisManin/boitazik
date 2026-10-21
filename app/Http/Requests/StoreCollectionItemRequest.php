<?php

namespace App\Http\Requests;

use App\Enums\ItemCondition;
use App\Enums\ReleaseFormat;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Enum;

class StoreCollectionItemRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'artist' => ['required', 'string', 'max:255'],
            'title' => ['required', 'string', 'max:255'],
            'year' => ['nullable', 'integer', 'between:1900,'.$this->maxYear()],
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
    public function messages(): array
    {
        return [
            'artist.required' => "L'artiste est obligatoire.",
            'artist.max' => "Le nom de l'artiste ne peut pas dépasser 255 caractères.",
            'title.required' => 'Le titre est obligatoire.',
            'title.max' => 'Le titre ne peut pas dépasser 255 caractères.',
            'year.integer' => "L'année doit être un nombre.",
            'year.between' => "L'année doit être comprise entre 1900 et ".$this->maxYear().'.',
            // Rule objects are keyed by their class name
            'format.'.Enum::class => "Le format choisi n'est pas valide.",
            'condition.'.Enum::class => "L'état choisi n'est pas valide.",
            'acquired_at.date' => "La date d'acquisition n'est pas valide.",
            'acquired_at.before_or_equal' => "La date d'acquisition ne peut pas être dans le futur.",
            'notes.max' => 'Les notes ne peuvent pas dépasser 2000 caractères.',
        ];
    }

    private function maxYear(): int
    {
        return now()->year + 1;
    }
}
