<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class SearchBarcodeRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            // EAN-8 to GTIN-14, once spaces and dashes are removed
            'code' => ['required', 'string', 'regex:/^\d{8,14}$/'],
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
            'code.required' => 'Le code-barres est obligatoire.',
            'code.regex' => 'Le code-barres doit contenir de 8 à 14 chiffres.',
        ];
    }

    // Barcodes are often typed with spaces or dashes between the digit groups
    protected function prepareForValidation(): void
    {
        if (is_string($this->code)) {
            $this->merge(['code' => preg_replace('/[\s-]/', '', $this->code)]);
        }
    }
}
