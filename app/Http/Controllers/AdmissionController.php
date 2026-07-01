<?php

namespace App\Http\Controllers;

use App\Enums\AdmissionRequestType;
use App\Http\Requests\StoreAdmissionRequest;
use App\Mail\AdmissionRequestConfirmation;
use App\Mail\AdmissionRequestNotification;
use App\Models\AdmissionRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Mail;
use Inertia\Inertia;
use Inertia\Response;

class AdmissionController extends Controller
{
    public function create(): Response
    {
        return Inertia::render('public/admitere/inregistrare');
    }

    public function store(StoreAdmissionRequest $request): RedirectResponse
    {
        $admission = AdmissionRequest::create([
            ...$request->validated(),
            'type' => AdmissionRequestType::Enrollment,
        ]);

        /* E-mail către secretariat — întotdeauna în RO (administrația citește local), pe queue (worker prod / sync dev). */
        Mail::to((string) config('contact.mailbox'))
            ->locale('ro')
            ->queue(new AdmissionRequestNotification($admission));

        /* E-mail de confirmare către părinte (DOAR dacă a oferit email) — în limba activă a vizitatorului. */
        if ($admission->email) {
            Mail::to($admission->email, $admission->parent_name)
                ->locale(app()->getLocale())
                ->queue(new AdmissionRequestConfirmation($admission));
        }

        return back()->with('success', 'Cererea de înmatriculare a fost trimisă. Vă vom contacta în curând.');
    }
}
