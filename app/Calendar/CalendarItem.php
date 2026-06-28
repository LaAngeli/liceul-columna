<?php

namespace App\Calendar;

use App\Enums\CalendarCategory;

/**
 * Eveniment normalizat de calendar produs de un proiector. IMUTABIL și fără obiecte Eloquent — doar
 * scalari + un `deepLink` — ca să fie serializabil/cache-uibil fără a căra relații ascunse sau PII
 * neintenționat. `studentId` marchează proprietarul PII (null = eveniment global/non-PII).
 */
final readonly class CalendarItem
{
    /**
     * @param  string  $id  identificator stabil, ex. „homework:123"
     * @param  string  $source  modulul-sursă, ex. „homework"
     * @param  string  $date  ziua de afișare (Y-m-d)
     * @param  string|null  $startTime  HH:MM dacă evenimentul are oră (orar); null = toată ziua
     * @param  string|null  $deepLink  rută către modulul-sursă
     * @param  int|null  $studentId  proprietarul PII; null pentru evenimente globale
     * @param  array<string, scalar|null>  $meta
     */
    public function __construct(
        public string $id,
        public string $source,
        public CalendarCategory $category,
        public string $title,
        public string $date,
        public bool $allDay = true,
        public ?string $startTime = null,
        public ?string $endTime = null,
        public ?string $deepLink = null,
        public ?int $studentId = null,
        public bool $editable = false,
        public array $meta = [],
    ) {}

    /**
     * Formă pentru frontend. NU expune `studentId` (câmp intern de scoping).
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'source' => $this->source,
            'category' => $this->category->value,
            'color' => $this->category->color(),
            'title' => $this->title,
            'date' => $this->date,
            'allDay' => $this->allDay,
            'startTime' => $this->startTime,
            'endTime' => $this->endTime,
            'deepLink' => $this->deepLink,
            'editable' => $this->editable,
            'meta' => $this->meta,
        ];
    }
}
