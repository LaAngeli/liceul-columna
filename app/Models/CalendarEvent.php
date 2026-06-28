<?php

namespace App\Models;

use App\Enums\CalendarEventScope;
use App\Enums\CalendarEventType;
use Database\Factories\CalendarEventFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;
use OwenIt\Auditing\Auditable as AuditableTrait;
use OwenIt\Auditing\Contracts\Auditable;

/**
 * Eveniment de calendar MANUAL (modul Calendar v2), creat de personal. Vizibilitatea către familii e
 * dată de scope (global / treaptă / clasă). Titlul/descrierea: RO pe model, RU/EN în
 * {@see CalendarEventTranslation} cu fallback RO. Modificările sunt auditate.
 *
 * @property CalendarEventType $type
 * @property CalendarEventScope $visibility_scope
 * @property int|null $grade_level
 * @property int|null $school_class_id
 * @property string $title
 * @property string|null $description
 * @property Carbon $starts_on
 * @property Carbon|null $ends_on
 * @property string|null $start_time
 * @property int|null $created_by
 */
class CalendarEvent extends Model implements Auditable
{
    use AuditableTrait;

    /** @use HasFactory<CalendarEventFactory> */
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'type',
        'visibility_scope',
        'grade_level',
        'school_class_id',
        'title',
        'description',
        'starts_on',
        'ends_on',
        'start_time',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'type' => CalendarEventType::class,
            'visibility_scope' => CalendarEventScope::class,
            'grade_level' => 'integer',
            'starts_on' => 'date',
            'ends_on' => 'date',
        ];
    }

    /** @return HasMany<CalendarEventTranslation, $this> */
    public function translations(): HasMany
    {
        return $this->hasMany(CalendarEventTranslation::class);
    }

    /** @return BelongsTo<SchoolClass, $this> */
    public function schoolClass(): BelongsTo
    {
        return $this->belongsTo(SchoolClass::class);
    }

    /** @return BelongsTo<User, $this> */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function localizedTitle(?string $locale = null): string
    {
        $value = $this->translationFor($locale)?->title;

        return $value !== null && $value !== '' ? $value : $this->title;
    }

    public function localizedDescription(?string $locale = null): ?string
    {
        $value = $this->translationFor($locale)?->description;

        return $value !== null && $value !== '' ? $value : $this->description;
    }

    /**
     * Verificare per-înregistrare: vede clasa dată acest eveniment? (sursa logicii de vizibilitate)
     */
    public function isVisibleToClass(?SchoolClass $class): bool
    {
        if ($this->visibility_scope === CalendarEventScope::Global) {
            return true;
        }

        if ($class === null) {
            return false;
        }

        if ($this->visibility_scope === CalendarEventScope::GradeLevel) {
            return $this->grade_level === $class->grade_level;
        }

        return $this->school_class_id === $class->id;
    }

    /**
     * Filtru SQL echivalent: evenimentele vizibile clasei date (global ∪ treapta ∪ clasa).
     *
     * @param  Builder<CalendarEvent>  $query
     */
    public function scopeVisibleToClass(Builder $query, ?SchoolClass $class): void
    {
        $query->where(function (Builder $inner) use ($class): void {
            $inner->where('visibility_scope', CalendarEventScope::Global->value);

            if ($class !== null) {
                $inner->orWhere(fn (Builder $sub): Builder => $sub
                    ->where('visibility_scope', CalendarEventScope::GradeLevel->value)
                    ->where('grade_level', $class->grade_level))
                    ->orWhere(fn (Builder $sub): Builder => $sub
                        ->where('visibility_scope', CalendarEventScope::SchoolClass->value)
                        ->where('school_class_id', $class->id));
            }
        });
    }

    private function translationFor(?string $locale): ?CalendarEventTranslation
    {
        $locale ??= app()->getLocale();

        if ($locale === 'ro') {
            return null;
        }

        return $this->translations->firstWhere('locale', $locale);
    }
}
