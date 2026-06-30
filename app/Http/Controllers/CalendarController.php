<?php

namespace App\Http\Controllers;

use App\Enums\ScheduleType;
use App\Models\Schedule;
use App\Support\ContentTranslator;
use Inertia\Inertia;
use Inertia\Response;

class CalendarController extends Controller
{
    /**
     * „Calendar și orare" — explorator interactiv dedicat (NU mai trece prin `public/page`).
     * Trimite toate cele 9 tipuri de orar cu tabelele lor PUBLICE (read-only, fără PII — date
     * la nivel de CLASĂ; vezi {@see Schedule::publicTablesFor()}), ca frontend-ul să comute
     * tipul/clasa instant, fără navigare. Paginile individuale `/orarul-*` rămân (URL-uri păstrate).
     */
    public function index(): Response
    {
        $i18n = [
            'orarul-lectiilor' => 'lessons',
            'orarul-sunetelor' => 'bells',
            'orarul-examenelor' => 'exams',
            'orarul-ess' => 'ess',
            'orarul-pretestarilor' => 'pretests',
            'cursuri-de-pregatire-pentru-examene' => 'prep',
            'orarul-cpae' => 'cpae',
            'orar-recuperari' => 'recovery',
            'sedintele-cu-parintii' => 'meetings',
        ];

        $types = [];
        foreach (ScheduleType::cases() as $type) {
            $tables = Schedule::publicTablesFor($type->value);
            $types[] = [
                'key' => $type->value,
                'i18n' => $i18n[$type->value],
                'label' => $type->label(),
                'count' => count($tables),
                'tables' => $tables,
            ];
        }

        return Inertia::render('public/calendar', [
            'title' => ContentTranslator::string('Calendar și orare'),
            'description' => ContentTranslator::string('Toate orarele și programele Liceului „Columna" într-un singur loc.'),
            'breadcrumbs' => [['title' => ContentTranslator::string('Calendar și orare')]],
            'scheduleTypes' => $types,
        ]);
    }
}
