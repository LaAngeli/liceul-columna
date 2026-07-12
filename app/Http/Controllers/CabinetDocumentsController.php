<?php

namespace App\Http\Controllers;

use App\Enums\DocumentCategory;
use App\Enums\DocumentSource;
use App\Enums\GeneratedDocumentType;
use App\Models\Document;
use App\Models\DocumentRequest;
use App\Models\Student;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Pagina „Documente" din cabinetul familiei (spec §2/§3 — Elev/Părinte). Prezintă UNITAR:
 *   • documentele ȘCOLII (statice, publice sau vizibile rolului familiei) — descărcabile;
 *   • documentele COPILULUI (generate la cerere: foaia matricolă, situația școlară) + cererile depuse.
 *
 * Gardul „doar familie" e aplicat de middleware-ul EnsureFamilyCabinet pe rută; accesul la fiecare
 * document e RE-verificat pe server la descărcare/generare (§1).
 */
class CabinetDocumentsController extends Controller
{
    public function index(Request $request): Response
    {
        $user = $request->user('web');

        return Inertia::render('cabinet/documents', [
            // Toate cele 5 subcategorii — pentru taburi mereu prezente (badge 0 când sunt goale).
            'categories' => array_map(
                fn (DocumentCategory $category): array => ['key' => $category->value, 'label' => $category->getLabel()],
                DocumentCategory::cases(),
            ),
            'schoolDocuments' => $this->schoolDocuments($user),
            'children' => $this->familyStudents($user)
                ->map(fn (Student $student): array => [
                    'id' => $student->id,
                    'name' => $student->full_name,
                    'class' => $this->className($student),
                    'generated' => $this->generatedDescriptors($student),
                    'requests' => $this->requestsFor($student),
                    // Totalul REAL — lista de mai sus e plafonată la 15, deci badge-ul nu se
                    // calculează din ea (ar minți la a 16-a cerere).
                    'requestsTotal' => DocumentRequest::query()->where('student_id', $student->id)->count(),
                ])
                ->all(),
        ]);
    }

    /**
     * Documentele statice ale școlii vizibile utilizatorului (public + rolul familiei), grupate pe
     * categorie. Scoping-ul de acces e impus pe server prin {@see Document::applyVisibility}.
     *
     * @return array<int, array{category: string, label: string, items: array<int, array<string, mixed>>}>
     */
    private function schoolDocuments(User $user): array
    {
        return Document::applyVisibility(Document::query(), $user)
            ->where('source', DocumentSource::Static->value)
            ->orderBy('title')
            ->get()
            ->groupBy(fn (Document $document): string => $document->category->value)
            ->map(fn (Collection $items, string $category): array => [
                'category' => $category,
                'label' => DocumentCategory::from($category)->getLabel(),
                'items' => $items->map(fn (Document $document): array => [
                    'id' => $document->id,
                    'title' => $document->title,
                    'description' => $document->description,
                    'version' => $document->version,
                    'size' => $document->formattedSize(),
                    'url' => $document->file_path !== null ? route('documents.download', $document) : null,
                ])->values()->all(),
            ])
            ->values()
            ->all();
    }

    /**
     * Documentele generate disponibile pentru un elev (link de producere la cerere).
     *
     * @return list<array{key: string, label: string, description: string, url: string}>
     */
    private function generatedDescriptors(Student $student): array
    {
        return array_map(
            fn (GeneratedDocumentType $type): array => [
                'key' => $type->value,
                'label' => $type->getLabel(),
                'description' => $type->description(),
                'url' => route('cabinet.document.generate', ['student' => $student->id, 'type' => $type->value]),
            ],
            GeneratedDocumentType::cases(),
        );
    }

    /**
     * Cererile tipice depuse pentru un elev (cu PDF descărcabil când e generat).
     *
     * @return array<int, array<string, mixed>>
     */
    private function requestsFor(Student $student): array
    {
        return DocumentRequest::query()
            ->where('student_id', $student->id)
            ->latest()
            ->limit(15)
            ->get()
            ->map(fn (DocumentRequest $request): array => [
                'id' => $request->id,
                'type' => $request->type->label(),
                'date' => $request->created_at?->format('d.m.Y'),
                'statusLabel' => $request->status->label(),
                'url' => $request->pdf_path !== null ? route('cabinet.requests.pdf', $request) : null,
            ])
            ->all();
    }

    /**
     * Elevii familiei: elevul însuși (dacă e cont de elev) + copiii tutorelui.
     *
     * @return Collection<int, Student>
     */
    private function familyStudents(User $user): Collection
    {
        $students = new Collection;

        $self = Student::query()->where('user_id', $user->id)->first();
        if ($self !== null) {
            $students->push($self);
        }

        foreach ($user->students()->get() as $child) {
            $students->push($child);
        }

        return $students->unique('id')->values();
    }

    private function className(Student $student): ?string
    {
        $class = $student->currentSchoolClass();

        return $class !== null ? trim($class->name.' '.($class->section ?? '')) : null;
    }
}
