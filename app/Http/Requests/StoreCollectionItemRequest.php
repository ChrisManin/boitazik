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
        $copyRules = $this->copyRules();

        return [
            // With a release MBID, the edition gives the album and the format: the other album fields are ignored
            'release_mbid' => ['nullable', 'uuid'],
            // With an MBID, the album comes from MusicBrainz: the manual fields are ignored (an empty mbid is null)
            'mbid' => ['exclude_unless:release_mbid,null', 'nullable', 'uuid'],
            'artist' => ['exclude_unless:release_mbid,null', 'exclude_unless:mbid,null', 'required_without:mbid', 'string', 'max:255'],
            'title' => ['exclude_unless:release_mbid,null', 'exclude_unless:mbid,null', 'required_without:mbid', 'string', 'max:255'],
            'year' => ['exclude_unless:release_mbid,null', 'exclude_unless:mbid,null', 'nullable', 'integer', 'between:1900,'.$this->maxYear()],
            ...$copyRules,
            'format' => ['exclude_unless:release_mbid,null', ...$copyRules['format']],
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
            'release_mbid.uuid' => "L'identifiant de l'édition n'est pas valide.",
            'mbid.uuid' => "L'identifiant MusicBrainz n'est pas valide.",
            'artist.required_without' => "L'artiste est obligatoire.",
            'artist.max' => "Le nom de l'artiste ne peut pas dépasser 255 caractères.",
            'title.required_without' => 'Le titre est obligatoire.',
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
