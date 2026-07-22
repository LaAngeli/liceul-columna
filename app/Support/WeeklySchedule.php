<?php

namespace App\Support;

use App\Console\Commands\ImportLessonsFromSchedules;
use App\Enums\Weekday;
use App\Models\Lesson;
use App\Models\SchoolClass;
use App\Models\Subject;

/**
 * Orarul săptămânal al unei clase, NORMALIZAT pentru cabinet: o singură formă (sloturi + celule cu
 * SEGMENTE structurate) indiferent de sursă — orarul PUBLICAT (bogat: intervale orare + programul
 * întreg al zilei, dar celule de text liber) sau orarul STRUCTURAT (Lesson — fallback).
 *
 * Spre deosebire de importul structurat ({@see ImportLessonsFromSchedules},
 * care refuză tot ce nu e CERT — corect pentru calcul), parserul de aici e de AFIȘARE: nu pierde
 * nimic. O celulă ca „Limba engleză , gr.1, Popa N. Limba engleză , gr.2, Buga A." devine două
 * segmente {disciplină, grupă, profesor}; ce nu se poate segmenta rămâne un segment întreg, cu
 * textul original — mai puțin structurat, dar niciodată absent.
 */
class WeeklySchedule
{
    /**
     * Denumiri colocviale din orare care NU sunt fișe în nomenclator, dar marchează începutul unui
     * segment. Importul structurat le tratează diferit (are nevoie de un ID cert); afișarea are
     * nevoie doar de GRANIȚĂ. „Limba engleză" e exemplul-cheie: în nomenclator există doar „Limba
     * străină 1 (engleza)"/„Limba engleză (opț)", dar orarele scriu simplu „Limba engleză".
     *
     * @var list<string>
     */
    private const EXTRA_NAMES = [
        'limba engleză',
        'limba franceză',
        'limba germană',
        'limba română',
    ];

    /** @var list<string>|null vocabularul de segmentare (normalizat, cele mai lungi întâi) — cache per request */
    private ?array $vocabulary = null;

    /**
     * Forma: {source: published|structured, label, days: [{value,label,short}], slots: [{number,
     * time, label, kind, uniform, cells: zi → {segments: [{subject,teacher,group}], room, raw}}]}.
     *
     * @return array<string, mixed>|null
     */
    public function forClass(SchoolClass $class): ?array
    {
        $schedule = $class->lessonsSchedule;

        if ($schedule !== null && $schedule->is_public && $schedule->rows !== []) {
            return $this->fromPublished($class, $schedule->label, array_values($schedule->headers), $schedule->rows);
        }

        return $this->fromStructured($class);
    }

