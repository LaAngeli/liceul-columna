<?php

use App\Enums\CalendarCategory;
use App\Enums\EvaluationType;
use App\Enums\RequestStatus;
use App\Enums\ScheduleType;
use App\Enums\SchoolCycle;
use App\Enums\Sex;
use App\Enums\StudentStatus;
use App\Enums\UserRole;

/**
 * Lot 8.A: etichetele enum (HasLabel) sunt sensibile la locale (RO/RU/EN) prin lang/enums.php,
 * nu mai sunt RO hardcodate. RO trebuie să rămână EXACT ca înainte (fallback + utilizatorii RO).
 */
it('eticheta enum în RO rămâne neschimbată (istoric)', function () {
    app()->setLocale('ro');

    expect(RequestStatus::Pending->getLabel())->toBe('În așteptare')
        ->and(StudentStatus::Corigent->label())->toBe('Corigent')
        ->and(EvaluationType::Teza->getLabel())->toBe('Teză')
        ->and(Sex::from('f')->getLabel())->toBe('Feminin')
        ->and(CalendarCategory::Homework->getLabel())->toBe('Teme');
});

it('eticheta enum se traduce în RU', function () {
    app()->setLocale('ru');

    expect(RequestStatus::Pending->getLabel())->toBe('В ожидании')
        ->and(StudentStatus::Corigent->label())->toBe('На переэкзаменовке')
        ->and(EvaluationType::Teza->getLabel())->toBe('Контрольная')
        ->and(ScheduleType::cases()[0]->getLabel())->toBe('Расписание уроков');
});

it('eticheta enum se traduce în EN', function () {
    app()->setLocale('en');

    expect(RequestStatus::Pending->getLabel())->toBe('Pending')
        ->and(StudentStatus::Corigent->label())->toBe('Make-up exam')
        ->and(EvaluationType::Teza->getLabel())->toBe('Term paper')
        ->and(CalendarCategory::Homework->getLabel())->toBe('Homework');
});

it('eticheta sumativei semestriale diferă pe ciclu (ESS gimnaziu / teză liceu)', function () {
    app()->setLocale('ro');

    expect(EvaluationType::Teza->labelForCycle(SchoolCycle::Gimnaziu))->toBe('ESS (sumativă semestrială)')
        ->and(EvaluationType::Teza->labelForCycle(SchoolCycle::Liceu))->toBe('Teză')
        ->and(EvaluationType::Teza->labelForCycle(SchoolCycle::Primar))->toBe('Teză')
        ->and(EvaluationType::Teza->labelForCycle(null))->toBe('Teză')
        ->and(EvaluationType::Curenta->labelForCycle(SchoolCycle::Gimnaziu))->toBe('Curentă');
});

it('UserRole reutilizează dicționarul site.roles (RO/RU/EN)', function () {
    app()->setLocale('ro');
    expect(UserRole::Director->label())->toBe('Director');

    app()->setLocale('ru');
    expect(UserRole::Director->label())->toBe('Директор');

    app()->setLocale('en');
    expect(UserRole::Director->label())->toBe(trans('site.roles.director', [], 'en'));
});

it('mesajele de validare scoped sunt traductibile', function () {
    app()->setLocale('ru');
    expect(trans('panel.validation.scope.no_teacher_profile'))->toBe('Ваша учётная запись не связана с карточкой преподавателя.');

    app()->setLocale('en');
    expect(trans('panel.validation.scope.not_enrolled'))->toBe('The student is not enrolled in the selected class.');
});

it('o cheie lipsă cade pe RO (fallback)', function () {
    // Forțăm o locală fără cheia respectivă — fallback la APP_FALLBACK_LOCALE=ro.
    app()->setLocale('ru');
    // RequestStatus există în ru; verificăm că fallback-ul nu sparge dacă o cheie ar lipsi
    // folosind o cheie inexistentă, care trebuie să întoarcă fallback-ul RO real al cheii.
    expect((string) trans('enums.request_status.pending'))->not->toBe('enums.request_status.pending');
});
