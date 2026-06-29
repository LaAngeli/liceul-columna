<?php

namespace App\Models;

use App\Enums\ScheduleType;
use Database\Factories\ScheduleFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Cache;

/**
 * Un tabel de orar publicabil (spec §2.1). Sursa UNICĂ: editat în panou, citit read-only pe site.
 * Pentru orarul „lecții", `school_class_id` leagă opțional tabelul de clasa reală (canonizare orar).
 *
 * @property ScheduleType $type
 * @property string $label
 * @property int|null $school_class_id
 * @property array<int, string> $headers
 * @property array<int, array<int, string>> $rows
 * @property bool $is_public
 */
class Schedule extends Model
{
    /** @use HasFactory<ScheduleFactory> */
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'type',
        'label',
        'school_class_id',
        'headers',
        'rows',
        'position',
        'is_public',
    ];

    protected function casts(): array
    {
        return [
            'type' => ScheduleType::class,
            'headers' => 'array',
            'rows' => 'array',
            'is_public' => 'boolean',
        ];
    }

    protected static function booted(): void
    {
        $forget = static function (Schedule $schedule): void {
            Cache::forget(self::cacheKey($schedule->type->value));
        };

        static::saved($forget);
        static::deleted($forget);
        static::restored($forget);
        static::forceDeleted($forget);
    }

    /**
     * @param  Builder<Schedule>  $query
     */
    public function scopePublic(Builder $query): void
    {
        $query->where('is_public', true);
    }

    /**
     * Clasa reală a orarului (pentru tipul „lecții"); null pentru orarele globale (sunete, examene…).
     *
     * @return BelongsTo<SchoolClass, $this>
     */
    public function schoolClass(): BelongsTo
    {
        return $this->belongsTo(SchoolClass::class);
    }

    /**
     * Tabelele PUBLICE pentru un tip, în forma {label, headers, rows} — whitelist de câmpuri
     * (niciodată coloane interne) + cache invalidat la editare. Folosit DOAR pentru citirea
     * publică de pe site (gardul de securitate: read-only, doar `is_public`).
     *
     * @return list<array{label: string, headers: list<string>, rows: list<list<string>>}>
     */
    public static function publicTablesFor(string $type): array
    {
        return Cache::rememberForever(self::cacheKey($type), static function () use ($type): array {
            $tables = self::query()
                ->where('type', $type)
                ->where('is_public', true)
                ->orderBy('position')
                ->orderBy('id')
                ->get()
                ->map(static fn (Schedule $schedule): array => [
                    'label' => $schedule->label,
                    'headers' => array_values($schedule->headers),
                    'rows' => array_values(array_map(
                        static fn (array $row): array => array_values($row),
                        $schedule->rows,
                    )),
                ])
                ->all();

            return array_values($tables);
        });
    }

    private static function cacheKey(string $type): string
    {
        return "schedules.public.{$type}";
    }
}
