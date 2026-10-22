<?php

namespace App\Http\Requests;

use App\Http\Requests\Concerns\ValidatesCopyFields;
use Illuminate\Foundation\Http\FormRequest;

class StoreCollectionItemRequest extends FormRequest
{
    use ValidatesCopyFields;

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
            ...$this->copyRules(),
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
            ...$this->copyMessages(),
        ];
    }

    private function maxYear(): int
    {
        return now()->year + 1;
    }
}
