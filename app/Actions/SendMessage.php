<?php

namespace App\Actions;

use App\Enums\AudienceDomain;
use App\Enums\MessageType;
use App\Enums\NotificationType;
use App\Enums\UserRole;
use App\Models\Enrollment;
use App\Models\Message;
use App\Models\Student;
use App\Models\Teacher;
use App\Models\User;
use App\Notifications\CatalogNotification;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;

/**
 * Trimiterea mesajelor cu filtrare IERARHICĂ pe server (spec §4.2): comunicarea e liberă spre
 * nivelul firesc (familie ↔ profesorul/dirigintele copilului), dar familia NU scrie direct
 * conducerii — pentru asta există „Solicitarea de audiență", rutată către vicedirectorul de
 * DOMENIU (instruire/educație) după subiect, cu fallback pe director. Canalele nepermise nu apar
 * în interfață, dar regula reală e aici, verificată la fiecare scriere.
 */
class SendMessage
{
    /**
     * Mesaj direct. Aruncă 403 dacă regula ierarhică nu permite perechea expeditor→destinatar.
     */
    public function direct(
        User $sender,
        User $recipient,
        string $body,
        ?string $subject = null,
        ?Student $student = null,
        ?Message $replyTo = null,
    ): Message {
        abort_unless($this->canSendDirect($sender, $recipient, $student), 403, 'Canal de comunicare nepermis.');

        return Message::create([
            'sender_user_id' => $sender->id,
            'recipient_user_id' => $recipient->id,
            'student_id' => $student !== null ? $student->id : $replyTo?->student_id,
            'parent_id' => $replyTo?->id,
            'type' => MessageType::Direct,
            'subject' => $subject ?? ($replyTo !== null ? 'Re: '.(string) $replyTo->subject : null),
            'body' => $body,
        ]);
    }

    /**
     * Răspuns într-un fir EXISTENT. Nu se reaplică filtrarea ierarhică — canalul a fost deja
     * deschis legitim; doar participanții la fir pot răspunde (ex. prim-vicedirectorul răspunde
     * la o solicitare de audiență, deși nu „predă" elevului).
     */
    public function reply(User $sender, Message $original, string $body): Message
    {
        abort_unless(
            in_array($sender->id, [$original->sender_user_id, $original->recipient_user_id], true),
            403,
            'Nu faci parte din această conversație.',
        );

        $recipientId = $original->sender_user_id === $sender->id
            ? $original->recipient_user_id
            : $original->sender_user_id;

        return Message::create([
            'sender_user_id' => $sender->id,
            'recipient_user_id' => $recipientId,
            'student_id' => $original->student_id,
            'parent_id' => $original->parent_id ?? $original->id,
            'type' => $original->type,
            'subject' => str_starts_with((string) $original->subject, 'Re: ')
                ? $original->subject
                : 'Re: '.(string) $original->subject,
            'body' => $body,
        ]);
    }

    /**
     * Solicitare de audiență a familiei → rutată către vicedirectorul responsabil de DOMENIU
     * (instruire / educație), după subiectul sesizării (§4.2). Dacă niciun cont nu gestionează
     * domeniul, cade pe director — care poate apoi atribui responsabilul (atributul audience_domains).
     */
    public function audience(User $sender, Student $student, string $subject, string $body, AudienceDomain $domain): Message
    {
        abort_unless($this->isFamilyOf($sender, $student), 403, 'Doar familia poate solicita audiență.');
        // Solicitarea de audiență e prerogativa TUTORELUI legal (părintele), NU a elevului. Elevul
        // comunică doar cu profesorii/dirigintele lui; escaladarea către conducere se face de familie.
        abort_if($sender->hasRole(UserRole::Elev->value), 403, 'Elevul nu poate solicita audiență direct — solicitarea o depune tutorele legal.');

        $recipient = $this->audienceTargetForDomain($domain);
        abort_unless($recipient !== null, 422, 'Nu există un membru al conducerii pentru rutarea audienței.');

        return Message::create([
            'sender_user_id' => $sender->id,
            'recipient_user_id' => $recipient->id,
            'student_id' => $student->id,
            'type' => MessageType::Audience,
            'audience_domain' => $domain,
            'subject' => $subject,
            'body' => $body,
        ]);
    }

