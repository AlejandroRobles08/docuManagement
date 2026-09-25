<?php

namespace App\Http\Requests;

use App\Enums\DocumentType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Enum;

class StoreDocumentRequest extends FormRequest
{
    public function authorize(): bool
    {
        // Toda la ruta ya está protegida por el middleware "auth".
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'person_id' => ['nullable', 'integer', 'exists:people,id'],
            'full_name' => ['required_without:person_id', 'nullable', 'string', 'max:255'],
            'curp' => ['nullable', 'string', 'size:18', 'regex:/^[A-Z0-9]{18}$/i'],
            'birth_date' => ['nullable', 'date', 'before:today'],
            'document_type' => ['required', new Enum(DocumentType::class)],
            'document_number' => ['required', 'string', 'max:100'],
            'document' => [
                'required',
                'file',
                'mimes:jpg,jpeg,png,pdf',
                'max:8192', // KB (8 MB)
            ],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'curp.size' => 'El CURP debe tener exactamente 18 caracteres.',
            'curp.regex' => 'El CURP solo puede contener letras y números.',
            'document.mimes' => 'El documento debe ser una imagen (JPG, PNG) o un PDF.',
            'document.max' => 'El documento no debe pesar más de 8 MB.',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'person_id' => 'persona',
            'full_name' => 'nombre completo',
            'curp' => 'CURP',
            'birth_date' => 'fecha de nacimiento',
            'document_type' => 'tipo de documento',
            'document_number' => 'número de documento',
            'document' => 'archivo del documento',
        ];
    }

    protected function prepareForValidation(): void
    {
        if ($this->filled('curp')) {
            $this->merge(['curp' => strtoupper(trim((string) $this->input('curp')))]);
        }
    }
}
