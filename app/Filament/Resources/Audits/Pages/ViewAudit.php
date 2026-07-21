<?php

namespace App\Filament\Resources\Audits\Pages;

use App\Enums\UserRole;
use App\Filament\Resources\Audits\AuditResource;
use App\Models\Audit;
use App\Support\AuditCategories;
use App\Support\SchoolCalendar;
use Filament\Resources\Pages\ViewRecord;
use Illuminate\Database\Eloquent\Model;
use Throwable;

/**
 * FIȘA unei intrări de jurnal — locul INVESTIGĂRII (cerința beneficiarului, 2026-07-21): tot
 * contextul unui eveniment se citește AICI, fără a părăsi modulul — cine (utilizator + rol),
 * când (ora școlii + UTC), ce (eveniment + severitate + modul + obiect, descris nu doar ca id),
 * diff-ul vechi→nou pe câmpuri, contextul tehnic (IP, dispozitiv, URL — ca TEXT, nu link) și
 * rezultatul. Valorile absente = „n/a", nu erori. Fișa NU navighează nicăieri: jurnalul e un
 * instrument de trasabilitate, nu un meniu.
 *
 * @property Audit $record
 */
class ViewAudit extends ViewRecord
{
    protected static string $resource = AuditResource::class;

    protected string $view = 'filament.administration.audit-details';

    public function getTitle(): string
    {
        return __('panel.audit_view.title', ['id' => $this->record->getKey()]);
    }

    /**
     * CINE: numele + rolul actorului. Rolul e cel CURENT al contului (jurnalul nu stochează
     * rolul de la momentul acțiunii — limitare spusă onest prin eticheta „rol actual").
     *
     * @return array{name: string, role: string|null, deleted: bool}
     */
    public function actor(): array
    {
        $user = $this->record->user;

        if ($user === null) {
            // user_id null = acțiune de sistem (comenzi, scheduler); user_id orfan = cont șters.
            return [
                'name' => $this->record->user_id === null
                    ? (string) __('panel.common.system')
                    : (string) __('panel.audit_view.deleted_user'),
                'role' => null,
                'deleted' => $this->record->user_id !== null,
            ];
        }

        $roleName = null;

        try {
            $rawRole = $user->roles->first()?->name;
            $roleName = $rawRole !== null
                ? (UserRole::tryFrom($rawRole)?->label() ?? $rawRole)
                : null;
        } catch (Throwable) {
            $roleName = null;
        }

        return [
            'name' => (string) $user->name,
            'role' => $roleName,
            'deleted' => false,
        ];
    }

    /**
     * CÂND: momentul pe ora școlii (investigatorul gândește local) + UTC-ul brut (corelare cu
     * loguri de server).
     *
     * @return array{local: string, utc: string}
     */
    public function moment(): array
    {
        $createdAt = $this->record->created_at;

        return [
            'local' => SchoolCalendar::local($createdAt)?->format('d.m.Y H:i:s') ?? 'n/a',
            'utc' => $createdAt !== null ? $createdAt->format('Y-m-d H:i:s').' UTC' : 'n/a',
        ];
    }

    /**
     * OBIECTUL: modulul (categoria), tipul, id-ul și — când obiectul mai există — o descriere
     * lizibilă (nume/titlu), rezolvată DEFENSIV: orice eșec devine n/a, nu eroare. Fără link.
     *
     * @return array{category: string, type: string, id: string, label: string|null, gone: bool}
     */
    public function subject(): array
    {
        $label = null;
        $gone = false;

        try {
            $type = $this->record->auditable_type;

            if (class_exists($type)) {
                /** @var Model|null $model */
                $model = $type::query()
                    ->withoutGlobalScopes()
                    ->whereKey($this->record->auditable_id)
                    ->first();

                if ($model === null) {
                    $gone = true;
                } else {
                    foreach (['full_name', 'name', 'title', 'subject', 'topic', 'label'] as $attribute) {
                        $value = $model->getAttribute($attribute);

                        if (is_string($value) && $value !== '') {
                            $label = $value;
                            break;
                        }
                    }
                }
            }
        } catch (Throwable) {
            $label = null;
        }

        return [
            'category' => AuditCategories::label(
                AuditCategories::categoryOf((string) $this->record->auditable_type),
            ),
            'type' => $this->record->auditableLabel(),
            'id' => (string) ($this->record->auditable_id ?? 'n/a'),
            'label' => $label,
            'gone' => $gone,
        ];
    }

    /**
     * DIFF-ul vechi→nou, pe câmpuri: reuniunea cheilor din old/new, valorile formatate lizibil
     * (bool → Da/Nu, null/lipsă → n/a, structuri → JSON). Accesele (viewed/exported) au doar
     * `detaliu` — afișat ca CONTEXT, nu ca diff.
     *
     * @return list<array{field: string, old: string|null, new: string|null}>
     */
    public function changes(): array
    {
        $old = is_array($this->record->old_values) ? $this->record->old_values : [];
        $new = is_array($this->record->new_values) ? $this->record->new_values : [];

        $rows = [];

        foreach (array_unique([...array_keys($old), ...array_keys($new)]) as $field) {
            $rows[] = [
                'field' => (string) $field,
                'old' => array_key_exists($field, $old) ? $this->formatValue($old[$field]) : null,
                'new' => array_key_exists($field, $new) ? $this->formatValue($new[$field]) : null,
            ];
        }

        return $rows;
    }

    /** Contextul acceselor (viewed/exported): ce anume s-a consultat/exportat. */
    public function accessContext(): ?string
    {
        if (! in_array($this->record->event, ['viewed', 'exported'], true)) {
            return null;
        }

        $new = is_array($this->record->new_values) ? $this->record->new_values : [];
        $detail = $new['detaliu'] ?? null;

        return is_string($detail) && $detail !== '' ? $detail : null;
    }

    /**
     * Contextul TEHNIC — totul ca text, cu n/a la lipsă. URL-ul e informație, nu navigație.
     *
     * @return array{ip: string, device: string, agent: string|null, url: string}
     */
    public function technical(): array
    {
        $device = $this->record->deviceLabel();

        return [
            'ip' => $this->record->ip_address ?? 'n/a',
            'device' => $device ?? 'n/a',
            'agent' => $this->record->user_agent,
            'url' => $this->record->url ?? 'n/a',
        ];
    }

    public function severityLabel(): string
    {
        return (string) __('panel.audit_view.severity.'.$this->record->severity());
    }

    private function formatValue(mixed $value): string
    {
        return match (true) {
            $value === null => 'n/a',
            is_bool($value) => (string) __($value ? 'panel.common.yes' : 'panel.common.no'),
            is_array($value) => (string) json_encode($value, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT),
            default => (string) $value,
        };
    }
}
