<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreContactRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, list<string>>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:120'],
            'email' => ['required', 'email:rfc', 'max:160'],
            'phone' => ['nullable', 'string', 'max:40'],
            'subject' => ['required', 'string', 'max:160'],
            'message' => ['required', 'string', 'min:10', 'max:4000'],
            'consent' => ['accepted'],
            // `website` = honeypot anti-spam; nu se validează aici, se verifică tăcut în controller.
        ];
    }

    /**
     * Denumiri prietenoase pentru mesajele de validare.
     *
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'name' => 'nume',
            'email' => 'e-mail',
            'phone' => 'telefon',
            'subject' => 'subiect',
            'message' => 'mesaj',
            'consent' => 'consimțământ',
        ];
    }
}
