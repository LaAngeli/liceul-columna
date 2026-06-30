<?php

namespace App\Http\Requests;

use App\Http\Requests\Concerns\ValidatesChildAgeClass;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

/**
 * Cerere de înmatriculare (pagina /inregistrarea-student) — date despre familie + copil,
 * FĂRĂ programare de vizită (aceea are formularul ei dedicat).
 */
class StoreAdmissionRequest extends FormRequest
{
    use ValidatesChildAgeClass;

    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'parent_name' => ['required', 'string', 'max:255', 'regex:/^[\p{L}\s\'.\-]+$/u'],
            'phone' => ['required', 'string', 'max:50', 'regex:/^[\d+()\s\-]+$/'],
            'email' => ['nullable', 'email', 'max:255'],
            'child_name' => ['required', 'string', 'max:255', 'regex:/^[\p{L}\s\'.\-]+$/u'],
            'child_age' => ['nullable', 'integer', 'min:3', 'max:20'],
            'desired_class' => ['nullable', 'string', 'max:100'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(fn (Validator $v) => $this->validateAgeClassCoherence($v));
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'parent_name' => 'numele părintelui',
            'phone' => 'telefonul',
            'email' => 'adresa de e-mail',
            'child_name' => 'numele copilului',
            'child_age' => 'vârsta copilului',
            'desired_class' => 'clasa pentru înmatriculare',
        ];
    }
}
