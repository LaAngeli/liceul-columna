<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use App\Enums\UserRole;
use Database\Factories\UserFactory;
use Filament\Models\Contracts\FilamentUser;
use Filament\Panel;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Carbon;
use Laravel\Fortify\Contracts\PasskeyUser;
use Laravel\Fortify\PasskeyAuthenticatable;
use Laravel\Fortify\TwoFactorAuthenticatable;
use Spatie\Permission\Traits\HasRoles;

/**
 * @property int $id
 * @property string $name
 * @property string $email
 * @property Carbon|null $email_verified_at
 * @property string $password
 * @property string|null $two_factor_secret
 * @property string|null $two_factor_recovery_codes
 * @property Carbon|null $two_factor_confirmed_at
 * @property string|null $remember_token
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['name', 'email', 'password'])]
#[Hidden(['password', 'two_factor_secret', 'two_factor_recovery_codes', 'remember_token'])]
class User extends Authenticatable implements FilamentUser, PasskeyUser
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, HasRoles, Notifiable, PasskeyAuthenticatable, TwoFactorAuthenticatable;

    /**
     * Doar personalul școlii (admin/director/profesor/diriginte) accesează panoul Filament.
     * Elevii și părinții folosesc cabinetul Inertia.
     */
    public function canAccessPanel(Panel $panel): bool
    {
        return $this->hasAnyRole(UserRole::panelRoleValues());
    }

    /**
     * Pagina de start după autentificare, decisă pe server în funcție de rol:
     * personalul → panoul Filament (/admin); elevii/părinții → cabinetul Inertia.
     * Sursă UNICĂ, refolosită în LoginResponse, TwoFactorLoginResponse și garda cabinetului.
     */
    public function homePath(): string
    {
        return $this->hasAnyRole(UserRole::panelRoleValues())
            ? '/admin'
            : route('dashboard');
    }

    /**
     * Administrația (admin/director/director-adjunct) vede tot, fără scoping.
     */
    public function isAdministrator(): bool
    {
        return $this->hasAnyRole(UserRole::administratorValues());
    }

    /**
     * Fișa de profesor legată de acest cont (pentru scoping pe clase/discipline).
     *
     * @return HasOne<Teacher, $this>
     */
    public function teacher(): HasOne
    {
        return $this->hasOne(Teacher::class);
    }

    /**
     * Rolurile pe care acest utilizator (actor) le poate ATRIBUI altora la crearea/editarea conturilor:
     * admin → toate; director → toate în afară de admin; director-adjunct → toate în afară de admin și director.
     *
     * @return list<string>
     */
    public function manageableRoleValues(): array
    {
        if ($this->hasRole(UserRole::Admin->value)) {
            return UserRole::values();
        }

        if ($this->hasRole(UserRole::Director->value)) {
            return array_values(array_diff(UserRole::values(), [UserRole::Admin->value]));
        }

        if ($this->hasRole(UserRole::DirectorAdjunct->value)) {
            return array_values(array_diff(UserRole::values(), [UserRole::Admin->value, UserRole::Director->value]));
        }

        return [];
    }

    /**
     * Poate acest cont să gestioneze (editeze/șteargă) contul-țintă? Doar dacă toate rolurile
     * țintei sunt în setul pe care actorul îl poate administra (un director NU atinge un admin).
     */
    public function canManageUser(self $target): bool
    {
        if (! $this->isAdministrator()) {
            return false;
        }

        $manageable = $this->manageableRoleValues();

        return $target->getRoleNames()->every(static fn (string $role): bool => in_array($role, $manageable, true));
    }

    /**
     * Elevii la care acest cont (părinte/tutore) are acces.
     *
     * @return BelongsToMany<Student, $this>
     */
    public function students(): BelongsToMany
    {
        return $this->belongsToMany(Student::class, 'guardian_student', 'guardian_user_id', 'student_id')
            ->withTimestamps();
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'two_factor_confirmed_at' => 'datetime',
        ];
    }
}
