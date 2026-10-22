<?php

namespace App\Http\Requests;

use App\Http\Requests\Concerns\ValidatesCopyFields;
use Illuminate\Foundation\Http\FormRequest;

// The shared catalog (artist, title, year) is not editable from a copy
class UpdateCollectionItemRequest extends FormRequest
{
    use ValidatesCopyFields;

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return $this->copyRules();
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return $this->copyMessages();
    }
}
