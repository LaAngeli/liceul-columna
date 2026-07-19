<?php

/**
 * Audiența PROGRAMATĂ (calendar v3): destinatarul-conducere al solicitării fixează data + ora pe
 * mesajul-rădăcină; solicitantul (familia) e notificat și vede programarea pe fir + în calendarul
 * lui; calendarul instituțional al staff-ului arată întâlnirea cu numele SOLICITANTULUI (adult).
 */

use App\Actions\SendMessage;
use App\Calendar\CalendarAccess;
use App\Calendar\CalendarScope;
use App\Calendar\Projectors\AudienceProjector;
use App\Enums\AudienceDomain;
use App\Enums\MessageType;
use App\Enums\NotificationType;
use App\Enums\UserRole;
use App\Filament\Pages\Mailbox;
use App\Models\Message;
use App\Models\Student;
use App\Models\User;
use App\Notifications\CatalogNotification;
use App\Support\ThreadPresenter;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Notification;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Symfony\Component\HttpKernel\Exception\HttpException;

use function Pest\Laravel\actingAs;

beforeEach(function () {
    foreach (UserRole::cases() as $role) {
        Role::findOrCreate($role->value, 'web');
    }

    $this->deputy = User::factory()->create(['email_verified_at' => now()]);
    $this->deputy->assignRole(UserRole::PrimVicedirector->value);

    $this->parent = User::factory()->create(['email_verified_at' => now()]);
    $this->parent->assignRole(UserRole::Parinte->value);

    $student = Student::factory()->create();
    $this->parent->students()->attach($student->id);

    // Solicitarea reală de audiență, prin fluxul existent (rutare spre conducere).
    $this->audience = app(SendMessage::class)->audience(
        $this->parent,
        $student,
        'Solicitare de discuție',
        'Aș dori o întâlnire despre situația școlară.',
        AudienceDomain::Instruire,
    );
});

it('destinatarul-conducere programează audiența; solicitantul e notificat; re-programarea înlocuiește data', function () {
    Notification::fake();

    app(SendMessage::class)->scheduleAudience($this->deputy, $this->audience, Carbon::parse('2026-09-15 14:00'));

    expect($this->audience->refresh()->scheduled_at?->format('Y-m-d H:i'))->toBe('2026-09-15 14:00');

    Notification::assertSentTo(
        $this->parent,
        CatalogNotification::class,
        fn (CatalogNotification $n): bool => $n->type === NotificationType::NewMessage
            && str_contains((string) $n->customBody, '15'),
    );

    app(SendMessage::class)->scheduleAudience($this->deputy, $this->audience, Carbon::parse('2026-09-16 09:00'));
    expect($this->audience->refresh()->scheduled_at?->format('Y-m-d H:i'))->toBe('2026-09-16 09:00');
});

it('doar destinatarul programează: solicitantul și un terț din conducere primesc 403; un mesaj direct — 422', function () {
    expect(fn () => app(SendMessage::class)->scheduleAudience($this->parent, $this->audience, Carbon::parse('2026-09-15 14:00')))
        ->toThrow(HttpException::class);

    $otherBoss = User::factory()->create();
    $otherBoss->assignRole(UserRole::Director->value);
    expect(fn () => app(SendMessage::class)->scheduleAudience($otherBoss, $this->audience, Carbon::parse('2026-09-15 14:00')))
        ->toThrow(HttpException::class);

    $direct = Message::factory()->create(['type' => MessageType::Direct]);
    $direct->forceFill(['recipient_user_id' => $this->deputy->id])->save();
    expect(fn () => app(SendMessage::class)->scheduleAudience($this->deputy, $direct->refresh(), Carbon::parse('2026-09-15 14:00')))
        ->toThrow(HttpException::class);
});

it('firul poartă programarea pentru AMBII participanți; doar destinatarul are dreptul de a programa', function () {
    $this->audience->forceFill(['scheduled_at' => '2026-09-15 14:00'])->save();
    $root = $this->audience->refresh()->load(['sender', 'recipient', 'student', 'attachments', 'states', 'replies']);

    $forDeputy = app(ThreadPresenter::class)->present($root, (int) $this->deputy->id);
    $forParent = app(ThreadPresenter::class)->present($root, (int) $this->parent->id);

    expect($forDeputy['scheduledAt'])->toBe('15.09.2026 14:00')
        ->and($forDeputy['canSchedule'])->toBeTrue()
        ->and($forParent['scheduledAt'])->toBe('15.09.2026 14:00')
        ->and($forParent['canSchedule'])->toBeFalse();
});

it('pagina Mailbox programează audiența din firul deschis (Livewire)', function () {
    actingAs($this->deputy);

    Livewire::test(Mailbox::class)
        ->call('openThread', $this->audience->id)
        ->set('scheduleAt', '2026-09-15T14:00')
        ->call('saveAudienceSchedule')
        ->assertHasNoErrors();

    expect($this->audience->refresh()->scheduled_at?->format('Y-m-d H:i'))->toBe('2026-09-15 14:00');
});

it('calendarul: staff-ul vede audiența cu numele SOLICITANTULUI; familia doar pe a ei; alt părinte nimic', function () {
    $this->audience->forceFill(['scheduled_at' => '2026-09-15 14:00'])->save();

    $project = fn (CalendarScope $scope): array => app(AudienceProjector::class)
        ->project($scope, Carbon::parse('2026-09-01'), Carbon::parse('2026-09-30'));

    $staffItems = $project(app(CalendarAccess::class)->staffScope($this->deputy));
    expect($staffItems)->toHaveCount(1)
        ->and($staffItems[0]->date)->toBe('2026-09-15')
        ->and($staffItems[0]->startTime)->toBe('14:00')
        ->and($staffItems[0]->title)->toContain($this->parent->name);

    $ownItems = $project(new CalendarScope($this->parent, collect()));
    expect($ownItems)->toHaveCount(1)
        ->and($ownItems[0]->title)->not->toContain($this->parent->name);

    $strangerItems = $project(new CalendarScope(User::factory()->create(), collect()));
    expect($strangerItems)->toHaveCount(0);
});
