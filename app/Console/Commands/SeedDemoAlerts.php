<?php

namespace App\Console\Commands;

use App\Enums\MessageType;
use App\Enums\StudentStatus;
use App\Models\Message;
use App\Models\SemesterValidation;
use App\Models\Student;
use App\Models\Term;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

/**
 * Populează banda de alerte a cockpitului pentru contul demo „[DEMO] Părinte", ca să poată fi testată
 * vizual. Creează câte un exemplar din FIECARE tip de alertă cross-copil:
 *   1. mesaj necitit (→ alerta „mesaje necitite")
 *   2. notificare necitită (→ alerta „notificări necitite")
 *   3. un copil cu statut OFICIAL corigent (→ alerta „cu risc" + confirmarea „am luat cunoștință")
 *
 * Tot ce inserează e marcat „[DEMO]" → curățare curată cu `--remove`. Idempotent (re-rulabil).
 */
class SeedDemoAlerts extends Command
{
    private const MARKER = '[DEMO]';

    protected $signature = 'app:demo-alerts {--remove : Șterge alertele demo în loc să le creeze}';

    protected $description = 'Creează (sau șterge cu --remove) alerte demo pentru contul [DEMO] Părinte';

    public function handle(): int
    {
        $parent = User::query()->where('email', 'parinte@columna.test')->first();

        if ($parent === null) {
            $this->warn('Contul demo `parinte@columna.test` lipsește — rulează `db:seed --class=DemoAccountsSeeder`.');

            return self::FAILURE;
        }

        if ($this->option('remove')) {
            return $this->remove($parent);
        }

        return $this->create($parent);
    }

    private function create(User $parent): int
    {
        // Idempotent: curăță întâi orice [DEMO] anterior, ca să nu se acumuleze la re-rulare.
        $this->purge($parent);

        // 1) MESAJ NECITIT — de la un cadru didactic către părinte (necitit ⇒ intră la „mesaje necitite").
        $sender = User::query()->where('email', 'profesor@columna.test')->first()
            ?? User::query()->where('email', 'admin@liceul-columna.test')->first();

        if ($sender !== null) {
            $child = $parent->students()->first();
            Message::create([
                'sender_user_id' => $sender->id,
                'recipient_user_id' => $parent->id,
                'student_id' => $child?->id,
                'type' => MessageType::Direct,
                'subject' => 'Întâlnire părinți',
                'body' => self::MARKER.' Bună ziua! Vă reamintim de ședința cu părinții de vinerea aceasta.',
                'read_at' => null,
            ]);
            $this->info('✓ Mesaj necitit creat.');
        }

        // 2) NOTIFICARE NECITITĂ — formă de date identică cu CatalogNotification (type/title/body/url),
        //    plus un marcaj „demo" pentru curățare.
        $parent->notifications()->create([
            'id' => (string) Str::uuid(),
            'type' => 'App\\Notifications\\CatalogNotification',
            'data' => [
                'type' => 'announcement',
                'title' => self::MARKER.' Notificare de test',
                'body' => 'Aceasta este o notificare demo pentru a testa banda de alerte.',
                'url' => '/cabinet/notificari',
                'demo' => true,
            ],
            'read_at' => null,
        ]);
        $this->info('✓ Notificare necitită creată.');

        // 3) COPIL CU RISC — statut OFICIAL corigent pentru semestrul curent (declanșează alerta „cu risc"
        //    + confirmarea „am luat cunoștință" din tabul Prezentare). Aleg un copil diferit de cel cu motivări,
        //    ca demoul să arate variație.
        $termId = Term::query()->where('is_current', true)->value('id');
        $atRiskChild = $parent->students()->orderByDesc('students.id')->first();

        if ($termId !== null && $atRiskChild instanceof Student) {
            SemesterValidation::updateOrCreate(
                ['student_id' => $atRiskChild->id, 'term_id' => (int) $termId],
                [
                    'status' => StudentStatus::Corigent,
                    'order_reference' => self::MARKER.' Ordin de test nr. 0/0000',
                    'validated_at' => now(),
                ],
            );
            $this->info("✓ Copil cu risc (corigent oficial): {$atRiskChild->full_name}.");
        }

        $this->newLine();
        $this->info("Gata. Reîncarcă cabinetul cu contul {$parent->name} — banda „Alerte\" va afișa 3 carduri.");
        $this->line('Curățare: php artisan app:demo-alerts --remove');

        return self::SUCCESS;
    }

    private function remove(User $parent): int
    {
        $this->purge($parent);
        $this->info('Alertele demo au fost șterse.');

        return self::SUCCESS;
    }

    /**
     * Șterge toate artefactele de alertă demo ale părintelui (mesaje [DEMO], notificări demo, validări [DEMO]).
     */
    private function purge(User $parent): void
    {
        Message::query()
            ->where('recipient_user_id', $parent->id)
            ->where('body', 'like', self::MARKER.'%')
            ->forceDelete();

        $parent->notifications()
            ->where('data->demo', true)
            ->delete();

        $studentIds = $parent->students()->pluck('students.id');
        SemesterValidation::query()
            ->whereIn('student_id', $studentIds)
            ->where('order_reference', 'like', self::MARKER.'%')
            ->delete();
    }
}
