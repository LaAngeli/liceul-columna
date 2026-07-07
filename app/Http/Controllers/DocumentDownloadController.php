<?php

namespace App\Http\Controllers;

use App\Models\Document;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Descărcarea unui document din bibliotecă. Accesul e RE-CONFIRMAT pe server la FIECARE cerere pe
 * baza rolului real (spec §1 — „nu doar ascuns vizual, ca la note"): chiar dacă cineva ghicește
 * URL-ul, primește 403 dacă rolul lui nu are dreptul. Fișierele stau pe disk-ul PRIVAT `local`.
 */
class DocumentDownloadController extends Controller
{
    public function download(Request $request, Document $document): StreamedResponse
    {
        $user = $request->user('web');

        abort_unless($user instanceof User && $document->isVisibleTo($user), 403);
        abort_unless(
            $document->file_path !== null && Storage::disk('local')->exists($document->file_path),
            404,
        );

        return Storage::disk('local')->download(
            $document->file_path,
            $document->file_name ?? ($document->title.'.pdf'),
        );
    }
}
