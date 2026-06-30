<?php

namespace App\Http\Requests;

use App\Http\Requests\Concerns\ValidatesChildAgeClass;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

/**
 * Programare vizită (pagina /programeaza-vizita) — contact + copil + data/ora aleasă din calendar.
 * `preferred_time` e OBLIGATORIU (formatul ISO compact „Y-m-d\TH:i", produs de VisitScheduler).
 */
class StoreVisitRequest extends FormRequest
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
            'preferred_time' => ['required', 'date_format:Y-m-d\TH:i', 'after_or_equal:today'],
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
            'preferred_time' => 'data și ora vizitei',
        ];
    }
}
