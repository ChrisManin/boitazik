<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class SearchMusicBrainzRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'min:2', 'max:255'],
            'artist' => ['nullable', 'string', 'max:255'],
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
            'title.required' => 'Le titre est obligatoire.',
            'title.min' => 'Le titre doit contenir au moins 2 caractères.',
            'title.max' => 'Le titre ne peut pas dépasser 255 caractères.',
            'artist.max' => "Le nom de l'artiste ne peut pas dépasser 255 caractères.",
        ];
    }
}
