<?php

namespace App\Console\Commands;

use App\Enums\CorrectionStatus;
use App\Models\AbsenceMotivation;
use App\Models\Announcement;
use App\Models\Audit;
use App\Models\CalendarEvent;
use App\Models\CorigentaExam;
use App\Models\CorigentaSession;
use App\Models\Document;
use App\Models\ExamCommission;
use App\Models\Grade;
use App\Models\GradeCorrection;
use App\Models\Holiday;
use App\Models\HomeworkAssignment;
use App\Models\HomeworkCorrection;
use App\Models\Message;
use App\Models\User;
use App\Observers\AbsenceMotivationObserver;
use App\Observers\GradeObserver;
use App\Support\Holidays;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;

/**
 * Curăță DATELE demo/test care SUPRAVIEȚUIESC lui `app:demo-accounts --remove`.
 *
 * De ce e nevoie: ștergerea conturilor demo duce cu ea, prin FK `ON DELETE CASCADE`, doar mesajele,
 * stările lor, consimțămintele și cererile depuse. Restul referințelor către `users` sunt
 * `ON DELETE SET NULL` → rândurile RĂMÂN în producție, doar orfanizate (autorul devine NULL):
 * corecții de note, motivări de absențe, documente, evenimente de calendar. Fără această comandă,
 * zeci de rânduri de date fictive ar rămâne vizibile utilizatorilor REALI după go-live.
 *
 * ⚠️ ORDINEA CONTEAZĂ — se rulează ÎNAINTE de `app:demo-accounts --remove`, fiindcă are nevoie de
 * ID-urile conturilor demo ca să-și identifice datele. După ștergerea conturilor, autorul e deja
 * NULL și rândurile nu mai pot fi legate de un cont demo.
 *
 * Identificarea se face pe DOUĂ căi, fiindcă una singură nu ajunge: seeder-ul marchează textual
 * (`[DEMO]`), dar testarea manuală din UI a produs și date NEMARCATE — prinse doar după autor.
 *
 * Idempotentă. `--dry-run` raportează fără să șteargă nimic.
 */
class PurgeDemoData extends Command
{
    /** Marcajul textual pus de seedere/conturile demo. */
    private const DEMO = '[DEMO]';

    /** Marcajul notelor injectate manual la testarea din interfață. */
    private const TEST_UI = '[TEST UI]';

    protected $signature = 'app:purge-demo-data {--dry-run : Doar raportează ce ar șterge, fără a șterge}';

    protected $description = 'Șterge datele demo/test care supraviețuiesc lui app:demo-accounts --remove (corecții, motivări, documente, evenimente, note de test, mesaje).';