    /**
     * Semnalare de COMPORTAMENT a unui elev (spec §4.2, „mesaj comportamental filtrat"):
     * profesorul/dirigintele care PREDĂ elevului o trimite, dar mesajul NU merge la familie —
     * merge la PRIM-VICEDIRECTOR (moderare), care decide ce și cum transmite părinților (prin
     * compose-ul obișnuit). Rutare: responsabilul domeniului EDUCAȚIE (atributul
     * audience_domains), altfel prim-vicedirector → director → administrator operațional.
     */
    public function behavioralReport(User $sender, Student $student, string $body): Message
    {
        abort_unless(
            $this->teachesStudent($sender, $student),
            403,
            'Doar profesorul sau dirigintele elevului poate semnala comportamentul.',
        );

        $recipient = $this->behavioralTarget();
        abort_unless($recipient !== null, 422, 'Nu există un membru al conducerii pentru rutarea semnalării.');

        $class = $student->currentSchoolClass();
        $classLabel = $class !== null ? ' ('.trim($class->name.' '.($class->section ?? '')).')' : '';

        return Message::create([
            'sender_user_id' => $sender->id,
            'recipient_user_id' => $recipient->id,
            'student_id' => $student->id,
            'type' => MessageType::Behavioral,
            'subject' => __('panel.actions.behavior.subject', ['student' => $student->full_name]).$classLabel,
            'body' => $body,
        ]);
    }

    /**
     * Destinatarul unei semnalări de comportament: responsabilul domeniului EDUCAȚIE (dacă e
     * desemnat), altfel prim-vicedirectorul (spec §4.2), apoi directorul / AO.
     */
    private function behavioralTarget(): ?User
    {
        $handler = User::query()
            ->handlingAudienceDomain(AudienceDomain::Educatie)
            ->orderBy('id')
            ->first();

        if ($handler !== null) {
            return $handler;
        }

        foreach ([UserRole::PrimVicedirector, UserRole::Director, UserRole::AdministratorOperational] as $role) {
            $user = User::query()
                ->whereHas('roles', fn ($query) => $query->where('name', $role->value))
                ->first();

            if ($user !== null) {
                return $user;
            }
        }

        return null;
    }

    /**
     * PROGRAMEAZĂ audiența (calendar v3): destinatarul-conducere al solicitării fixează data +
     * ora întâlnirii pe mesajul-RĂDĂCINĂ; re-apelarea RE-programează. Solicitantul (familia) e
     * notificat, iar programarea se proiectează în calendarul staff + calendarul familiei.
     */
    public function scheduleAudience(User $actor, Message $root, CarbonInterface $at): Message
    {
        abort_unless($root->type === MessageType::Audience && $root->parent_id === null, 422, 'Doar o solicitare de audiență se poate programa.');
        abort_unless((int) $root->recipient_user_id === (int) $actor->id, 403, 'Doar destinatarul solicitării programează audiența.');

        $root->forceFill(['scheduled_at' => $at])->save();

        $root->sender?->notify(new CatalogNotification(
            NotificationType::NewMessage,
            url: '/cabinet/mesaje?fir='.$root->id,
            customTitle: (string) __('panel.mailbox.audience_scheduled_notify_title'),
            customBody: (string) __('panel.mailbox.audience_scheduled_notify_body', [
                'at' => $at->translatedFormat('l, j F Y · H:i'),
            ]),
        ));

        return $root->refresh();
    }

    /**
     * Regula centrală „cine poate scrie cui" (§4.2):
     * - familia → doar profesorul/dirigintele copilului (NU conducerea: aceea e via audiență);
     * - personalul → familia unui elev pe care îl predă/conduce, sau alt membru al personalului;
     * - familie → familie: interzis.
     */
    public function canSendDirect(User $sender, User $recipient, ?Student $student): bool
    {
        if ($sender->id === $recipient->id) {
            return false;
        }

        $senderIsStaff = $sender->hasAnyRole(UserRole::panelRoleValues());
        $recipientIsStaff = $recipient->hasAnyRole(UserRole::panelRoleValues());

        // Familie ca expeditor: doar către profesorul/dirigintele copilului.
        if (! $senderIsStaff) {
            return $student !== null
                && $this->isFamilyOf($sender, $student)
                && $recipientIsStaff
                && $this->teachesStudent($recipient, $student);
        }

        // Personal → familie: doar către familia unui elev pe care îl predă/conduce.
        if (! $recipientIsStaff) {
            return $student !== null
                && $this->isFamilyOf($recipient, $student)
                && $this->teachesStudent($sender, $student);
        }

        // Personal → personal: permis (comunicare internă / escaladare ierarhică). DAR dacă mesajul
        // e ANCORAT pe un elev (student_id), expeditorul trebuie să aibă o legătură reală cu acel
        // MINOR — altfel oricine din personal ar putea deschide corespondență despre ORICE elev,
        // expunându-i contextul (nume, clasă) unui coleg fără temei (L133, proporționalitate).
        // Legătura legitimă:
        //  • îl predă / e dirigintele lui;
        //  • e administrație ACADEMICĂ (director / prim-vicedirector / AO — văd tot catalogul prin
        //    rol); NU include administratorul tehnic, exclus din isAdministrator();
        //  • e familia lui (caz de margine: un cadru didactic care e și părintele elevului).
        if ($student !== null) {
            return $this->teachesStudent($sender, $student)
                || $sender->isAdministrator()
                || $this->isFamilyOf($sender, $student);
        }

        return true;
    }

