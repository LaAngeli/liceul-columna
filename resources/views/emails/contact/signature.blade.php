@php($school = config('contact'))
{{ __('contact_mail.signature.regards') }}<br>
**{{ __('contact_mail.signature.team') }}**

{{ $school['name'] }}<br>
{{ $school['address'] }}<br>
{{ $school['phone'] }} · {{ $school['mailbox'] }}

*{{ __('contact_mail.signature.slogan') }}*
