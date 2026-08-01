<?php

namespace App\Models;

use Database\Factories\CanteenMenuFactory;
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
