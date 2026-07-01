@php($school = config('contact'))
@php($logoPath = public_path('images/logo/columna-crest-color.png'))
---

<p style="margin: 0 0 16px;">
    {{-- CID-embed (atașament inline) = imaginea merge ÎN email, NU prin HTTP extern.
         Asta evită blocarea de privacy din Gmail/Outlook/Apple Mail (n-au nimic de tracker).
         Fallback la URL absolut pentru preview/render fără context de Message (de ex. tests). --}}
    @if (isset($message) && file_exists($logoPath))
        <img src="{{ $message->embed($logoPath) }}" alt="{{ $school['name'] }}" width="80" height="80" style="display: block; width: 80px; height: 80px; border: 0;">
    @else
        <img src="{{ asset('images/logo/columna-crest-color.png') }}" alt="{{ $school['name'] }}" width="80" height="80" style="display: block; width: 80px; height: 80px; border: 0;">
    @endif
</p>

{{ __('contact_mail.signature.regards') }}

**{{ __('contact_mail.signature.team') }}**

{{ $school['name'] }}

- **{{ __('contact_mail.signature.address_label') }}:** {{ $school['address'] }}
- **{{ __('contact_mail.signature.phone_label') }}:** {{ $school['phone'] }}
- **{{ __('contact_mail.signature.email_label') }}:** {{ $school['mailbox'] }}
- **{{ __('contact_mail.signature.website_label') }}:** {{ $school['website'] }}
- **{{ __('contact_mail.signature.hours_label') }}:** {{ __('contact_mail.signature.hours_value') }}

*„{{ __('contact_mail.signature.slogan') }}"*

---

> {{ __('contact_mail.signature.automated_note') }}
