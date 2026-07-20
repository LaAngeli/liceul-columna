<?php

namespace App\Filament\Pages;

use App\Enums\UserRole;
use App\Models\User;
use BackedEnum;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Illuminate\Database\Eloquent\Model;
use ReflectionClass;
use ReflectionMethod;

/**
 * Cine ce poate face — matricea EFECTIVĂ de capabilități, generată prin reflecție peste
 * {@see User}.
 *
 * De ce generată și nu scrisă: matricea §3.3 din specificație există deja pe hârtie, iar
 * implementarea ei stă în metodele `can*()`/`is*()` ale modelului User. Orice tabel scris de mână
 * aici ar fi o A TREIA versiune a aceluiași adevăr — cea care se desincronizează tăcut la prima
 * schimbare de rol și pe care nimeni n-o mai verifică. Pagina interoghează codul real: dacă o
 * capabilitate se schimbă, tabelul se schimbă în aceeași secundă, iar dacă apare una nouă, apare și
 * aici, fără să fi cerut cuiva să-și amintească.
 *
 * Read-only prin construcție: drepturile se schimbă în cod și în policies, nu dintr-un ecran.
 */
class RoleMatrix extends Page
{
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedTableCells;

    protected static ?string $slug = 'matrice-roluri';

    protected string $view = 'filament.catalog.role-matrix';

    /**
     * Capabilități care descriu IDENTITATEA contului („ce ești"), nu o permisiune („ce poți").
     * Rămân în afara matricei: pe un tabel de drepturi ar fi zgomot, iar unele („e super-admin")
     * s-ar citi greșit ca permisiune acordabilă.
     */
    private const IDENTITY_PREFIX = 'is';

    public static function getNavigationGroup(): ?string
    {
        return __('panel.nav.groups.administration');
    }

    public static function getNavigationLabel(): string
    {
        return __('panel.role_matrix.title');
    }

    public function getTitle(): string
    {
        return __('panel.role_matrix.title');
    }

    public function getSubheading(): ?string
    {
        return __('panel.role_matrix.subtitle');
    }

    /**
     * Doar cine administrează conturi sau auditează sistemul: matricea arată harta completă a
     * drepturilor, adică exact ce trebuie să vadă cine acordă roluri — și nimeni altcineva.
     */
    public static function canAccess(): bool
    {
        $user = auth('web')->user();

        return $user !== null && ($user->canManageAccounts() || $user->canViewAuditLog());
    }

    /**
     * Rolurile, în ordinea din enum (de la conducere spre familie).
     *
     * @return list<array{value: string, label: string}>
     */
    public function roles(): array
    {
        return array_map(fn (UserRole $role): array => [
            'value' => $role->value,
            'label' => $role->label(),
        ], UserRole::cases());
    }

    /**
     * Matricea: o linie per capabilitate, o coloană per rol.
     *
     * Fiecare celulă se obține CHEMÂND metoda pe un User în memorie căruia i s-a atribuit rolul —
     * nu dintr-o listă paralelă. Utilizatorul nu se salvează nicăieri.
     *
     * @return list<array{name: string, label: string, roles: array<string, bool>}>
     */
    public function capabilities(): array
    {
        $probes = $this->probeUsers();
        $rows = [];

        foreach ($this->capabilityMethods() as $method) {
            $cells = [];

            foreach ($probes as $roleValue => $probe) {
                $cells[$roleValue] = (bool) $probe->{$method}();
            }

            $rows[] = [
                'name' => $method,
                'label' => $this->humanize($method),
                'roles' => $cells,
            ];
        }

        return $rows;
    }

    /**
     * Metodele-capabilitate ale lui User: publice, fără parametri, cu return `bool`, al căror nume
     * începe cu `can`. Filtrul pe PARAMETRI e esențial — `canAccessPanel(Panel)` sau
     * `handlesAudienceDomain(Domain)` depind de un context pe care matricea nu-l are, iar a le
     * chema cu o valoare inventată ar produce o coloană care arată sigură și e falsă.
     *
     * @return list<string>
     */
    private function capabilityMethods(): array
    {
        $methods = [];

        foreach ((new ReflectionClass(User::class))->getMethods(ReflectionMethod::IS_PUBLIC) as $method) {
            if ($method->getNumberOfParameters() > 0 || $method->isStatic()) {
                continue;
            }

            if (! str_starts_with($method->getName(), 'can')
                || str_starts_with($method->getName(), self::IDENTITY_PREFIX)) {
                continue;
            }

            $type = $method->getReturnType();

            if ($type === null || (string) $type !== 'bool') {
                continue;
            }

            $methods[] = $method->getName();
        }

        sort($methods);

        return $methods;
    }

    /**
     * Câte un User NESALVAT per rol, folosit doar ca sondă pentru capabilități.
     *
     * @return array<string, User>
     */
    private function probeUsers(): array
    {
        $probes = [];

        foreach (UserRole::cases() as $role) {
            $user = new User;
            // `setRelation`, nu `assignRole`: rolul se atașează în memorie, fără nicio scriere în
            // baza de date. O pagină de consultare nu are voie să creeze conturi ca efect secundar.
            $user->setRelation('roles', collect([$this->roleRecord($role)])->filter());

            $probes[$role->value] = $user;
        }

        return $probes;
    }

    private function roleRecord(UserRole $role): ?object
    {
        /** @var class-string<Model> $model */
        $model = config('permission.models.role');

        return $model::query()->where('name', $role->value)->where('guard_name', 'web')->first();
    }

    /**
     * `canApproveGradeCorrections` → eticheta tradusă, dacă există; altfel numele descompus în
     * cuvinte. Fără traducere, o capabilitate NOUĂ apare oricum în tabel (lizibil, dar netradus) —
     * mai bine decât să lipsească.
     */
    private function humanize(string $method): string
    {
        $key = 'panel.role_matrix.capabilities.'.$method;
        $translated = __($key);

        if (is_string($translated) && $translated !== $key) {
            return $translated;
        }

        return ucfirst(strtolower(trim((string) preg_replace('/(?<!^)[A-Z]/', ' $0', $method))));
    }
}
