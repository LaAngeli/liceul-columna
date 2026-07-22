<?php

namespace App\Models;

use App\Enums\CalendarAudienceReach;
use App\Enums\CalendarEventScope;
use App\Enums\CalendarEventType;
use App\Observers\CalendarEventObserver;
use Database\Factories\CalendarEventFactory;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;
use OwenIt\Auditing\Auditable as AuditableTrait;
use OwenIt\Auditing\Contracts\Auditable;

/**
 * Eveniment de calendar MANUAL (modul Calendar v2), creat de personal. Vizibilitatea către familii e
 * dată de scope (global / treaptă / clasă). Titlul/descrierea: RO pe model, RU/EN în
 * {@see CalendarEventTranslation} cu fallback RO. Modificările sunt auditate; crearea/anularea
 * notifică familiile din scope ({@see CalendarEventObserver}).
 *
 * @property CalendarEventType $type
 * @property CalendarEventScope $visibility_scope
 * @property int|null $grade_level
 * @property int|null $school_class_id
 * @property CalendarAudienceReach|null $audience_reach
 * @property string $title
 * @property string|null $description
 * @property Carbon $starts_on
 * @property Carbon|null $ends_on
 * @property string|null $start_time
 * @property int|null $created_by
 */
#[ObservedBy(CalendarEventObserver::class)]
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
        'audience_reach',
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
            'audience_reach' => CalendarAudienceReach::class,
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

    /**
     * Elevii vizați NOMINAL (audiența „elevi anume"). Gol pentru celelalte scope-uri.
     *
     * @return BelongsToMany<Student, $this>
     */
    public function students(): BelongsToMany
    {
        return $this->belongsToMany(Student::class);
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
     * Verificare per-înregistrare: vede clasa dată acest eveniment? (sursa logicii de vizibilitate
     * pe audiențele largi). Evenimentele NOMINALE (elevi anume) NU se rezolvă pe clasă — vezi
     * {@see reachIncludes()} + relația {@see students()}.
     */
    public function isVisibleToClass(?SchoolClass $class): bool
    {
        if ($this->visibility_scope === CalendarEventScope::Global) {
            return true;
        }

        if ($this->visibility_scope === CalendarEventScope::Students) {
            return false;
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
     * Un eveniment nominal, cu un elev vizat: îl vede persoana care privește? `asGuardian` = true
     * pentru contul de părinte, false pentru contul propriu al elevului. `reach` null (nu ar trebui
     * pe nominal) degradează la „ambii văd", ca să nu ascundem tăcut un eveniment prost salvat.
     */
    public function reachIncludes(bool $asGuardian): bool
    {
        $reach = $this->audience_reach ?? CalendarAudienceReach::Both;

        return $asGuardian ? $reach->includesGuardians() : $reach->includesStudent();
    }

    /**
     * Filtru SQL: audiențele LARGI vizibile clasei date (global ∪ treapta ∪ clasa). NU include
     * evenimentele nominale — acelea au calea lor ({@see scopeNominalForStudent}).
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

    /**
     * Filtru SQL: evenimentele NOMINALE care vizează elevul dat. Reach-ul (elev/părinți) se
     * aplică deasupra, la citire ({@see reachIncludes}), în funcție de cine privește.
     *
     * @param  Builder<CalendarEvent>  $query
     */
    public function scopeNominalForStudent(Builder $query, int $studentId): void
    {
        $query->where('visibility_scope', CalendarEventScope::Students->value)
            ->whereHas('students', fn (Builder $inner): Builder => $inner->whereKey($studentId));
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
