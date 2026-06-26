<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class StoreAdmissionRequest extends FormRequest
{
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
            'parent_name' => ['required', 'string', 'max:255'],
            'phone' => ['required', 'string', 'max:50'],
            'email' => ['nullable', 'email', 'max:255'],
            'child_name' => ['required', 'string', 'max:255'],
            'child_age' => ['nullable', 'integer', 'min:3', 'max:20'],
            'desired_class' => ['nullable', 'string', 'max:100'],
            'preferred_time' => ['nullable', 'string', 'max:1000'],
        ];
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
            'preferred_time' => 'intervalul de timp',
        ];
    }
}
