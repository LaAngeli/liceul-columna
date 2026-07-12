<?php

namespace App\Actions\Fortify;

use App\Concerns\PasswordValidationRules;
use App\Models\User;
use Illuminate\Support\Facades\Validator;
use Laravel\Fortify\Contracts\ResetsUserPasswords;

class ResetUserPassword implements ResetsUserPasswords
{
    use PasswordValidationRules;

    /**
     * Validate and reset the user's forgotten password.
     *
     * @param  array<string, string>  $input
     */
    public function reset(User $user, array $input): void
    {
        Validator::make($input, [
            'password' => $this->passwordRules(),
        ])->validate();

        // Resetarea pe email dovedește controlul contului și înlocuiește parola legacy — exact scopul
        // flag-ului must_change_password. Fără asta, userul migrat care își resetează parola prin „am
        // uitat parola" era blocat imediat pe /schimbare-parola și obligat să o schimbe încă o dată.
        $user->forceFill([
            'password' => $input['password'],
            'must_change_password' => false,
        ])->save();
    }
}