    /**
     * Lista destinatarilor permiși pentru familia unui elev: profesorii care îi predau + dirigintele.
     *
     * @return list<array{id: int, name: string, role: string}>
     */
    public function allowedRecipientsForStudent(Student $student): array
    {
        $classIds = $student->enrollments()->pluck('school_class_id')
            ->map(static fn ($id): int => (int) $id)->all();

        if ($classIds === []) {
            return [];
        }

        $homeroomTeacherIds = Teacher::query()
            ->whereHas('homeroomClasses', fn (Builder $q) => $q->whereIn('id', $classIds))
            ->pluck('id')->map(static fn ($id): int => (int) $id)->all();

        // Profesorii care predau la clasele elevului + dirigintele clasei.
        $teachers = Teacher::query()
            ->whereNotNull('user_id')
            ->where(function (Builder $query) use ($classIds): void {
                $query->whereHas('teachingAssignments', fn (Builder $q) => $q->whereIn('school_class_id', $classIds))
                    ->orWhereHas('homeroomClasses', fn (Builder $q) => $q->whereIn('id', $classIds));
            })
            ->get();

        // Dedup pe user id; dirigintele primește eticheta „diriginte" chiar dacă și predă.
        $recipients = [];
        foreach ($teachers as $teacher) {
            if ($teacher->user_id === null) {
                continue;
            }
            $recipients[(int) $teacher->user_id] = [
                'id' => (int) $teacher->user_id,
                'name' => $teacher->full_name,
                'role' => in_array((int) $teacher->id, $homeroomTeacherIds, true) ? 'diriginte' : 'profesor',
            ];
        }

        return array_values($recipients);
    }

    /**
     * Familia unui elev, ca destinatari: tutorii legali + contul PROPRIU al elevului.
     *
     * ⚠️ Părintele și elevul sunt utilizatori DISTINCȚI (`guardians()` vs `user()`). Fără această
     * distincție, personalul ar putea trimite din greșeală unui minor un mesaj destinat tutorelui.
     *
     * @return list<array{id: int, name: string, relation: string}>
     */
    public function familyRecipientsForStudent(Student $student): array
    {
        $recipients = [];

        foreach ($student->guardians as $guardian) {
            $recipients[(int) $guardian->id] = [
                'id' => (int) $guardian->id,
                'name' => $guardian->name,
                'relation' => 'parinte',
            ];
        }

        if ($student->user_id !== null) {
            $recipients[(int) $student->user_id] = [
                'id' => (int) $student->user_id,
                'name' => $student->full_name,
                'relation' => 'elev',
            ];
        }

        return array_values($recipients);
    }

