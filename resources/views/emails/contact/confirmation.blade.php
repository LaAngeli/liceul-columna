<x-mail::message>
# {{ __('contact_mail.confirmation.heading', ['name' => $data['name']]) }}

{{ __('contact_mail.confirmation.intro') }}

{{ __('contact_mail.confirmation.summary') }}

**{{ __('contact_mail.confirmation.subject') }}:** {{ $data['subject'] }}

<x-mail::panel>
{{ $data['message'] }}
</x-mail::panel>

{{ __('contact_mail.confirmation.urgent', ['phone' => config('contact.phone')]) }}

@include('emails.contact.signature')
</x-mail::message>
