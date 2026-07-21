<?php

namespace App\Models;

use Illuminate\Support\Facades\Lang;
use LogicException;
use OwenIt\Auditing\Models\Audit as BaseAudit;

/**
 * Extinde modelul de audit owen-it cu etichete traduse + ajutoare de INVESTIGARE pentru fișa
 * din panou (spec §7 / L133): severitate derivată, dispozitiv din user agent, rezultat.
 * Aceeași tabelă `audits` — scrierea rămâne a pachetului.
 *
 * IMUABIL prin instanțe de model: jurnalul nu se editează și nu se șterge prin nicio cale de
 * aplicație ({@see booted}). Retenția (12 ani, L133) și curățarea demo folosesc query builder
 * din CONSOLĂ — deliberat în afara acestei gărzi.
 *
 * @property string $event
 * @property string $auditable_type
 * @property int|null $user_id
 * @property array<string, mixed>|null $old_values
 * @property array<string, mixed>|null $new_values
 * @property string|null $ip_address
 * @property string|null $user_agent
 */
class Audit extends BaseAudit
{
    /**
     * Jurnalul e doar-scriere (append-only): orice update sau ștergere PRIN INSTANȚĂ e un bug
     * sau o tentativă — oprită la model, nu doar în UI.
     */
    protected static function booted(): void
    {
        static::updating(static function (): never {
            throw new LogicException('Jurnalul de audit este imuabil — intrările nu se editează.');
        });

        static::deleting(static function (): never {
            throw new LogicException('Jurnalul de audit este imuabil — intrările nu se șterg prin aplicație.');
        });
    }

    /**
     * Eticheta tradusă a tipului de date auditat (din clasa modelului). TOATE modelele Auditable
     * au cheie în `panel.audit_types` (garantat de test); un tip nou fără cheie cade pe numele
     * clasei — vizibil, nu ascuns.
     */
    public function auditableLabel(): string
    {
        return self::labelForType($this->auditable_type);
    }

    /** Aceeași etichetă, pentru un tip dat (opțiunile filtrelor din viewer). */
    public static function labelForType(string $auditableType): string
    {
        $key = 'panel.audit_types.'.class_basename($auditableType);

        return Lang::has($key) ? (string) trans($key) : class_basename($auditableType);
    }

    /**
     * Eticheta tradusă a evenimentului (inclusiv accesul: vizualizare/export, spec §7).
     */
    public function eventLabel(): string
    {
        return self::eventLabelFor($this->event);
    }

    /**
     * Aceeași etichetă, pentru un eveniment dat — varianta STATICĂ, folosită acolo unde avem doar
     * valoarea coloanei (`formatStateUsing`), nu instanța: nu depinde de clasa hidratată, deci nu
     * se rupe dacă relația întoarce modelul pachetului în loc de al nostru.
     */
    public static function eventLabelFor(string $event): string
    {
        $key = 'panel.tables.audits.event_'.$event;

        return Lang::has($key) ? (string) trans($key) : $event;
    }

    /**
     * Severitatea DERIVATĂ din eveniment (predictibilă, nu configurabilă): ștergerile = critice;
     * modificările/exporturile/restaurările = importante; creările/consultările = informative.
     * Sursă unică pentru badge-ul fișei ȘI filtrul de severitate (aceeași hartă în ambele).
     */
    public function severity(): string
    {
        return self::severityForEvent($this->event);
    }

    public static function severityForEvent(string $event): string
    {
        return match ($event) {
            'deleted', 'forceDeleted' => 'danger',
            'updated', 'exported', 'restored' => 'warning',
            default => 'info',
        };
    }

    /**
     * Evenimentele fiecărei trepte de severitate — pentru filtrul din tabel (whereIn), în
     * oglindă cu {@see severityForEvent}.
     *
     * @return array<string, list<string>>
     */
    public static function severityMap(): array
    {
        return [
            'danger' => ['deleted', 'forceDeleted'],
            'warning' => ['updated', 'exported', 'restored'],
            'info' => ['created', 'viewed'],
        ];
    }

    /**
     * Dispozitivul, DERIVAT din user agent (euristică minimă, fără dependență nouă): browser + OS.
     * n/a când user agent-ul lipsește (acțiuni de consolă/sistem).
     */
    public function deviceLabel(): ?string
    {
        $agent = $this->user_agent;

        if ($agent === null || $agent === '') {
            return null;
        }

        $browser = match (true) {
            str_contains($agent, 'Edg/') => 'Edge',
            str_contains($agent, 'OPR/') => 'Opera',
            str_contains($agent, 'Firefox/') => 'Firefox',
            str_contains($agent, 'Chrome/') => 'Chrome',
            str_contains($agent, 'Safari/') => 'Safari',
            default => null,
        };

        $os = match (true) {
            str_contains($agent, 'Windows') => 'Windows',
            str_contains($agent, 'Android') => 'Android',
            str_contains($agent, 'iPhone') || str_contains($agent, 'iPad') => 'iOS',
            str_contains($agent, 'Mac OS') => 'macOS',
            str_contains($agent, 'Linux') => 'Linux',
            default => null,
        };

        return match (true) {
            $browser !== null && $os !== null => $browser.' · '.$os,
            $browser !== null => $browser,
            $os !== null => $os,
            default => mb_substr($agent, 0, 60),
        };
    }

    /**
     * Rezultatul acțiunii. Jurnalul owen-it consemnează DOAR operațiuni care s-au produs
     * (scrieri persistate, accese efectuate) — deci rezultatul e „consemnată"; tentativele
     * eșuate nu ajung aici (limitare de arhitectură, spusă onest în fișă).
     */
    public function resultLabel(): string
    {
        return (string) __('panel.audit_view.result_recorded');
    }
}
