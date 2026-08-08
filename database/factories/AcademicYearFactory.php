<?php

namespace Database\Factories;

use App\Models\AcademicYear;
use Illuminate\Database\Eloquent\Factories\Factory;
use RuntimeException;

/**
 * @extends Factory<AcademicYear>
 */
class AcademicYearFactory extends Factory
{
    protected $model = AcademicYear::class;

    /**
     * Primul an de start emis de factory. Plaja 2500+ îi aparține EXCLUSIV: testele fixează
     * manual doar ani „plauzibili" (azi 2010–2041 și 2097–2100), deci un nume generat nu se
     * poate ciocni nici de un an fixat DUPĂ ce factory-ul a rulat — fereastra pe care varianta
     * aleatoare (2000–2090 + gardă pe DB) nu o putea închide: garda nu are de unde ști ce va
     * fixa testul ulterior. Cine hardcodează un an nou într-un test rămâne SUB 2500.
     */
    private const int FIRST_START_YEAR = 2500;

    /**
     * Ultimul an de start emis în procesul curent. Avansează monoton peste toate testele
     * (RefreshDatabase golește baza, contorul nu se resetează — e sursă de unicitate, nu stare),
     * astfel apelurile în lot (count(N), relații imbricate) primesc ani diferiți chiar dacă
     * toate definition() rulează înainte de primul INSERT, unde garda pe DB nu vede nimic.
     */
    protected static int $lastStartYear = self::FIRST_START_YEAR - 1;

    public function definition(): array
    {
        // Plasă pentru nume fixate manual chiar în plaja factory-ului (azi: niciunul).
        // `withTrashed`: indexul unic vede și rândurile șterse logic.
        $taken = AcademicYear::query()->withTrashed()->pluck('name')->all();

        do {
            $start = ++static::$lastStartYear;
        } while (in_array(AcademicYear::canonicalName($start), $taken, true));

        if ($start > 9998) {
            // Formatul canonic (4 cifre) și DATE-ul MySQL se opresc la 9999 — ends_on ar ieși din plajă.
            throw new RuntimeException('AcademicYearFactory a epuizat plaja de ani de start (2500–9998) în acest proces.');
        }

        return [
            'name' => AcademicYear::canonicalName($start),
            'starts_on' => $start.'-09-01',
            'ends_on' => ($start + 1).'-06-30',
            'is_current' => false,
        ];
    }
}