    public function handle(): int
    {
        $dryRun = (bool) $this->option('dry-run');

        /** @var Collection<int, int> $demoIds */
        $demoIds = User::query()
            ->where('name', 'like', self::DEMO.'%')
            ->pluck('id');

        if ($demoIds->isEmpty()) {
            $this->warn('Niciun cont demo ('.self::DEMO.') găsit.');
            $this->line('Dacă ai rulat deja `app:demo-accounts --remove`, autorii sunt NULL și datele orfane');
            $this->line('nu mai pot fi identificate după cont — rămâne doar curățarea după marcaj textual.');
            $this->line('Rulează ÎNTOTDEAUNA această comandă ÎNAINTE de ștergerea conturilor.');
        } else {
            $this->info($demoIds->count().' cont(uri) demo identificate.');
        }

        // ID-urile motivărilor demo, colectate ÎNAINTE de orice ștergere: notificările lor de verdict
        // ajung în inboxurile unor familii REALE și le referă prin `data->motivation_id` — după ce
        // rândul-motivare dispare, legătura nu mai poate fi refăcută (la fel ca la anunțuri).
        $demoMotivationIds = AbsenceMotivation::query()
            ->where(fn (Builder $inner) => $inner
                ->whereIn('requested_by_user_id', $demoIds)
                ->orWhereIn('reviewed_by_user_id', $demoIds)
                ->orWhere('reason', 'like', '%'.self::DEMO.'%'))
            ->pluck('id');

        $this->newLine();

        $rows = [
            ['Corecții de note', $this->purgeGradeCorrections($demoIds, $dryRun)],
            // Corecțiile ÎNAINTEA temelor: FK-ul e CASCADE, deci ștergerea temelor le-ar lua cu ea
            // și raportul ar arăta 0 pe un rând care de fapt a curățat.
            ['Corecții de teme', $this->purgeHomeworkCorrections($demoIds, $dryRun)],
            ['Teme [DEMO]', $this->purgeHomeworkAssignments($dryRun)],
            // Verdictele motivărilor demo din inboxurile REALE — ÎNAINTE de ștergerea motivărilor.
            ['Notificări de verdict al motivărilor (inboxuri reale)', $this->purgeMotivationNotifications($demoMotivationIds, $dryRun)],
            ['Motivări de absențe (+ justificative)', $this->purgeAbsenceMotivations($demoIds, $dryRun)],
            ['Documente (+ fișiere de pe disc)', $this->purgeDocuments($demoIds, $dryRun)],
            ['Evenimente de calendar', $this->purgeCalendarEvents($demoIds, $dryRun)],
            ['Zile libere [DEMO]', $this->purgeHolidays($dryRun)],
            // Examenele ÎNAINTEA sesiunilor și comisiilor: FK-urile sunt nullOnDelete — ștergerea
            // părinților întâi ar lăsa examene demo orfane (comisie/sesiune null), de negăsit.
            ['Examene de corigență [DEMO]', $this->purgeCorigentaExams($dryRun)],
            ['Sesiuni de corigență [DEMO]', $this->purgeCorigentaSessions($dryRun)],
            ['Comisii de examen [DEMO]', $this->purgeExamCommissions($dryRun)],
            // Notificările ÎNAINTEA anunțurilor: se identifică prin announcement_id, care dispare
            // odată cu rândurile-mamă.
            ['Notificări de anunț din inboxuri', $this->purgeAnnouncementNotifications($demoIds, $dryRun)],
            ['Anunțuri', $this->purgeAnnouncements($demoIds, $dryRun)],
            ['Note injectate la testare', $this->purgeTestGrades($dryRun)],
            ['Mesaje', $this->purgeMessages($demoIds, $dryRun)],
            // Restul inboxurilor conturilor demo + jurnalul lor de audit — ULTIMELE, cât conturile
            // demo încă există (autorul devine NULL la ștergerea lor).
            ['Notificări din inboxurile conturilor demo', $this->purgeDemoUserNotifications($demoIds, $dryRun)],
            ['Intrări de audit ale conturilor demo', $this->purgeDemoAudits($demoIds, $dryRun)],
        ];

        $this->table(['Date demo/test', $dryRun ? 'AR FI șterse' : 'Șterse'], $rows);

        $total = array_sum(array_column($rows, 1));

        if ($dryRun) {
            $this->warn("DRY-RUN — nimic nu a fost șters. Total identificat: {$total} rânduri.");
            $this->line('Rulează fără `--dry-run` pentru a curăța.');

            return self::SUCCESS;
        }

        $this->newLine();
        $this->info("Curățare finalizată — {$total} rânduri șterse.");
        $this->line('Pași rămași la go-live (ideal cât worker-ul de queue și SMTP sunt OPRITE, ca');
        $this->line('joburile de notificare demo să nu fie LIVRATE — inclusiv prin email — la ștergere):');
        $this->line('  1. php artisan queue:clear        # aruncă joburile de notificare demo nelivrate');
        $this->line('  2. php artisan app:demo-alerts --remove');
        $this->line('  3. php artisan app:demo-accounts --remove');
        $this->line('  ⤷ dacă un worker a livrat notificări între timp, reia app:purge-demo-data.');

        return self::SUCCESS;
    }

    /**
     * Corecțiile de note (FK `SET NULL` → ar supraviețui ștergerii conturilor).
     *
     * ⚠️ O corecție demo APROBATĂ a suprascris DEFINITIV valoarea unei note REALE (seeder-ul vechi
     * aproba, până la 2026-07-21) — ștergerea corecției NU restaura nota. Restaurăm nota la
     * `old_value` ÎNAINTE de ștergere (idempotent: doar dacă încă ține valoarea demo), ca purge să
     * reverseze COMPLET efectul. Salvarea prin model declanșează recalculul mediei
     * ({@see GradeObserver::saved}). Seeder-ul nou nu mai aprobă → cazul e istoric.
     *
     * @param  Collection<int, int>  $demoIds
     */
    private function purgeGradeCorrections(Collection $demoIds, bool $dryRun): int
    {
        $query = GradeCorrection::query()->where(function (Builder $inner) use ($demoIds): void {
            $inner->whereIn('requested_by_user_id', $demoIds)
                ->orWhereIn('reviewed_by_user_id', $demoIds)
                ->orWhere('reason', 'like', '%'.self::DEMO.'%');
        });

        $corrections = (clone $query)->with('grade')->get();

        if (! $dryRun && $corrections->isNotEmpty()) {
            foreach ($corrections as $correction) {
                $this->restoreGradeIfDemoApproved($correction);
            }

            $query->delete();
        }

        return $corrections->count();
    }