    /**
     * Sursa PUBLICATĂ: tabelul întreținut de administrația operațională (intervale orare reale +
     * programul complet al zilei — pauze, plimbări, program prelungit). Celulele sunt text liber →
     * fiecare trece prin parser. Sala lipsește de regulă din text → se completează din orarul
     * STRUCTURAT (Lesson), cheia (zi, nr. lecție) fiind deterministă, nu o potrivire fuzzy.
     *
     * @param  list<string>  $headers
     * @param  array<int, array<int, string>>  $rows
     * @return array<string, mixed>
     */
    private function fromPublished(SchoolClass $class, string $label, array $headers, array $rows): array
    {
        $dayColumns = $this->dayColumns($headers);

        // Sălile din orarul structurat, cheiate (zi-nr) — un singur query pentru toată grila.
        $rooms = Lesson::query()
            ->where('school_class_id', $class->id)
            ->whereNotNull('room')
            ->get(['day_of_week', 'lesson_number', 'room'])
            ->mapWithKeys(fn (Lesson $lesson): array => [
                $lesson->day_of_week->value.'-'.$lesson->lesson_number => (string) $lesson->room,
            ])
            ->all();

        $slots = [];

        foreach ($rows as $row) {
            $row = array_values($row);
            $rawLabel = trim((string) ($row[0] ?? ''));

            $number = preg_match('/^Lec[țţ]ia\s+(\d+)/iu', $rawLabel, $m) === 1 ? (int) $m[1] : null;
            $time = preg_match('/(\d{1,2}[.:]\d{2})\s*[–—-]\s*(\d{1,2}[.:]\d{2})/u', $rawLabel, $t) === 1
                ? $t[1].'–'.$t[2]
                : null;

            $cells = [];
            foreach ($dayColumns as $column => $weekday) {
                $raw = trim((string) ($row[$column] ?? ''));
                $cell = $raw !== '' ? $this->parseCell($raw) : null;

                // Sala din structurat — doar unde textul publicat nu o poartă deja.
                if ($cell !== null && $cell['room'] === null && $number !== null) {
                    $cell['room'] = $rooms[$weekday->value.'-'.$number] ?? null;
                }

                $cells[$weekday->value] = $cell;
            }

            // Rând-activitate identic pe toate zilele („Plimbări, jocuri", „PTA 1") → o singură
            // bandă (colspan în UI), nu cinci celule cu același text.
            $uniform = null;
            if ($number === null && $cells !== []) {
                $raws = array_map(fn (?array $cell): ?string => $cell['raw'] ?? null, $cells);
                $unique = array_unique($raws);
                if (count($unique) === 1 && ($first = reset($unique)) !== null && $first !== '') {
                    $uniform = reset($cells);
                    $cells = [];
                }
            }

            $slots[] = [
                'number' => $number,
                'time' => $time,
                'label' => $rawLabel,
                'kind' => $number !== null ? 'lesson' : 'activity',
                'uniform' => $uniform,
                // Chei = ziua ISO (1..6, ne-secvențiale) → JSON obiect; golit la uniform.
                'cells' => $cells,
            ];
        }

        $days = [];
        foreach ($dayColumns as $weekday) {
            $days[$weekday->value] = ['value' => $weekday->value, 'label' => $weekday->label(), 'short' => $weekday->short()];
        }
        ksort($days);

        return [
            'source' => 'published',
            'label' => ContentTranslator::string($label),
            'days' => array_values($days),
            'slots' => $slots,
        ];
    }

    /**
     * Fallback: orarul STRUCTURAT (Lesson) — grila pe (zi, nr. lecție) cu disciplină/profesor/sală
     * din nomenclator, dar FĂRĂ intervale orare (modelul nu le are încă) și fără programul zilei.
     *
     * @return array<string, mixed>|null
     */
    private function fromStructured(SchoolClass $class): ?array
    {
        $lessons = Lesson::query()
            ->where('school_class_id', $class->id)
            ->with(['subject', 'teacher'])
            ->orderBy('day_of_week')
            ->orderBy('lesson_number')
            ->get();

        if ($lessons->isEmpty()) {
            return null;
        }

        $byNumber = [];
        $daysPresent = [];

        foreach ($lessons as $lesson) {
            $day = $lesson->day_of_week->value;
            $daysPresent[$day] = true;

            $subject = $lesson->subject !== null ? ContentTranslator::subject($lesson->subject->name) : '';
            $byNumber[$lesson->lesson_number][$day] = [
                'segments' => [[
                    'subject' => $subject,
                    'teacher' => $lesson->teacher?->full_name,
                    'group' => null,
                ]],
                'room' => $lesson->room,
                // `??` are semantica isset — lanțul e sigur și fără nullsafe.
                'raw' => trim($subject.' '.($lesson->teacher->full_name ?? '')),
            ];
        }

        // Coloane: Luni–Vineri mereu; Sâmbăta doar dacă are lecții (aceeași regulă ca vechiul grid).
        $days = [];
        foreach (Weekday::cases() as $weekday) {
            if ($weekday->value <= 5 || isset($daysPresent[$weekday->value])) {
                $days[] = ['value' => $weekday->value, 'label' => $weekday->label(), 'short' => $weekday->short()];
            }
        }

        ksort($byNumber);
        $slots = [];
        foreach ($byNumber as $number => $cells) {
            $slots[] = [
                'number' => $number,
                'time' => null,
                'label' => (string) $number,
                'kind' => 'lesson',
                'uniform' => null,
                'cells' => $cells,
            ];
        }

        return [
            'source' => 'structured',
            'label' => null,
            'days' => $days,
            'slots' => $slots,
        ];
    }

