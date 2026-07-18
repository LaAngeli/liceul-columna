<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

/**
 * Documentele GENERATE per-elev (spec §1/§3): produse LA CERERE din datele catalogului, mereu
 * actualizate, exportabile PDF, cu gardurile de acces aplicate (familia doar pentru copilul propriu).
 * Spre deosebire de biblioteca STATICĂ ({@see Document}), nu se stochează — se regenerează la fiecare
 * descărcare. Modulul „Documente utile" le prezintă unitar alături de cele statice.
 */
enum GeneratedDocumentType: string implements HasLabel
{
    case Transcript = 'transcript';        // Foaia matricolă (istoricul pe trepte, bilingvă RO/EN)
    case TermSituation = 'term_situation'; // Situația școlară — semestrul curent
    case StudentFile = 'student_file';     // Dosarul elevului (situația curentă + evoluția pe ani)

    public function getLabel(): string
    {
        return (string) trans('enums.generated_document_type.'.$this->value.'.label');
    }

    public function description(): string
    {
        return (string) trans('enums.generated_document_type.'.$this->value.'.description');
    }

    /** Categoria sub care apare în biblioteca unitară (toate sunt rapoarte). */
    public function category(): DocumentCategory
    {
        return DocumentCategory::Reports;
    }

    public function icon(): string
    {
        return match ($this) {
            self::Transcript => 'heroicon-o-rectangle-stack',
            self::TermSituation => 'heroicon-o-document-chart-bar',
            self::StudentFile => 'heroicon-o-folder-open',
        };
    }

    /** Șablonul Blade folosit la randarea PDF-ului. */
    public function blade(): string
    {
        return match ($this) {
            self::Transcript => 'pdf.documents.transcript',
            self::TermSituation => 'pdf.documents.term-situation',
            self::StudentFile => 'pdf.documents.student-file',
        };
    }

    /** Prefix pentru numele fișierului descărcat (se adaugă numele elevului). */
    public function fileBase(): string
    {
        return match ($this) {
            self::Transcript => 'foaia-matricola',
            self::TermSituation => 'situatia-scolara',
            self::StudentFile => 'dosarul-elevului',
        };
    }
}
