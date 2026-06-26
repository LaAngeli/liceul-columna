<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreAdmissionRequest;
use App\Models\AdmissionRequest;
use Illuminate\Http\RedirectResponse;
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
        AdmissionRequest::create($request->validated());

        return back()->with('success', 'Cererea de înscriere a fost trimisă. Vă vom contacta în curând.');
    }
}
