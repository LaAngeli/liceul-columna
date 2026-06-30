<?php

namespace App\Filament\Pages\Auth;

use Filament\Auth\Pages\EditProfile as BaseEditProfile;
use Illuminate\Contracts\Support\Htmlable;

/**
 * Pagina „Profil" a panoului staff — editarea propriului cont (nume, email, parolă, 2FA).
 *
 * ⚠️ Pagina de profil Filament e legată de meniul user, NU de navigația laterală: `->profile()`
 * doar îi înregistrează ruta + componenta Livewire, fără a o adăuga în colecția de pagini peste
 * care iterează sidebar-ul. De aceea `$navigationGroup`/`shouldRegisterNavigation()` nu au efect
 * aici. Linkul „Setări → Profil" din sidebar e un `NavigationItem` către `getProfileUrl()`,
 * declarat în `AdminPanelProvider`. Tema și limba rămân în meniul user (pastila + tabs).
 */
class EditProfile extends BaseEditProfile
{
    public function getTitle(): string|Htmlable
    {
        return __('panel.pages.profile.title');
    }

    public static function getLabel(): string
    {
        return __('panel.pages.profile.title');
    }
}
