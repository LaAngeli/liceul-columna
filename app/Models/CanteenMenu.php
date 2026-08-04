<?php

namespace App\Models;

use App\Providers\AppServiceProvider;
use App\Support\CanteenWeek;
use App\Support\SchoolCalendar;
use Carbon\CarbonImmutable;
use Database\Factories\CanteenMenuFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use OwenIt\Auditing\Auditable as AuditableTrait;
use OwenIt\Auditing\Contracts\Auditable;

/**
 * Meniul cantinei pentru O zi (cerință 2026-08-01). Se scrie EXCLUSIV de administratorul
 * operațional (super-adminul păstrează break-glass — {@see User::canManageCanteenMenu}) și se
 * consultă de toți utilizatorii: personalul în panou, familia în cabinet. Fără cache pe citire —
 * orice salvare se vede instant peste tot.
 *
 * Auditable ca orarele publicabile: e conținut văzut de toată platforma, deci modificările lasă urmă.
 *
 * @property Carbon $menu_date
 */
class CanteenMenu extends Model implements Auditable
{
    use AuditableTrait;

    /** @use HasFactory<CanteenMenuFactory> */
    use HasFactory;

    protected $fillable = [
        'menu_date',
        'breakfast_main',
        'breakfast_fruit',
        'breakfast_bakery',
        'breakfast_drink',
        'lunch_first',
        'lunch_second',
        'lunch_side',
        'lunch_salad',
        'lunch_drink',
        'lunch_fruit',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'menu_date' => 'date',
        ];
    }

    /**
     * ZIUA din lună de la care meniul lunii URMĂTOARE devine public (cerința beneficiarului,
     * 04.08.2026): meniul se introduce oricând, dar se arată abia când școala e gata să-l anunțe.
     */
    public const PUBLISH_DAY = 25;

    /**
     * FEREASTRA PUBLICĂ, la data dată: de la 1 ale lunii curente până la finalul ei — plus toată
     * luna următoare, din ziua de {@see PUBLISH_DAY} încolo.
     *
     * De aici rezultă, fără nicio altă regulă, ambele cerințe:
     *  • meniul lunii viitoare NU apare mai devreme de 25 ale lunii curente;
     *  • meniul lunii precedente dispare de la sine pe 1 ale lunii noi (capătul de jos urcă),
     *    iar luna în curs rămâne vizibilă până la ultima ei zi — familia are ce mânca și pe 26.
     *
     * @return array{0: Carbon, 1: Carbon}
     */
    public static function publicWindow(?Carbon $today = null): array
    {
        $today ??= SchoolCalendar::localNow();

        $start = $today->copy()->startOfMonth()->startOfDay();

        $end = $today->day >= self::PUBLISH_DAY
            ? $today->copy()->addMonthNoOverflow()->endOfMonth()->endOfDay()
            : $today->copy()->endOfMonth()->endOfDay();

        return [$start, $end];
    }

    /**
     * Data la care meniul ACESTA devine public: ziua 25 a lunii dinaintea lui.
     *
     * Tip IMUTABIL, nu `Illuminate\Support\Carbon`: aplicația rulează cu
     * `Date::use(CarbonImmutable::class)` ({@see AppServiceProvider}), deci
     * `menu_date` e imutabilă, iar orice derivare din ea la fel.
     */
    public function publishedOn(): CarbonImmutable
    {
        return $this->menu_date->copy()->startOfMonth()->subMonthNoOverflow()->setDay(self::PUBLISH_DAY)->startOfDay();
    }

    /** E în fereastra publică acum? (fals și pentru viitorul nepublicat, și pentru arhivă) */
    public function isPublished(?Carbon $today = null): bool
    {
        [$start, $end] = self::publicWindow($today);

        return $this->menu_date->betweenIncluded($start, $end);
    }

    /**
     * Restrânge la ce POATE vedea privitorul curent. Administratorul operațional (și super-adminul,
     * break-glass) văd tot — ei scriu meniul și trebuie să-l pregătească înainte de publicare, plus
     * să consulte arhiva. Restul personalului și familia văd doar fereastra publică.
     *
     * Scope-ul citește privitorul din `auth` — la fel ca gărzile de catalog — ca să nu fie nevoie
     * să circule un parametru prin toate cele patru interogări din {@see CanteenWeek}.
     *
     * @param  Builder<CanteenMenu>  $query
     * @return Builder<CanteenMenu>
     */
    public function scopeVisible(Builder $query): Builder
    {
        if (auth('web')->user()?->canManageCanteenMenu() ?? false) {
            return $query;
        }

        return $query->whereBetween('menu_date', self::publicWindow());
    }

    /**
     * Câmpurile dejunului, în ordinea meniului oficial — sursa UNICĂ pentru afișare (panou +
     * cabinet folosesc aceeași listă, deci nu pot diverge în structură).
     *
     * @return list<string>
     */
    public static function breakfastFields(): array
    {
        return ['breakfast_main', 'breakfast_fruit', 'breakfast_bakery', 'breakfast_drink'];
    }

    /**
     * Câmpurile prânzului, în ordinea meniului oficial.
     *
     * @return list<string>
     */
    public static function lunchFields(): array
    {
        return ['lunch_first', 'lunch_second', 'lunch_side', 'lunch_salad', 'lunch_drink', 'lunch_fruit'];
    }
}
