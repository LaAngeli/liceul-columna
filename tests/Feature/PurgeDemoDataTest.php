<?php

/**
 * `app:purge-demo-data` trebuie să lase producția CURATĂ după go-live: nu doar rândurile de date
 * demo, ci ȘI urmele lor din inboxuri și din jurnalul de audit — fără să atingă datele REALE.
 * Aceste cazuri acoperă exact găurile închise pentru rularea demo pe columna.md (2026-07-21).
 */

use App\Enums\CorrectionStatus;
use App\Models\AbsenceMotivation;
use App\Models\Grade;
use App\Models\GradeCorrection;
use App\Models\Student;
use App\Models\User;
use App\Notifications\CatalogNotification;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

function pdDemoUser(string $label): User
{
    return User::factory()->create(['name' => '[DEMO] '.$label]);
}

function pdInsertNotification(User $notifiable, array $data): string
{
    $id = (string) Str::uuid();

    DB::table('notifications')->insert([
        'id' => $id,
        'type' => CatalogNotification::class,
        'notifiable_type' => User::class,
        'notifiable_id' => $notifiable->id,
        'data' => json_encode($data),
        'read_at' => null,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    return $id;
}

function pdInsertAudit(User $author, string $auditableType, int $auditableId): int
{
    return (int) DB::table('audits')->insertGetId([
        'user_type' => User::class,
        'user_id' => $author->id,
        'event' => 'updated',
        'auditable_type' => $auditableType,
        'auditable_id' => $auditableId,
        'old_values' => '[]',
        'new_values' => '[]',
        'created_at' => now(),
        'updated_at' => now(),
    ]);
}

it('curăță verdictul unei motivări demo din inboxul unei familii REALE, fără să atingă notificările reale', function () {
    $demoParent = pdDemoUser('Părinte');
    $realFamily = User::factory()->create(['name' => 'Familie Reală']);

    $demoMotivation = AbsenceMotivation::factory()->create([
        'student_id' => Student::factory()->create()->id,
        'requested_by_user_id' => $demoParent->id,
        'reason' => '[DEMO] Consultație',
    ]);

    // Verdictul demo ajuns în inboxul unei familii REALE (poartă motivation_id, ca în observer).
    $demoVerdict = pdInsertNotification($realFamily, [
        'type' => 'absence_motivation_decided',
        'motivation_id' => $demoMotivation->id,
        'body' => 'Cererea ... a fost soluționată.',
    ]);

    // O notificare REALĂ, fără legătură cu demo — trebuie să SUPRAVIEȚUIASCĂ.
    $realNotice = pdInsertNotification($realFamily, [
        'type' => 'new_grade',
        'body' => 'Notă nouă reală.',
    ]);

    $this->artisan('app:purge-demo-data')->assertExitCode(0);

    expect(DB::table('notifications')->where('id', $demoVerdict)->exists())->toBeFalse()
        ->and(DB::table('notifications')->where('id', $realNotice)->exists())->toBeTrue();
});

it('curăță inboxul conturilor demo, dar nu al conturilor reale', function () {
    $demoTeacher = pdDemoUser('Profesor');
    $realTeacher = User::factory()->create(['name' => 'Profesor Real']);

    $demoInbox = pdInsertNotification($demoTeacher, ['type' => 'grade_correction_rejected', 'body' => 'x']);
    $realInbox = pdInsertNotification($realTeacher, ['type' => 'grade_correction_rejected', 'body' => 'y']);

    $this->artisan('app:purge-demo-data')->assertExitCode(0);

    expect(DB::table('notifications')->where('id', $demoInbox)->exists())->toBeFalse()
        ->and(DB::table('notifications')->where('id', $realInbox)->exists())->toBeTrue();
});

it('curăță intrările de audit ale conturilor demo, dar nu pe cele reale', function () {
    $demoAdmin = pdDemoUser('Admin');
    $realAdmin = User::factory()->create(['name' => 'Admin Real']);

    $student = Student::factory()->create();
    $demoAudit = pdInsertAudit($demoAdmin, Student::class, $student->id);
    $realAudit = pdInsertAudit($realAdmin, Student::class, $student->id);

    $this->artisan('app:purge-demo-data')->assertExitCode(0);

    expect(DB::table('audits')->where('id', $demoAudit)->exists())->toBeFalse()
        ->and(DB::table('audits')->where('id', $realAudit)->exists())->toBeTrue();
});

it('restaurează nota la valoarea de dinainte când șterge o corecție demo APROBATĂ', function () {
    $grade = Grade::factory()->create(['value' => 5]);

    // Corecție demo APROBATĂ care a schimbat nota reală 5 → 9 (cazul seeder-ului vechi).
    GradeCorrection::factory()->create([
        'grade_id' => $grade->id,
        'requested_by_user_id' => pdDemoUser('Profesor')->id,
        'old_value' => 5,
        'new_value' => 9,
        'reason' => '[DEMO] Eroare de transcriere',
        'status' => CorrectionStatus::Approved,
    ]);
    $grade->update(['value' => 9]); // starea lăsată de aprobarea demo

    $this->artisan('app:purge-demo-data')->assertExitCode(0);

    expect((float) $grade->fresh()->value)->toBe(5.0)
        ->and(GradeCorrection::query()->count())->toBe(0);
});

it('nu atinge nota dacă valoarea curentă nu mai e cea injectată de demo', function () {
    $grade = Grade::factory()->create(['value' => 5]);

    GradeCorrection::factory()->create([
        'grade_id' => $grade->id,
        'requested_by_user_id' => pdDemoUser('Profesor')->id,
        'old_value' => 5,
        'new_value' => 9,
        'reason' => '[DEMO] Eroare',
        'status' => CorrectionStatus::Approved,
    ]);
    // Între timp o corecție REALĂ a dus nota la 10 → purge NU trebuie s-o retrogradeze la 5.
    $grade->update(['value' => 10]);

    $this->artisan('app:purge-demo-data')->assertExitCode(0);

    expect((float) $grade->fresh()->value)->toBe(10.0);
});

it('dry-run raportează dar nu șterge nimic', function () {
    $demoTeacher = pdDemoUser('Profesor');
    $demoInbox = pdInsertNotification($demoTeacher, ['type' => 'new_message', 'body' => 'z']);

    $this->artisan('app:purge-demo-data', ['--dry-run' => true])->assertExitCode(0);

    expect(DB::table('notifications')->where('id', $demoInbox)->exists())->toBeTrue();
});
