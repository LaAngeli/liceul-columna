<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Traducerea RU/EN a unui eveniment de calendar. Câmpuri nullable → fallback la sursa RO de pe
 * {@see CalendarEvent}. Oglindește PostTranslation.
 *
 * @property string $locale
 * @property string|null $title
 * @property string|null $description
 */
class CalendarEventTranslation extends Model
{
    protected $fillable = [
        'calendar_event_id',
        'locale',
        'title',
        'description',
    ];

    /** @return BelongsTo<CalendarEvent, $this> */
    public function calendarEvent(): BelongsTo
    {
        return $this->belongsTo(CalendarEvent::class);
    }
}