    /**
     * Agenda de destinatari a unui membru al PERSONALULUI, pe 4 categorii ABSOLUTE (nu relative
     * la expeditor — „colegii" unui administrator nu sunt „doar ceilalți administratori"):
     *
     *  • administration — conducerea/administrația (director, prim-vicedirector, administrator
     *    operațional, administrator tehnic). Super-adminul (break-glass IT) NU e listat: e un cont
     *    de urgență, nu o destinație de corespondență școlară.
     *  • colleagues — cadrele didactice (profesori + diriginți).
     *  • parents — tutorii elevilor pe care expeditorul îi PREDĂ / le e diriginte, câte o intrare
     *    per (tutore, elev) — elevul e ancora obligatorie a mesajului.
     *  • students — conturile PROPRII ale acelorași elevi (utilizatori distincți de tutori!).
     *
     * Conducerea nu are fișă de cadru didactic ⇒ parents/students goale: ea NU inițiază spre
     * familii, ci răspunde la audiențe (§4.2). Agenda e consultativă; poarta reală rămâne
     * {@see canSendDirect}, verificată la fiecare scriere.
     *
     * @return array{
     *     administration: array<int, array{id: int, name: string, role: string}>,
     *     colleagues: array<int, array{id: int, name: string, role: string}>,
     *     parents: array<int, array{userId: int, name: string, studentId: int, studentName: string, classLabel: string|null}>,
     *     students: array<int, array{userId: int, name: string, studentId: int, classLabel: string|null}>
     * }
     */
    public function allowedRecipientsForStaff(User $staff): array
    {
        $pedagogical = [UserRole::Profesor->value, UserRole::Diriginte->value];

        $panelUsers = User::query()
            ->whereHas('roles', fn (Builder $query) => $query->whereIn('name', UserRole::panelRoleValues()))
            ->whereKeyNot($staff->id)
            ->with('roles')
            ->orderBy('name')
            ->get();

        $administration = [];
        $colleagues = [];

        foreach ($panelUsers as $user) {
            $role = (string) $user->getRoleNames()->first();

            if ($role === UserRole::Admin->value) {
                continue; // break-glass IT — nu e o destinație de corespondență
            }

            $entry = ['id' => (int) $user->id, 'name' => $user->name, 'role' => $role];

            if (in_array($role, $pedagogical, true)) {
                $colleagues[] = $entry;
            } else {
                $administration[] = $entry;
            }
        }

        $parents = [];
        $students = [];
        $classIds = $staff->teacher?->visibleSchoolClassIds() ?? [];

        if ($classIds !== []) {
            $taught = Student::query()
                ->whereHas('enrollments', fn (Builder $query) => $query->whereIn('school_class_id', $classIds))
                ->with(['guardians', 'enrollments.schoolClass'])
                ->orderBy('last_name')
                ->orderBy('first_name')
                ->get();

            foreach ($taught as $student) {
                $class = $student->enrollments->last()?->schoolClass;
                $classLabel = $class !== null ? trim($class->name.' '.($class->section ?? '')) : null;

                foreach ($student->guardians as $guardian) {
                    $parents[] = [
                        'userId' => (int) $guardian->id,
                        'name' => $guardian->name,
                        'studentId' => (int) $student->id,
                        'studentName' => $student->full_name,
                        'classLabel' => $classLabel,
                    ];
                }

                if ($student->user_id !== null) {
                    $students[] = [
                        'userId' => (int) $student->user_id,
                        'name' => $student->full_name,
                        'studentId' => (int) $student->id,
                        'classLabel' => $classLabel,
                    ];
                }
            }
        }

        return [
            'administration' => $administration,
            'colleagues' => $colleagues,
            'parents' => $parents,
            'students' => $students,
        ];
    }

    private function isFamilyOf(User $user, Student $student): bool
    {
        return $user->students()->whereKey($student->id)->exists() || $student->user_id === $user->id;
    }

    private function teachesStudent(User $staff, Student $student): bool
    {
        $teacher = $staff->teacher;

        if ($teacher === null) {
            return false;
        }

        $classIds = $teacher->visibleSchoolClassIds();

        if ($classIds === []) {
            return false;
        }

        return Enrollment::query()
            ->where('student_id', $student->id)
            ->whereIn('school_class_id', $classIds)
            ->exists();
    }

    /**
     * Destinatarul unei audiențe pe un domeniu: întâi un cont care GESTIONEAZĂ domeniul
     * (atributul audience_domains), altfel fallback pe conducere. Atributul de domeniu primează.
     */
    private function audienceTargetForDomain(AudienceDomain $domain): ?User
    {
        $handler = User::query()
            ->handlingAudienceDomain($domain)
            ->orderBy('id')
            ->first();

        return $handler ?? $this->audienceTarget();
    }

    /**
     * Fallback de rutare dacă niciun responsabil de domeniu nu e atribuit: director, apoi
     * (excepțional) prim-vicedirector / administrator operațional.
     */
    private function audienceTarget(): ?User
    {
        foreach ([UserRole::Director, UserRole::PrimVicedirector, UserRole::AdministratorOperational] as $role) {
            $user = User::query()
                ->whereHas('roles', fn ($query) => $query->where('name', $role->value))
                ->first();

            if ($user !== null) {
                return $user;
            }
        }

        return null;
    }
}
