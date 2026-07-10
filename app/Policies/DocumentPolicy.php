<?php

namespace App\Policies;

use App\Models\Document;
use App\Models\User;

/**
 * Biblioteca de documente: TOT personalul o consultă (filtrată pe rol în `Document::isVisibleTo`),
 * dar o gestionează doar administratorul operațional / directorul / super-adminul
 * (`canManageDocuments`).
 */
class DocumentPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, Document $document): bool
    {
        return $document->isVisibleTo($user);
    }

    public function create(User $user): bool
    {
        return $user->canManageDocuments();
    }

    public function update(User $user, Document $document): bool
    {
        return $user->canManageDocuments();
    }

    public function delete(User $user, Document $document): bool
    {
        return $user->canManageDocuments();
    }

    public function deleteAny(User $user): bool
    {
        return $user->canManageDocuments();
    }

    public function restore(User $user, Document $document): bool
    {
        return $user->canManageDocuments();
    }

    public function restoreAny(User $user): bool
    {
        return $user->canManageDocuments();
    }

    public function forceDelete(User $user, Document $document): bool
    {
        return $user->canManageDocuments();
    }

    public function forceDeleteAny(User $user): bool
    {
        return $user->canManageDocuments();
    }
}
