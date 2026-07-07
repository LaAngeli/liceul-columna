<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

/**
 * Categoriile modulului „Documente utile" (spec §2 — anexa tehnică): dimensiunea de organizare a
 * bibliotecii, sub care se grupează documentele. Eticheta RO/RU/EN vine din `enums.document_category.*`;
 * culoarea/iconița sunt chei semantice mapate pe paleta de brand (navy/verde + accente).
 */
enum DocumentCategory: string implements HasLabel
{
    case Reports = 'reports';    // Rapoarte
    case Requests = 'requests';  // Cereri
    case Notices = 'notices';    // Înștiințări
    case Forms = 'forms';        // Formulare
    case Useful = 'useful';      // Utile

    public function getLabel(): string
    {
        return (string) trans('enums.document_category.'.$this->value);
    }

    /** Culoare de badge (Filament) — semantică, aliniată temei. */
    public function color(): string
    {
        return match ($this) {
            self::Reports => 'info',
            self::Requests => 'primary',
            self::Notices => 'warning',
            self::Forms => 'gray',
            self::Useful => 'success',
        };
    }

    /** Iconiță Heroicon per categorie (folosită în grupare + badge). */
    public function icon(): string
    {
        return match ($this) {
            self::Reports => 'heroicon-o-document-chart-bar',
            self::Requests => 'heroicon-o-inbox-arrow-down',
            self::Notices => 'heroicon-o-bell-alert',
            self::Forms => 'heroicon-o-clipboard-document-list',
            self::Useful => 'heroicon-o-book-open',
        };
    }
}