    /**
     * Restaurează nota la valoarea de dinaintea unei corecții demo APROBATE, dacă nota încă ține
     * valoarea injectată (nicio corecție reală n-a intervenit între timp).
     */
    private function restoreGradeIfDemoApproved(GradeCorrection $correction): void
    {
        if ($correction->status !== CorrectionStatus::Approved) {
            return;
        }

        $grade = $correction->grade;

        if ($grade === null
            || (string) $grade->value !== (string) $correction->new_value
            || (string) $correction->new_value === (string) $correction->old_value) {
            return;
        }

        $grade->update([
            'value' => $correction->old_value,
            'calificativ' => $correction->old_calificativ,
        ]);
    }

    /**
     * Motivările de absențe + justificativele atașate (PII de minor — fișierul trebuie să dispară
     * odată cu rândul, invariantul „rând șters ⇒ fișier șters", L133).
     *
     * @param  Collection<int, int>  $demoIds
     */
    private function purgeAbsenceMotivations(Collection $demoIds, bool $dryRun): int
    {
        $query = AbsenceMotivation::query()->where(function (Builder $inner) use ($demoIds): void {
            $inner->whereIn('requested_by_user_id', $demoIds)
                ->orWhereIn('reviewed_by_user_id', $demoIds)
                ->orWhere('reason', 'like', '%'.self::DEMO.'%');
        });

        $motivations = (clone $query)->get();

        if (! $dryRun && $motivations->isNotEmpty()) {
            foreach ($motivations as $motivation) {
                $path = $motivation->document_path;

                if (is_string($path) && $path !== '') {
                    Storage::disk('local')->delete($path);
                }
            }

            $query->delete();
        }

        return $motivations->count();
    }

    /**
     * Documentele demo. `forceDelete()` pe MODEL declanșează hook-urile din {@see Document}
     * (`forceDeleting`/`forceDeleted`), care șterg de pe disc și fișierul curent, și fișierele
     * versiunilor arhivate — de aceea NU se șterge prin query builder.
     *
     * @param  Collection<int, int>  $demoIds
     */
    private function purgeDocuments(Collection $demoIds, bool $dryRun): int
    {
        $documents = Document::withTrashed()
            ->where(function (Builder $inner) use ($demoIds): void {
                $inner->whereIn('uploaded_by_user_id', $demoIds)
                    ->orWhere('title', 'like', self::DEMO.'%');
            })
            ->get();

        if (! $dryRun) {
            foreach ($documents as $document) {
                $document->forceDelete();
            }
        }

        return $documents->count();
    }

    /**
     * @param  Collection<int, int>  $demoIds
     */
    private function purgeCalendarEvents(Collection $demoIds, bool $dryRun): int
    {
        $query = CalendarEvent::withTrashed()->where(function (Builder $inner) use ($demoIds): void {
            $inner->whereIn('created_by', $demoIds)
                ->orWhere('title', 'like', '%'.self::DEMO.'%');
        });

        $count = (clone $query)->count();

        if (! $dryRun && $count > 0) {
            $query->forceDelete();
        }

        return $count;
    }

    /**
     * Corecțiile de teme cerute de conturile demo sau marcate [DEMO] în motiv. Modelul n-are
     * SoftDeletes — delete-ul e definitiv de la sine.
     *
     * @param  Collection<int, int>  $demoIds
     */
    private function purgeHomeworkCorrections(Collection $demoIds, bool $dryRun): int
    {
        $query = HomeworkCorrection::query()->where(function (Builder $inner) use ($demoIds): void {
            $inner->whereIn('requested_by_user_id', $demoIds)
                ->orWhere('reason', 'like', '%'.self::DEMO.'%');
        });

        $count = (clone $query)->count();

        if (! $dryRun && $count > 0) {
            $query->delete();
        }

        return $count;
    }

    /**
     * Temele-suport create pentru corecțiile demo (subiect marcat [DEMO]). `withTrashed()` +
     * `forceDelete()`: au SoftDeletes, iar una „ștearsă" în testare ar rămâne altfel în tabel.
     * NU se șterg pe autor: profesorul demo e legat de o fișă REALĂ de profesor, iar temele
     * legacy ale aceleiași fișe sunt date reale.
     */
    private function purgeHomeworkAssignments(bool $dryRun): int
    {
        $query = HomeworkAssignment::withTrashed()->where('topic', 'like', '%'.self::DEMO.'%');

        $count = (clone $query)->count();

        if (! $dryRun && $count > 0) {
            $query->forceDelete();
        }

        return $count;
    }

