<?php

namespace App\Filament\Pages;

use App\Actions\SendMessage;
use App\Enums\AudienceDomain;
use App\Enums\UserRole;
use App\Filament\Resources\AbsenceMotivations\AbsenceMotivationResource;
use App\Models\User;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

/**
 * RESPONSABILII DE DOMENIU (spec §4.2) — desemnarea, mutată de pe fișa persoanei aici
 * (cerința beneficiarului, 06.08.2026: „nu înțeleg necesitatea ferestrei «Domenii de audiență»").
 *
 * DE CE nu mai stă în formularul de utilizator: era o bifă pe OM, dar întrebarea e a ȘCOLII —
 * „cine răspunde de Instruire" și „cine răspunde de Educație", câte unul. Pe fișa unei persoane
 * nu poți răspunde: nu vezi dacă domeniul mai are un responsabil, dacă a rămas descoperit sau dacă
 * ai creat tăcut al treilea. Măsurat înainte de mutare: 1 desemnare din 5 conturi eligibile și
 * zero cereri de audiență — nimeni nu înțelegea ce i se cere. Aici întrebarea are un răspuns:
 * ambele domenii, cine le ține, ce lipsește.
 *
 * CE ATÂRNĂ de desemnare (de-asta nu s-a șters, ci s-a mutat):
 *  - rutarea cererilor de audiență ale familiei pe domeniu ({@see SendMessage});
 *    ARE fallback — fără responsabil, cererea ajunge la conducere, deci nimic nu se pierde;
 *  - aprobarea motivărilor TARDIVE pe toată școala, pentru responsabilul de Educație
 *    ({@see AbsenceMotivationResource}); asta NU are
 *    fallback — fără desemnare, excepțiile rămân doar la administrație.
 *
 * UN singur responsabil per domeniu: desemnarea îl înlocuiește pe precedentul, într-o tranzacție.
 * O persoană poate ține AMBELE domenii (școală mică) — sunt desemnări independente.
 */
class AudienceDomainOwners extends Page
{
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedUserGroup;

    protected static ?int $navigationSort = 12;

    protected static ?string $slug = 'responsabili-domeniu';

    protected string $view = 'filament.catalog.audience-domain-owners';

    public static function getNavigationGroup(): ?string
    {
        return __('panel.nav.groups.administration');
    }

    public static function getNavigationLabel(): string
    {
        return __('panel.audience_domains.title');
    }

    public function getTitle(): string
    {
        return __('panel.audience_domains.title');
    }

    /** Aceeași poartă ca la editarea conturilor: desemnarea e un act de administrare a personalului. */
    public static function canAccess(): bool
    {
        return auth('web')->user()?->canManageAccounts() ?? false;
    }

    /**
     * Cele două domenii, cu responsabilul curent și starea lor.
     *
     * @return list<array{value: string, label: string, description: string, owners: list<array{id: int, name: string, role: string}>, state: string}>
     */
    public function domains(): array
    {
        $out = [];

        foreach (AudienceDomain::cases() as $domain) {
            $owners = $this->ownersOf($domain);

            $out[] = [
                'value' => $domain->value,
                'label' => $domain->label(),
                'description' => (string) __('panel.audience_domains.meaning.'.$domain->value),
                'owners' => $owners,
                // „Mai mulți" e o stare REALĂ, nu teoretică: bifa de pe fișa persoanei o permitea,
                // deci datele vechi o pot conține. Se semnalează, nu se repară tăcut.
                'state' => match (true) {
                    $owners === [] => 'uncovered',
                    count($owners) > 1 => 'multiple',
                    default => 'covered',
                },
            ];
        }

        return $out;
    }

    /**
     * Conturile care țin efectiv domeniul: atributul E marcat ȘI rolul îl poate exercita — aceeași
     * regulă ca la rutare ({@see User::scopeHandlingAudienceDomain}). O desemnare rămasă după o
     * retrogradare NU se afișează ca responsabil, fiindcă nici nu funcționează ca atare.
     *
     * @return list<array{id: int, name: string, role: string}>
     */
    private function ownersOf(AudienceDomain $domain): array
    {
        $owners = [];

        foreach (User::query()->handlingAudienceDomain($domain)->orderBy('name')->get() as $user) {
            $owners[] = [
                'id' => (int) $user->getKey(),
                'name' => (string) $user->name,
                'role' => $this->roleLabelFor($user),
            ];
        }

        return $owners;
    }