    /**
     * O celulă de text liber → segmente structurate. Granițele = aparițiile numelor de discipline
     * (vocabular: nomenclator + denumiri colocviale), astfel „Limba engleză , gr.1, Popa N. Limba
     * engleză , gr.2, Buga A." se taie ÎNAINTEA fiecărei discipline. În fiecare segment se extrag
     * apoi sala „(s. NN)", grupa „gr.N" și profesorul terminal („Nume V."); restul e disciplina.
     * Fără nicio graniță → un singur segment (activitățile: „Plimbări, jocuri", „PTA 1").
     *
     * @return array{segments: list<array{subject: string, teacher: string|null, group: string|null}>, room: string|null, raw: string}
     */
    public function parseCell(string $raw): array
    {
        // NBSP-urile (frecvente în orarele migrate din WordPress) nu sunt \s pentru PCRE fără UCP —
        // le aducem la spațiu normal; diacriticele legacy cu sedilă → forma standard (afișare).
        $raw = strtr($raw, ["\u{00A0}" => ' ', 'ş' => 'ș', 'ţ' => 'ț', 'Ş' => 'Ș', 'Ţ' => 'Ț']);
        $clean = trim(preg_replace('/\s+/u', ' ', $raw) ?? $raw);
        $normalized = self::normalize($clean);

        // Granițele segmentelor: pozițiile (în OCTEȚI — normalize păstrează lungimea) unde începe
        // un nume de disciplină. Scanare care CONSUMĂ numele găsit, cel mai lung întâi.
        $starts = [];
        $length = strlen($normalized);
        $position = 0;

        while ($position < $length) {
            $found = null;
            foreach ($this->vocabulary() as $name) {
                if (str_starts_with(substr($normalized, $position), $name)) {
                    $found = $name;
                    break; // sortate descrescător după lungime — primul e cel mai lung
                }
            }

            if ($found === null) {
                $position++;

                continue;
            }

            $starts[] = $position;
            $position += strlen($found);
        }

        if ($starts === []) {
            $starts = [0];
        }
        // Prefixul dinaintea primei discipline (dacă există) aparține primului segment.
        $starts[0] = 0;

        $segments = [];
        $room = null;

        foreach ($starts as $index => $start) {
            $end = $starts[$index + 1] ?? strlen($clean);
            $slice = trim(substr($clean, $start, $end - $start));

            if ($slice === '') {
                continue;
            }

            $segment = $this->parseSegment($slice);
            $room ??= $segment['room'];
            $segments[] = ['subject' => $segment['subject'], 'teacher' => $segment['teacher'], 'group' => $segment['group']];
        }

        if ($segments === []) {
            $segments[] = ['subject' => $clean, 'teacher' => null, 'group' => null];
        }

        return ['segments' => $segments, 'room' => $room, 'raw' => $clean];
    }

