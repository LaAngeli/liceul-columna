<?php

namespace App\Http\Controllers;

use App\Enums\AdmissionRequestType;
use App\Http\Requests\StoreVisitRequest;
use App\Mail\AdmissionRequestNotification;
use App\Models\AdmissionRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Mail;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Programare vizită la liceu (acțiune cu angajament mic, mereu disponibilă — CTA principal din navbar).
 * Reutilizează modelul {@see AdmissionRequest} cu `type = visit`.
 */
class VisitController extends Controller
{
    public function create(): Response
    {
        return Inertia::render('public/admitere/programare-vizita');
    }

    public function store(StoreVisitRequest $request): RedirectResponse
    {
        $visit = AdmissionRequest::create([
            ...$request->validated(),
            'type' => AdmissionRequestType::Visit,
        ]);

        Mail::to((string) config('contact.mailbox'))
            ->locale('ro')
            ->queue(new AdmissionRequestNotification($visit));

        return back()->with('success', 'Programarea vizitei a fost trimisă. Vă vom contacta în curând.');
    }
}
