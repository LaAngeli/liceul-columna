<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreContactRequest;
use App\Mail\ContactConfirmation;
use App\Mail\ContactNotification;
use App\Support\Locale;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Mail;
use Inertia\Inertia;
use Inertia\Response;

class ContactController extends Controller
{
    /**
     * Primește mesajul din formularul public de contact: validează (StoreContactRequest), trimite
     * pe QUEUE un e-mail către cutia liceului (`replyTo` = expeditor) + un e-mail de confirmare către
     * expeditor, apoi redirecționează la pagina „mulțumim" (tipar Post/Redirect/Get). Honeypot tăcut;
     * throttling pe rută. NU stocăm mesajul (fără PII la rest) — doar îl expediem.
     */
    public function store(StoreContactRequest $request): RedirectResponse
    {
        $name = (string) $request->string('name');
        $thanks = redirect(Locale::path('contacte/multumim'))->with('contact.name', $name);

        // Honeypot: dacă botul a completat câmpul ascuns, simulăm succesul fără a trimite nimic.
        if ($request->filled('website')) {
            return $thanks;
        }

        $data = [
            'name' => $name,
            'email' => (string) $request->string('email'),
            'phone' => $request->filled('phone') ? (string) $request->string('phone') : null,
            'subject' => (string) $request->string('subject'),
            'message' => (string) $request->string('message'),
        ];

        $locale = app()->getLocale();

        Mail::to((string) config('contact.mailbox'))->locale($locale)->queue(new ContactNotification($data));
        Mail::to($data['email'], $data['name'])->locale($locale)->queue(new ContactConfirmation($data));

        return $thanks;
    }

    /**
     * Pagina „mulțumim" — accesibilă doar imediat după trimitere (flash), altfel revine la /contacte.
     */
    public function thanks(): Response|RedirectResponse
    {
        $name = session('contact.name');

        if (! is_string($name) || $name === '') {
            return redirect(Locale::path('contacte'));
        }

        return Inertia::render('public/contact-multumim', [
            'name' => $name,
        ]);
    }
}
