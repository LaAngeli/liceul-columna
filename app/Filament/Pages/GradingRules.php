<?php

namespace App\Filament\Pages;

use App\Actions\ComputeTermAverage;
use App\Enums\EvaluationType;
use App\Enums\SchoolCycle;
use App\Support\Grades;
use BackedEnum;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;

/**
 * Regulile după care se calculează mediile — VIZIBILE, dar nu editabile.
 *
 * De ce read-only: formula NU e o configurare, e legislație (§2.4) implementată în
 * {@see ComputeTermAverage}. Un ecran care ar lăsa-o modificată din panou ar însemna
 * că mediile deja calculate și cele viitoare pot ajunge să urmeze reguli diferite, fără nicio urmă
 * în catalog. (Capabilitatea `canChangeAveragingFormula()` exista în cod, dar nu garda nimic —
 * promitea un drept fără punct de aplicare; a fost eliminată, iar adevărul stă acum aici.)
 *
 * De ce o pagină totuși: până acum regulile trăiau doar în cod și în specificație. Dirigintele care
 * explică unui părinte de ce media semestrială e 7,46 și nu 7,5 nu are unde să se uite. Valorile de
 * mai jos se CITESC din aceleași constante folosite la calcul ({@see Grades}, {@see EvaluationType})
 * — un text paralel s-ar fi desincronizat la prima schimbare a formulei.
 */
class GradingRules extends Page
{
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCalculator;

    protected static ?int $navigationSort = 46;

    protected static ?string $slug = 'reguli-de-notare';

    protected string $view = 'filament.catalog.grading-rules';

    public static function getNavigationGroup(): ?string
    {
        return __('panel.nav.groups.configuration');
    }

    public static function getNavigationLabel(): string
    {
        return __('panel.grading_rules.title');
    }

    public function getTitle(): string
    {
        return __('panel.grading_rules.title');
    }

    public function getSubheading(): ?string
    {
        return __('panel.grading_rules.subtitle');
    }

    /**
     * Oricine consultă catalogul: regulile de calcul nu sunt un secret operațional, iar cine vede
     * o medie are nevoie să știe cum s-a obținut. Rămâne închisă doar administratorului tehnic,
     * care n-are acces la date academice (§3.2).
     */
    public static function canAccess(): bool
    {
        $user = auth('web')->user();

        return $user !== null && ! $user->isTechnicalAdmin();
    }

    /**
     * Formula pe fiecare ciclu, cu ponderea CITITĂ din enum. Ciclul primar nu are sumativă: media
     * semestrială e chiar media notelor curente.
     *
     * @return array<int, array{cycle: string, grades: string, formula: string, note: string}>
     */
    public function cycleRules(): array
    {
        $weight = EvaluationType::Teza->weight() ?? 0.5;
        $percent = (int) round($weight * 100);

        return [
            [
                'cycle' => SchoolCycle::Primar->label(),
                'grades' => 'I–IV',
                'formula' => __('panel.grading_rules.formula_primary'),
                'note' => __('panel.grading_rules.note_primary'),
            ],
            [
                'cycle' => SchoolCycle::Gimnaziu->label(),
                'grades' => 'V–IX',
                'formula' => __('panel.grading_rules.formula_summative', ['percent' => $percent]),
                'note' => __('panel.grading_rules.note_summative'),
            ],
            [
                'cycle' => SchoolCycle::Liceu->label(),
                'grades' => 'X–XII',
                'formula' => __('panel.grading_rules.formula_summative', ['percent' => $percent]),
                'note' => __('panel.grading_rules.note_summative'),
            ],
        ];
    }

    /**
     * Tipurile de evaluare și rolul lor în calcul, direct din enum.
     *
     * @return array<int, array{label: string, role: string}>
     */
    public function evaluationTypes(): array
    {
        return array_map(fn (EvaluationType $type): array => [
            'label' => $type->label(),
            'role' => $type->weight() === null
                ? (string) __('panel.grading_rules.role_current')
                : (string) __('panel.grading_rules.role_summative', ['percent' => (int) round($type->weight() * 100)]),
        ], EvaluationType::cases());
    }

    /**
     * Regulile care se aplică indiferent de ciclu, cu valorile citite din {@see Grades}.
     *
     * @return array<int, array{title: string, body: string}>
     */
    public function commonRules(): array
    {
        return [
            [
                'title' => (string) __('panel.grading_rules.truncation_title'),
                // Exemplul se CALCULEAZĂ cu funcția reală: dacă regula de trunchiere s-ar schimba
                // vreodată, exemplul de pe pagină se schimbă odată cu ea, nu rămâne să mintă.
                'body' => (string) __('panel.grading_rules.truncation_body', [
                    'raw' => '8,567',
                    'result' => number_format(Grades::truncate2(8.567), 2, ',', ''),
                ]),
            ],
            [
                'title' => (string) __('panel.grading_rules.pass_title'),
                'body' => (string) __('panel.grading_rules.pass_body', [
                    'threshold' => number_format(Grades::PASS, 2, ',', ''),
                ]),
            ],
            [
                'title' => (string) __('panel.grading_rules.annulment_title'),
                'body' => (string) __('panel.grading_rules.annulment_body'),
            ],
        ];
    }
}