    /**
     * Notificările DIFUZATE pentru anunțurile demo, din inboxurile utilizatorilor REALI.
     *
     * Broadcast-ul COPIAZĂ titlul și corpul în payload-ul fiecărei notificări — ștergerea
     * anunțului nu le atinge, deci fără pasul acesta fiecare familie ar păstra în inbox, pe
     * termen nelimitat, un anunț „[DEMO]" orfan, de la un expeditor care nu mai există.
     *
     * @param  Collection<int, int>  $demoIds
     */
    private function purgeAnnouncementNotifications(Collection $demoIds, bool $dryRun): int
    {
        $announcementIds = $this->demoAnnouncements($demoIds)->pluck('id');

        $query = DatabaseNotification::query()->whereIn('data->announcement_id', $announcementIds);

        $count = (clone $query)->count();

        if (! $dryRun && $count > 0) {
            $query->delete();
        }

        return $count;
    }

    /**
     * Notificările de VERDICT ale motivărilor demo, din inboxurile utilizatorilor REALI (familii +
     * diriginți). La fel ca anunțurile, ele copiază textul în payload și poartă `motivation_id`
     * ({@see AbsenceMotivationObserver}) — ștergerea motivării nu le atinge, deci
     * fără pasul acesta o familie reală ar păstra în inbox un verdict despre o cerere demo pe care
     * n-a depus-o niciodată (fără marcaj [DEMO] în text → alarmant). ID-urile se colectează în
     * `handle()`, înainte de ștergerea motivărilor.
     *
     * @param  Collection<int, int>  $motivationIds
     */
    private function purgeMotivationNotifications(Collection $motivationIds, bool $dryRun): int
    {
        if ($motivationIds->isEmpty()) {
            return 0;
        }

        $query = DatabaseNotification::query()->whereIn('data->motivation_id', $motivationIds->all());

        $count = (clone $query)->count();

        if (! $dryRun && $count > 0) {
            $query->delete();
        }

        return $count;
    }

    /**
     * Restul notificărilor din inboxurile CONTURILOR DEMO (verdicte de corecții către profesorul
     * demo, mesaje, cereri rutate către dirigintele demo...). Tabelul `notifications` e polimorf,
     * FĂRĂ FK — ștergerea conturilor NU le duce cu ea, deci ar rămâne orfane. Se rulează ÎNAINTE de
     * `app:demo-accounts --remove`, cât `notifiable_id` încă indică un cont demo.
     *
     * @param  Collection<int, int>  $demoIds
     */
    private function purgeDemoUserNotifications(Collection $demoIds, bool $dryRun): int
    {
        if ($demoIds->isEmpty()) {
            return 0;
        }

        $query = DatabaseNotification::query()
            ->where('notifiable_type', User::class)
            ->whereIn('notifiable_id', $demoIds->all());

        $count = (clone $query)->count();

        if (! $dryRun && $count > 0) {
            $query->delete();
        }

        return $count;
    }

    /**
     * Intrările din jurnalul de audit generate de acțiunile conturilor demo (crearea/judecarea
     * corecțiilor și motivărilor demo trec toate printr-un cont [DEMO]). Fără curățare, ar rămâne
     * vizibile în „Jurnal de audit" după go-live, atribuite unui autor șters (`user_id` NULL). Se
     * rulează ÎNAINTE de ștergerea conturilor, cât `user_id` încă le leagă de un cont demo.
     *
     * @param  Collection<int, int>  $demoIds
     */
    private function purgeDemoAudits(Collection $demoIds, bool $dryRun): int
    {
        if ($demoIds->isEmpty()) {
            return 0;
        }

        $query = Audit::query()->whereIn('user_id', $demoIds->all());

        $count = (clone $query)->count();

        if (! $dryRun && $count > 0) {
            $query->delete();
        }

        return $count;
    }

    /**
     * @param  Collection<int, int>  $demoIds
     */
    private function purgeAnnouncements(Collection $demoIds, bool $dryRun): int
    {
        $query = $this->demoAnnouncements($demoIds);

        $count = (clone $query)->count();

        if (! $dryRun && $count > 0) {
            $query->forceDelete();
        }

        return $count;
    }

