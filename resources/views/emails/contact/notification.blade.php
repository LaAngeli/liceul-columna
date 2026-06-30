<x-mail::message>
# {{ __('contact_mail.notification.heading') }}

- **{{ __('contact_mail.notification.from') }}:** {{ $data['name'] }}
- **{{ __('contact_mail.notification.email') }}:** {{ $data['email'] }}
- **{{ __('contact_mail.notification.phone') }}:** {{ $data['phone'] ?: '—' }}
- **{{ __('contact_mail.notification.subject') }}:** {{ $data['subject'] }}

<x-mail::panel>
{{ $data['message'] }}
</x-mail::panel>

<x-mail::button :url="'mailto:'.$data['email']">
{{ __('contact_mail.notification.reply') }}
</x-mail::button>

{{ __('contact_mail.notification.via', ['url' => config('app.url')]) }}

@include('emails.contact.signature')
</x-mail::message>
