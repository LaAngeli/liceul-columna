<?php

namespace App\Filament\Resources\Students\Schemas;

use App\Enums\SchoolCycle;
use App\Enums\SecondLanguage;
use App\Enums\Sex;
use App\Filament\Schemas\FicheAccountSection;
use App\Models\Student;
use Closure;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * Fișa elevului, RESTRUCTURATĂ (cerința beneficiarului, 2026-07-21): trei secțiuni logice
 * (date personale → date academice → cont & acces), câmpuri DEPENDENTE de structura reală a
 * școlii și cont gestionat de SISTEM, nu de un select mereu editabil.
 *
 * Structura lingvistică a școlii (verificată pe date, nu presupusă): ENGLEZA e limba străină 1,
 * obligatorie pentru TOȚI elevii și predată pe două grupe (clasa se împarte); germana/franceza
 * e limba străină 2, care începe din clasa a V-a (la primar nimeni nu o studiază) și devine
 * opțională la liceu. De aici dependențele: grupa există ORICÂND (dar e etichetată onest ca
 * grupa la L1), iar limba 2 se poate alege doar de la treapta a V-a în sus.
 */
class StudentForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make(__('panel.forms.student.section_personal'))
                    ->description(__('panel.forms.student.section_personal_hint'))
                    ->columns(3)
                    ->schema([
                        TextInput::make('last_name')
                            ->label(__('panel.fields.last_name'))
                            ->required()
                            ->maxLength(50),
                        TextInput::make('first_name')
                            ->label(__('panel.fields.first_name'))
                            ->required()
                            ->maxLength(50),
                        Select::make('sex')
                            ->label(__('panel.fields.sex'))
                            ->options(Sex::class)
                            ->native(false),
                    ]),

                Section::make(__('panel.forms.student.section_academic'))
                    ->description(__('panel.forms.student.section_academic_hint'))
                    ->columns(2)
                    ->schema([
                        // Clasa curentă — context READ-ONLY: dependența limbii 2 se explică prin ea.
                        Placeholder::make('clasa_curenta')
                            ->label(__('panel.forms.student.current_class'))
                            ->content(static function (?Model $record): string {
                                $class = $record instanceof Student ? $record->currentSchoolClass() : null;

                                if ($class === null) {
                                    return (string) __('panel.forms.student.no_current_class');
                                }

                                return $class->name.' — '.__('panel.forms.subject.grade_option', [
                                    'roman' => SchoolCycle::romanNumeral((int) $class->grade_level),
                                    'cycle' => SchoolCycle::fromGradeLevel((int) $class->grade_level)->label(),
                                ]);
                            })
                            ->columnSpanFull(),
                        TextInput::make('register_number')
                            ->label(__('panel.fields.register_number'))
                            ->maxLength(10)
                            ->helperText(__('panel.forms.student.register_number_hint'))
                            ->rules([
                                // UNICITATE la schimbare: numărul matricol identifică elevul în
                                // registru — un număr NOU sau MODIFICAT nu poate repeta unul
                                // existent. Duplicatele moștenite din legacy (29 azi) NU blochează
                                // salvările care nu ating câmpul — se corectează deliberat.
                                static fn (?Model $record): Closure => static function (string $attribute, mixed $value, Closure $fail) use ($record): void {
                                    $number = trim((string) $value);

                                    if ($number === '') {
                                        return;
                                    }

                                    $initial = $record instanceof Student ? (string) $record->getRawOriginal('register_number') : null;

                                    if ($record !== null && $number === $initial) {
                                        return;
                                    }

                                    $taken = Student::query()
                                        ->when($record !== null, fn (Builder $query) => $query->whereKeyNot($record->getKey()))
                                        ->where('register_number', $number)
                                        ->exists();

                                    if ($taken) {
                                        $fail(__('panel.validation.student.register_number_taken'));
                                    }
                                },
                            ]),
                        // Grupa la ENGLEZĂ (limba străină 1) — nu „încă o limbă": engleza e
                        // obligatorie pentru toți, clasa se împarte în două grupe la orele ei.
                        // Selector strict 1/2 (formularul vechi accepta și 3 — valoare inexistentă).
                        Select::make('english_group')
                            ->label(__('panel.forms.student.english_group_long'))
                            ->options([
                                1 => __('panel.forms.student.group_option', ['group' => 1]),
                                2 => __('panel.forms.student.group_option', ['group' => 2]),
                            ])
                            ->native(false)
                            ->helperText(__('panel.forms.student.english_group_hint')),
                        Select::make('second_language')
                            ->label(__('panel.forms.student.second_language'))
                            ->options(static fn (?Model $record): array => self::secondLanguageOptions($record))
                            ->default(SecondLanguage::None->value)
                            ->required()
                            ->native(false)
                            ->helperText(static fn (?Model $record): string => self::isPrimaryCycle($record)
                                ? (string) __('panel.forms.student.second_language_primary_hint')
                                : (string) __('panel.forms.student.second_language_hint'))
                            ->rules([
                                // Dublura pe SERVER a dependenței din UI: la ciclul primar limba 2
                                // nu se studiază — orice altă valoare e o configurare invalidă.
                                static fn (?Model $record): Closure => static function (string $attribute, mixed $value, Closure $fail) use ($record): void {
                                    $language = $value instanceof SecondLanguage ? $value : SecondLanguage::tryFrom((string) $value);

                                    if ($language !== null && $language !== SecondLanguage::None && self::isPrimaryCycle($record)) {
                                        $fail(__('panel.validation.student.second_language_primary'));
                                    }
                                },
                            ]),
                    ]),

                // Contul fișei: aceeași secțiune ca la profesor — starea la vedere, crearea
                // contului DIN fișă (fără re-tastarea datelor personale) și, ca supapă, legarea
                // unui cont orfan doar când există chiar unul potrivit.
                FicheAccountSection::make(),
            ]);
    }

    /**
     * Opțiunile limbii 2, DEPENDENTE de treapta clasei curente: la ciclul primar (I–IV) limba a
     * doua nu se studiază — singura valoare logică e „Nu studiază"; de la a V-a se alege.
     *
     * @return array<string, string>
     */
    private static function secondLanguageOptions(?Model $record): array
    {
        if (self::isPrimaryCycle($record)) {
            return [SecondLanguage::None->value => SecondLanguage::None->label()];
        }

        $options = [];

        foreach (SecondLanguage::cases() as $language) {
            $options[$language->value] = $language->label();
        }

        return $options;
    }

    /** Elevul e (cunoscut ca fiind) la ciclul primar? Fără clasă curentă → nu restrângem. */
    private static function isPrimaryCycle(?Model $record): bool
    {
        if (! $record instanceof Student) {
            return false;
        }

        // getAttribute (mixed): coloana e nullable în schemă chiar dacă phpdoc-ul modelului
        // promite int — o clasă fără treaptă nu trebuie să restrângă limba 2.
        $level = $record->currentSchoolClass()?->getAttribute('grade_level');

        return is_numeric($level) && (int) $level <= 4;
    }
}