    private function roleLabelFor(User $user): string
    {
        foreach (UserRole::audienceDomainHolderValues() as $value) {
            if ($user->hasRole($value)) {
                return UserRole::from($value)->label();
            }
        }

        return (string) __('panel.common.dash');
    }

    /** Desemnează responsabilul unui domeniu (îl înlocuiește pe precedentul). */
    public function assignAction(): Action
    {
        return Action::make('assign')
            ->label(__('panel.audience_domains.assign'))
            ->icon('heroicon-o-user-plus')
            ->modalHeading(fn (array $arguments): string => __('panel.audience_domains.assign_heading', [
                'domain' => AudienceDomain::from((string) $arguments['domain'])->label(),
            ]))
            ->schema([
                Select::make('user_id')
                    ->label(__('panel.audience_domains.owner'))
                    ->options(fn (): array => $this->eligibleOptions())
                    ->helperText(__('panel.audience_domains.owner_hint'))
                    ->searchable()
                    ->required(),
            ])
            ->action(function (array $arguments, array $data): void {
                $domain = AudienceDomain::tryFrom((string) ($arguments['domain'] ?? ''));
                $user = User::query()->whereKey((int) $data['user_id'])->first();

                if ($domain === null || $user === null || ! $this->isEligible($user)) {
                    $this->deny();

                    return;
                }

                // Tranzacție: între retragerea de la precedentul și acordarea către noul responsabil
                // nu trebuie să existe o clipă cu domeniul dublu-atribuit sau descoperit.
                DB::transaction(function () use ($domain, $user): void {
                    $this->revokeEveryone($domain);
                    $this->grant($user, $domain);
                });

                Notification::make()
                    ->success()
                    ->title(__('panel.audience_domains.assigned', ['name' => $user->name]))
                    ->send();
            });
    }

    /** Retrage responsabilul unui domeniu — audiențele revin pe fallback-ul spre conducere. */
    public function clearAction(): Action
    {
        return Action::make('clear')
            ->label(__('panel.audience_domains.clear'))
            ->icon('heroicon-o-user-minus')
            ->color('gray')
            ->requiresConfirmation()
            ->modalHeading(fn (array $arguments): string => __('panel.audience_domains.clear_heading', [
                'domain' => AudienceDomain::from((string) $arguments['domain'])->label(),
            ]))
            ->modalDescription(__('panel.audience_domains.clear_description'))
            ->action(function (array $arguments): void {
                $domain = AudienceDomain::tryFrom((string) ($arguments['domain'] ?? ''));

                if ($domain === null) {
                    $this->deny();

                    return;
                }

                $this->revokeEveryone($domain);

                Notification::make()
                    ->warning()
                    ->title(__('panel.audience_domains.cleared'))
                    ->send();
            });
    }

    /**
     * Conturile care POT ține un domeniu: conducerea academică (§4.2). Rolul se verifică aici și
     * la scriere — un id venit din browser e o dorință, nu un adevăr.
     *
     * @return array<int, string>
     */
    private function eligibleOptions(): array
    {
        $options = [];

        foreach ($this->eligibleQuery()->orderBy('name')->get() as $user) {
            $options[(int) $user->getKey()] = $user->name.' · '.$this->roleLabelFor($user);
        }

        return $options;
    }

    private function isEligible(User $user): bool
    {
        return $this->eligibleQuery()->whereKey($user->getKey())->exists();
    }

    /** @return Builder<User> */
    private function eligibleQuery(): Builder
    {
        return User::query()->whereHas(
            'roles',
            fn ($query) => $query->whereIn('name', UserRole::audienceDomainHolderValues()),
        );
    }

    private function revokeEveryone(AudienceDomain $domain): void
    {
        foreach (User::query()->whereJsonContains('audience_domains', $domain->value)->get() as $user) {
            $rest = array_values(array_diff($user->audience_domains ?? [], [$domain->value]));

            $user->forceFill(['audience_domains' => $rest === [] ? null : $rest])->save();
        }
    }

    private function grant(User $user, AudienceDomain $domain): void
    {
        $domains = array_values(array_unique([...($user->audience_domains ?? []), $domain->value]));

        $user->forceFill(['audience_domains' => $domains])->save();
    }

    private function deny(): void
    {
        Notification::make()
            ->danger()
            ->title(__('panel.audience_domains.denied'))
            ->send();
    }
}
