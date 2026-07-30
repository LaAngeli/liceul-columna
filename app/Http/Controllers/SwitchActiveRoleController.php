<?php

namespace App\Http\Controllers;

use App\Enums\UserRole;
use App\Models\User;
use App\Support\ActiveRole;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * COMUTATORUL de rol activ (F2, doc pct. 4): schimbă contextul de lucru al unei sesiuni de panou
 * FĂRĂ logout. Redirectul înapoi face restul — Filament își construiește meniul, dashboard-ul,
 * permisiunile și filtrele la fiecare request, deci „reîncărcarea" cerută de document vine din
 * arhitectură, nu dintr-un mecanism separat.
 *
 * EXCLUSIV panoul staff (decizia beneficiarului, 30.07.2026): cabinetul familiei nu are comutare —
 * nici părinte↔părinte, nici părinte↔elev. Garda e dublă: ruta cere rol de panou, iar
 * {@see ActiveRole::activate} refuză orice valoare care nu e rol de panou al contului.
 */
class SwitchActiveRoleController extends Controller
{
    public function __invoke(Request $request): RedirectResponse
    {
        /** @var User $user */
        $user = $request->user('web');

        abort_unless($user->hasAnyRole(UserRole::panelRoleValues()), 403);

        $validated = $request->validate([
            'role' => ['required', 'string'],
        ]);

        $role = UserRole::tryFrom((string) $validated['role']);

        if ($role === null || ! ActiveRole::activate($user, $role)) {
            return redirect()
                ->back(fallback: '/admin')
                ->with('error', __('panel.role_switch.invalid'));
        }

        return redirect()->back(fallback: '/admin');
    }
}