    /**
     * Anunțurile-țintă, o singură definiție pentru rândul de anunțuri ȘI pentru notificările lor.
     * `withTrashed()` + `forceDelete()`: Announcement e SoftDeletes, iar un `delete()` simplu ar
     * lăsa rândul în tabel (doar marcat) — adică exact ce încearcă comanda asta să prevină.
     *
     * @param  Collection<int, int>  $demoIds
     * @return Builder<Announcement>
     */
    private function demoAnnouncements(Collection $demoIds): Builder
    {
        return Announcement::withTrashed()->where(function (Builder $inner) use ($demoIds): void {
            $inner->whereIn('author_user_id', $demoIds)
                ->orWhere('title', 'like', '%'.self::DEMO.'%');
        });
    }

    /**
     * Examenele de corigență demo: cele din sesiunile [DEMO] sau alocate comisiilor [DEMO].
     * Se șterg ÎNAINTEA sesiunilor/comisiilor (FK nullOnDelete le-ar orfaniza tăcut).
     */
    private function purgeCorigentaExams(bool $dryRun): int
    {
        $sessionIds = CorigentaSession::query()->where('order_reference', 'like', self::DEMO.'%')->pluck('id');
        $commissionIds = ExamCommission::query()->where('name', 'like', self::DEMO.'%')->pluck('id');

        $query = CorigentaExam::query()->where(function (Builder $inner) use ($sessionIds, $commissionIds): void {
            $inner->whereIn('corigenta_session_id', $sessionIds)
                ->orWhereIn('exam_commission_id', $commissionIds);
        });

        $count = (clone $query)->count();

        if (! $dryRun && $count > 0) {
            $query->delete();
        }

        return $count;
    }

    /** Sesiunile de corigență demo (ordinul poartă marcajul — inclusiv cea din seed-ul de calendar). */
    private function purgeCorigentaSessions(bool $dryRun): int
    {
        $query = CorigentaSession::query()->where('order_reference', 'like', self::DEMO.'%');

        $count = (clone $query)->count();

        if (! $dryRun && $count > 0) {
            $query->delete();
        }

        return $count;
    }

    /** Comisiile de examen demo (denumirea poartă marcajul; pivotul de membri cade prin CASCADE). */
    private function purgeExamCommissions(bool $dryRun): int
    {
        $query = ExamCommission::query()->where('name', 'like', self::DEMO.'%');

        $count = (clone $query)->count();

        if (! $dryRun && $count > 0) {
            $query->delete();
        }

        return $count;
    }

    /**
     * Zilele libere demo. Prinde ȘI denumirea istorică „Zi liberă (demo)" (pusă de seeder-ul de
     * calendar înainte de standardizarea marcajului [DEMO]) — pe producție ea există deja.
     * Ștergerea prin MODEL (nu query builder): evenimentul `deleted` invalidează cache-ul
     * {@see Holidays} și recalculează termenele de motivare deschise.
     */
    private function purgeHolidays(bool $dryRun): int
    {
        $holidays = Holiday::query()
            ->where(function (Builder $inner): void {
                $inner->where('name', 'like', '%'.self::DEMO.'%')
                    ->orWhere('name', 'Zi liberă (demo)');
            })
            ->get();

        if (! $dryRun) {
            foreach ($holidays as $holiday) {
                $holiday->delete();
            }
        }

        return $holidays->count();
    }

    /**
     * Notele injectate în fișele unor elevi REALI în timpul testării din interfață (marcaj
     * `[TEST UI]` în motivul de anulare). Nu sunt note legacy — se șterg definitiv, ca fișa
     * elevului real să rămână curată.
     */
    private function purgeTestGrades(bool $dryRun): int
    {
        $query = Grade::withTrashed()->where('annulment_reason', 'like', self::TEST_UI.'%');

        $count = (clone $query)->count();

        if (! $dryRun && $count > 0) {
            $query->forceDelete();
        }

        return $count;
    }

    /**
     * Mesajele demo. FK-ul e `CASCADE`, deci ar dispărea oricum la ștergerea conturilor — dar le
     * curățăm explicit, ca să prindem și mesajele NEMARCATE trimise manual la testare, și ca
     * raportul comenzii să reflecte tot ce dispare.
     *
     * @param  Collection<int, int>  $demoIds
     */
    private function purgeMessages(Collection $demoIds, bool $dryRun): int
    {
        // `withTrashed()`: Message e SoftDeletes — un mesaj demo deja soft-șters trebuie prins și el,
        // altfel rămâne în tabel după curățare.
        $query = Message::withTrashed()->where(function (Builder $inner) use ($demoIds): void {
            $inner->whereIn('sender_user_id', $demoIds)
                ->orWhereIn('recipient_user_id', $demoIds)
                ->orWhere('body', 'like', '%'.self::DEMO.'%');
        });

        $count = (clone $query)->count();

        if (! $dryRun && $count > 0) {
            $query->forceDelete();
        }

        return $count;
    }
}