    /**
     * Un segment → părțile lui. Ordinea extragerii contează: sala și grupa au tipare fără
     * ambiguitate; profesorul se ia DOAR de la coada segmentului („Nume V." — un cuvânt cu
     * majusculă + inițială cu punct), ca „Plimbări, jocuri" să nu piardă nimic.
     *
     * @return array{subject: string, teacher: string|null, group: string|null, room: string|null}
     */
    private function parseSegment(string $slice): array
    {
        $room = null;
        if (preg_match('/\(s\.\s*([^)]+)\)/u', $slice, $m) === 1) {
            $room = trim($m[1]);
            $slice = str_replace($m[0], ' ', $slice);
        }

        $group = null;
        if (preg_match('/\bgr\.?\s*(\d+|[IVX]+)\b\.?/iu', $slice, $m) === 1) {
            $group = 'gr. '.$m[1];
            $slice = str_replace($m[0], ' ', $slice);
        }

        $teacher = null;
        // La COADĂ: EXACT un cuvânt-nume (majusculă la început; interiorul permite orice literă —
        // compusele „Bujor-Cobili" au majusculă și după cratimă) + inițiala prescurtată cu punct
        // („V.", „Iu.", „Gh."). Un singur cuvânt, nu „1+": lăcomia pe mai multe cuvinte înghițea
        // disciplina cu majusculă în numele profesorului („Istorie Bujor-Cobili C.").
        if (preg_match('/(?:^|[\s,])(\p{Lu}[\p{L}\'’\-]+\s+\p{Lu}\p{Ll}{0,2}\.)\s*$/u', $slice, $m) === 1) {
            $teacher = trim($m[1]);
            $slice = mb_substr($slice, 0, mb_strlen($slice) - mb_strlen($m[0]));
        } elseif (preg_match('/,\s*(\p{Lu}[\p{L}\'’\-]+\.)\s*$/u', $slice, $m) === 1) {
            // Forma fără inițială, doar numele cu punct („Informatică , gr.1, Iurco.") — acceptată
            // DOAR după virgulă: fără această ancoră, o disciplină terminată în cuvânt cu majusculă
            // ar fi luată drept profesor.
            $teacher = trim($m[1]);
            $slice = mb_substr($slice, 0, mb_strlen($slice) - mb_strlen($m[0]));
        }

        // Disciplina = ce a rămas, curățat de virgulele-separator orfane.
        $parts = array_values(array_filter(
            array_map(trim(...), explode(',', $slice)),
            fn (string $part): bool => $part !== '',
        ));
        $subject = implode(', ', $parts);

        return [
            'subject' => ContentTranslator::subject($subject),
            'teacher' => $teacher,
            'group' => $group,
            'room' => $room,
        ];
    }

    /**
     * Coloanele-zi din antet: index → Weekday (match pe NUMELE zilei, nu pozițional).
     *
     * @param  list<string>  $headers
     * @return array<int, Weekday>
     */
    private function dayColumns(array $headers): array
    {
        $map = [
            'luni' => Weekday::Monday,
            'marți' => Weekday::Tuesday,
            'miercuri' => Weekday::Wednesday,
            'joi' => Weekday::Thursday,
            'vineri' => Weekday::Friday,
            'sâmbătă' => Weekday::Saturday,
        ];

        $days = [];
        foreach ($headers as $index => $header) {
            $normalized = self::normalize(trim($header));
            if (isset($map[$normalized])) {
                $days[$index] = $map[$normalized];
            }
        }

        return $days;
    }

    /**
     * Vocabularul de segmentare: numele din nomenclator + denumirile colocviale, normalizate,
     * cele mai LUNGI întâi (prefix-match corect: „Educație fizică" înaintea lui „Fizică").
     *
     * @return list<string>
     */
    private function vocabulary(): array
    {
        if ($this->vocabulary !== null) {
            return $this->vocabulary;
        }

        $names = Subject::query()
            ->pluck('name')
            ->map(fn (string $name): string => self::normalize($name))
            ->merge(self::EXTRA_NAMES)
            ->unique()
            ->values()
            ->all();

        usort($names, fn (string $a, string $b): int => strlen($b) <=> strlen($a));

        return $this->vocabulary = $names;
    }

    /** Diacritice legacy cu sedilă → forma standard + minuscule (byte-length identic în UTF-8). */
    private static function normalize(string $text): string
    {
        return mb_strtolower(strtr($text, ['ş' => 'ș', 'ţ' => 'ț', 'Ş' => 'Ș', 'Ţ' => 'Ț']));
    }
}
