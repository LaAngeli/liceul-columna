<?php

namespace App\Policies;

use App\Models\AcademicRecord;
use App\Models\User;

/**
 * Foaia matricolă e o ARHIVĂ: se consultă (scoped în `getEloquentQuery`), nu se creează, nu se
 * modifică și nu se șterge din panou. Mediile anuale se produc din catalog, nu se tastează.
 */
class AcademicRecordPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->canSeeAcademicData();
    }

    public function view(User $user, AcademicRecord $academicRecord): bool
    {
        return $user->canSeeAcademicData();
    }

    public function create(User $user): bool
    {
        return false;
    }

    public function update(User $user, AcademicRecord $academicRecord): bool
    {
        return false;
    }

    public function delete(User $user, AcademicRecord $academicRecord): bool
    {
        return false;
    }

    public function deleteAny(User $user): bool
    {
        return false;
    }

    public function restore(User $user, AcademicRecord $academicRecord): bool
    {
        return false;
    }

    public function restoreAny(User $user): bool
    {
        return false;
    }

    public function forceDelete(User $user, AcademicRecord $academicRecord): bool
    {
        return false;
    }

    public function forceDeleteAny(User $user): bool
    {
        return false;
    }
}
