@php
    use App\Enums\AdmissionRequestType;
    use Carbon\Carbon;

    $isVisit = $admission->type === AdmissionRequestType::Visit;

    /* preferred_time = ISO compact „2026-07-15T14:30" (din VisitScheduler). Cererile fără dată → null. */
    $visit = null;
    $rawVisit = $admission->preferred_time;

    if ($rawVisit) {
        try {
            $visit = Carbon::parse($rawVisit);
        } catch (\Throwable $e) {
            $visit = null;
        }
    }
@endphp

<x-mail::message>
# {{ $isVisit ? __('admission_mail.visit.heading') : __('admission_mail.enrollment.heading') }}

{{ $isVisit ? __('admission_mail.visit.intro') : __('admission_mail.enrollment.intro') }}

@if ($isVisit && $visit)
<x-mail::panel>
**{{ __('admission_mail.notification.visit') }}:** {{ ucfirst($visit->locale(app()->getLocale())->translatedFormat('l, j F Y')) }} · <strong>{{ $visit->format('H:i') }}</strong>
</x-mail::panel>
@endif

- **{{ __('admission_mail.notification.child') }}:** {{ $admission->child_name }}
@if ($admission->child_age)
- **{{ __('admission_mail.notification.child_age') }}:** {{ $admission->child_age }} {{ __('admission_mail.notification.years') }}
@endif
@if ($admission->desired_class)
- **{{ __('admission_mail.notification.desired_class') }}:** {{ $admission->desired_class }}
@endif
- **{{ __('admission_mail.notification.parent') }}:** {{ $admission->parent_name }}
- **{{ __('admission_mail.notification.phone') }}:** {{ $admission->phone }}
- **{{ __('admission_mail.notification.email') }}:** {{ $admission->email ?: '—' }}

@if ($admission->email)
<x-mail::button :url="'mailto:'.$admission->email">
{{ __('admission_mail.notification.reply') }}
</x-mail::button>
@endif

{{ __('admission_mail.notification.via', ['url' => config('app.url')]) }}

@include('emails.contact.signature')
</x-mail::message>
