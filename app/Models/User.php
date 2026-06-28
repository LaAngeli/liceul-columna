<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use App\Enums\AudienceDomain;
use App\Enums\NotificationChannel;
use App\Enums\NotificationType;
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
use Laravel\Fortify\TwoFactorAuthenticatable;
use Spatie\Permission\Traits\HasRoles;

/**
 * @property int $id
 * @property string $name
 * @property string|null $username
 * @property string|null $email
 * @property Carbon|null $email_verified_at
 * @property string $password
 * @property bool $must_change_password
 * @property string|null $two_factor_secret
 * @property string|null $two_factor_recovery_codes
 * @property Carbon|null $two_factor_confirmed_at
 * @property string|null $remember_token
 * @property string|null $locale
 * @property string|null $notification_locale
 * @property array<string, string>|null $notification_contacts
 * @property array<string, list<string>>|null $notification_preferences
 * @property list<string>|null $audience_domains
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['name', 'username', 'email', 'password', 'must_change_password', 'locale', 'notification_locale', 'notification_contacts', 'notification_preferences', 'audience_domains'])]
#[Hidden(['password', 'two_factor_secret', 'two_factor_recovery_codes', 'remember_token'])]
class User extends Authenticatable implements FilamentUser
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, HasRoles, Notifiable, TwoFactorAuthenticatable;

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
     * Administrația academică (super-admin / director / prim-vicedirector / administrator
     * operațional) vede TOT catalogul, fără scoping. NU implică drept de scriere — vezi
     * capabilitățile de mai jos. Administratorul tehnic e exclus (infra, fără date academice).
     */
    public function isAdministrator(): bool
    {
        return $this->hasAnyRole(UserRole::administratorValues());
    }

    /**
     * Super Administrator — rolul tehnic atotputernic (break-glass / contul IT).
     */
    public function isSuperAdmin(): bool
    {
        return $this->hasRole(UserRole::Admin->value);
    }

    /**
     * Administrator tehnic — mentenanță/infrastructură, fără acces la datele academice (§3.2).
     */
    public function isTechnicalAdmin(): bool
    {
        return $this->hasRole(UserRole::AdministratorTehnic->value);
    }

    /**
     * Administrator operațional — configurează școala + atribuțiile vicedirectorului (§3.2/⑥).
     */
    public function isOperationalAdmin(): bool
    {
        return $this->hasRole(UserRole::AdministratorOperational->value);
    }

    /**
     * Roluri „de sistem" (super-admin + admin tehnic) — văd starea tehnică/infra a platformei.
     */
    public function isSystemAdministrator(): bool
    {
        return $this->hasAnyRole([UserRole::Admin->value, UserRole::AdministratorTehnic->value]);
    }

    /**
     * Conducerea academică propriu-zisă (director / prim-vicedirector).
     */
    public function isDirector(): bool
    {
        return $this->hasAnyRole([UserRole::Director->value, UserRole::PrimVicedirector->value]);
    }

    /**
     * Conducere + operațional (director / prim-vicedirector / administrator operațional) —
     * imaginea de ansamblu a școlii (tabloul de conducere).
     */
    public function isManagement(): bool
    {
        return $this->hasAnyRole([
            UserRole::Director->value,
            UserRole::PrimVicedirector->value,
            UserRole::AdministratorOperational->value,
        ]);
    }

    /**
     * Domeniile de audiență pe care contul le gestionează (atributul „responsabil de domeniu",
     * spec §4.2). NU e un rol — se atribuie unor conturi de conducere existente, ca să evităm
     * proliferarea de roluri. Conduce rutarea audiențelor + aprobarea excepțiilor pe domeniu.
     *
     * @return list<AudienceDomain>
     */
    public function audienceDomains(): array
    {
        $domains = [];
        foreach ($this->audience_domains ?? [] as $value) {
            $domain = AudienceDomain::tryFrom((string) $value);
            if ($domain !== null) {
                $domains[] = $domain;
            }
        }

        return $domains;
    }

    /**
     * Contul răspunde de domeniul de audiență dat.
     */
    public function handlesAudienceDomain(AudienceDomain $domain): bool
    {
        return in_array($domain->value, $this->audience_domains ?? [], true);
    }

    /**
     * Contul e responsabil de cel puțin un domeniu de audiență (vede inboxul de audiențe pe domeniu).
     */
    public function canHandleAudiences(): bool
    {
        return $this->audienceDomains() !== [];
    }

    // ----------------------------------------------------------------------------------
    // Capabilități (matricea §3.3). Sursa UNICĂ de adevăr pentru celulele binare ●/—.
    // Scoping-ul fin (◐ „limitat") rămâne în policies + global scopes + concern-urile Enforces*.
    // ----------------------------------------------------------------------------------

    /**
     * Configurarea școlii: deschidere an, structură clase, alocare profesor↔disciplină↔clasă
     * (§3.3 CONFIGURARE; AO ●, Dir ◐). Administratorul operațional e proprietarul; directorul
     * și super-adminul au și ei dreptul.
     */
    public function canConfigureSchool(): bool
    {
        return $this->hasAnyRole([
            UserRole::Admin->value,
            UserRole::Director->value,
            UserRole::AdministratorOperational->value,
        ]);
    }

    /**
     * Creare / dezactivare conturi de familie (§3.3; AO ●, Dir ◐). Mecanismul concret de
     * atribuire e {@see manageableRoleValues()}.
     */
    public function canManageFamilyAccounts(): bool
    {
        return $this->canConfigureSchool();
    }

    /**
     * Poate gestiona conturi în panou (are cel puțin un rol pe care îl poate atribui).
     */
    public function canManageAccounts(): bool
    {
        return $this->manageableRoleValues() !== [];
    }

    /**
     * Publicare orar / meniu / regulament / anunțuri (§3.3; Dir ●, AO ●, VD ◐).
     */
    public function canPublishContent(): bool
    {
        return $this->hasAnyRole([
            UserRole::Admin->value,
            UserRole::Director->value,
            UserRole::PrimVicedirector->value,
            UserRole::AdministratorOperational->value,
        ]);
    }

    /**
     * Modificarea formulei de calcul al mediilor — actor: administratorul operațional, numai
     * în baza deciziei directorului (§3.3 / ⑩); versionată și logată.
     */
    public function canChangeAveragingFormula(): bool
    {
        return $this->hasAnyRole([UserRole::Admin->value, UserRole::AdministratorOperational->value]);
    }

    /**
     * Inserarea/întreținerea ORARELOR publicabile (cele 9 secțiuni Calendar) — obligație a
     * administratorului operațional (§3.2 AO: „publică orarul"); super-adminul are acces break-glass.
     */
    public function canManageSchedules(): bool
    {
        return $this->hasAnyRole([UserRole::Admin->value, UserRole::AdministratorOperational->value]);
    }

    /**
     * Aprobă corecțiile de notă solicitate de profesor/diriginte: prim-vicedirectorul, iar
     * excepțional directorul (§3.1 / ⑧). Administratorul operațional doar VEDE arhiva (○).
     */
    public function canApproveGradeCorrections(): bool
    {
        return $this->hasAnyRole([
            UserRole::Admin->value,
            UserRole::Director->value,
            UserRole::PrimVicedirector->value,
        ]);
    }

    /**
     * Operarea DIRECTĂ a catalogului (editare/anulare note, editare absențe) ca autoritate
     * academică. NU include administratorul operațional (§3.2: „nu introduce/editează note")
     * și nici administratorul tehnic (infra).
     */
    public function canAdministerCatalog(): bool
    {
        return $this->hasAnyRole([
            UserRole::Admin->value,
            UserRole::Director->value,
            UserRole::PrimVicedirector->value,
        ]);
    }

    /**
     * Validarea / închiderea situației semestriale (§3.3; VD ●, Dir ●). Dirigintele validează
     * scoped pe clasa lui (◐) — se adaugă când se construiește fluxul oficial de statut.
     */
    public function canValidateSemester(): bool
    {
        return $this->hasAnyRole([
            UserRole::Admin->value,
            UserRole::Director->value,
            UserRole::PrimVicedirector->value,
        ]);
    }

    /**
     * Arhiva corecțiilor de notă — invizibilă pe pagina copilului, vizibilă administrației
     * academice, inclusiv administratorului operațional (§3.2 / ⑧).
     */
    public function canViewCorrectionArchive(): bool
    {
        return $this->isAdministrator();
    }

    /**
     * Jurnalul de audit (§3.3 / §7): Dir ●; prim-vicedirector / AO / administrator tehnic ◐.
     */
    public function canViewAuditLog(): bool
    {
        return $this->hasAnyRole([
            UserRole::Admin->value,
            UserRole::Director->value,
            UserRole::PrimVicedirector->value,
            UserRole::AdministratorOperational->value,
            UserRole::AdministratorTehnic->value,
        ]);
    }

    /**
     * Infrastructură: backup/restore, schemă BD, migrări, securitate, certificate (§3.2 AT).
     */
    public function canManageInfrastructure(): bool
    {
        return $this->hasAnyRole([UserRole::Admin->value, UserRole::AdministratorTehnic->value]);
    }

    /**
     * Limba în care utilizatorul PRIMEȘTE notificările (spec §5): preferința explicită din Setări,
     * altfel limba de interfață, altfel RO. Folosită de {@see CatalogNotification} la randarea
     * șabloanelor predefinite — fără traducere în timp real.
     */
    public function notificationLocale(): string
    {
        $supported = ['ro', 'ru', 'en'];

        foreach ([$this->notification_locale, $this->locale] as $candidate) {
            if (is_string($candidate) && in_array($candidate, $supported, true)) {
                return $candidate;
            }
        }

        return 'ro';
    }

    /**
     * Tipurile de notificări RELEVANTE pentru rolurile acestui cont — „director pe nișa lui,
     * vicedirector pe a lui" (spec §5). Conduce matricea din Setări + e filtrul implicit de
     * rutare. Reuniune pe roluri; vezi {@see NotificationType::forRole()}.
     *
     * @return list<NotificationType>
     */
    public function availableNotificationTypes(): array
    {
        $types = [];

        foreach ($this->getRoleNames() as $roleName) {
            $role = UserRole::tryFrom((string) $roleName);

            if ($role === null) {
                continue;
            }

            foreach (NotificationType::forRole($role) as $type) {
                $types[$type->value] = $type;
            }
        }

        return array_values($types);
    }

    /**
     * Matricea EFECTIVĂ tip→canale, pentru a PRE-COMPLETA fereastra de Setări: preferința salvată
     * sau, în lipsa ei, implicit „cabinet" (in-app). Astfel utilizatorul vede starea reală, iar o
     * salvare nu îi golește din greșeală notificările. Doar tipurile relevante rolului (§5).
     *
     * @return array<string, list<string>>
     */
    public function effectiveNotificationMatrix(): array
    {
        $preferences = $this->notification_preferences ?? [];
        $matrix = [];

        foreach ($this->availableNotificationTypes() as $type) {
            $saved = $preferences[$type->value] ?? null;
            $matrix[$type->value] = is_array($saved)
                ? array_map(static fn ($value): string => (string) $value, $saved)
                : [NotificationChannel::Cabinet->value];
        }

        return $matrix;
    }

    /**
     * Canalele pe care utilizatorul vrea să primească un tip de notificare (§5). Implicit: doar
     * „cabinet" (in-app). Utilizatorul personalizează matricea în Setări → Notificări.
     *
     * @return list<NotificationChannel>
     */
    public function channelsFor(NotificationType $type): array
    {
        $preferences = $this->notification_preferences ?? [];
        $chosen = $preferences[$type->value] ?? [NotificationChannel::Cabinet->value];

        $channels = [];
        foreach ((array) $chosen as $value) {
            $channel = NotificationChannel::tryFrom((string) $value);
            if ($channel !== null) {
                $channels[] = $channel;
            }
        }

        return $channels;
    }

    /**
     * Contactul setat pentru un canal (e-mail / telegram / viber / messenger / whatsapp), sau null
     * dacă lipsește. Canalul „cabinet" nu are nevoie de contact.
     */
    public function notificationContact(NotificationChannel $channel): ?string
    {
        if ($channel === NotificationChannel::Email) {
            return $this->email;
        }

        $contacts = $this->notification_contacts ?? [];
        $value = $contacts[$channel->value] ?? null;

        return is_string($value) && $value !== '' ? $value : null;
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
     * Rolurile pe care acest utilizator (actor) le poate ATRIBUI altora la crearea/editarea
     * conturilor (ierarhie impusă pe server, §3.3 „Conturi de familie"):
     * - Super Administrator → toate;
     * - Director → toate în afară de super-admin și administrator tehnic (numire de infrastructură);
     * - Administrator operațional → conturi de familie (elev/părinte) + staff pedagogic alocat (profesor/diriginte);
     * - restul (prim-vicedirector, administrator tehnic, personal, familie) → niciunul.
     *
     * @return list<string>
     */
    public function manageableRoleValues(): array
    {
        if ($this->hasRole(UserRole::Admin->value)) {
            return UserRole::values();
        }

        if ($this->hasRole(UserRole::Director->value)) {
            return array_values(array_diff(UserRole::values(), [
                UserRole::Admin->value,
                UserRole::AdministratorTehnic->value,
            ]));
        }

        if ($this->hasRole(UserRole::AdministratorOperational->value)) {
            return [
                UserRole::Profesor->value,
                UserRole::Diriginte->value,
                UserRole::Elev->value,
                UserRole::Parinte->value,
            ];
        }

        return [];
    }

    /**
     * Poate acest cont să gestioneze (editeze/șteargă) contul-țintă? Doar dacă toate rolurile
     * țintei sunt în setul pe care actorul îl poate administra (un director NU atinge un super-admin).
     */
    public function canManageUser(self $target): bool
    {
        if (! $this->canManageAccounts()) {
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
            'must_change_password' => 'boolean',
            'two_factor_confirmed_at' => 'datetime',
            'notification_contacts' => 'array',
            'notification_preferences' => 'array',
            'audience_domains' => 'array',
        ];
    }
}
