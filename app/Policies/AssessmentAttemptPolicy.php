<?php

namespace App\Policies;

use App\Models\AssessmentAttempt;
use App\Models\User;

class AssessmentAttemptPolicy
{
    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        return $user->isAdmin();
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, AssessmentAttempt $assessmentAttempt): bool
    {
        return $user->isAdmin() || $assessmentAttempt->user_id === $user->id;
    }

    /**
     * Cuma pemilik yang boleh minta interpretasi AI (tiap permintaan makan biaya API),
     * admin cukup bisa melihat hasil yang sudah ada.
     */
    public function interpret(User $user, AssessmentAttempt $assessmentAttempt): bool
    {
        return $assessmentAttempt->user_id === $user->id;
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        return false;
    }

    /**
     * Determine whether the user can update the model.
     *
     * Selalu false, termasuk buat pemiliknya sendiri. Proteksi utama emang struktural
     * (route ambil {assessment} bukan {attempt}, startOrResume() selalu scope ke
     * Auth::user()), jadi gak ada jalur kode yang butuh authorize('update', ...) di
     * attempt orang lain. Ini defense-in-depth, bukan guard utama.
     */
    public function update(User $user, AssessmentAttempt $assessmentAttempt): bool
    {
        return false;
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, AssessmentAttempt $assessmentAttempt): bool
    {
        return false;
    }

    /**
     * Determine whether the user can restore the model.
     */
    public function restore(User $user, AssessmentAttempt $assessmentAttempt): bool
    {
        return false;
    }

    /**
     * Determine whether the user can permanently delete the model.
     */
    public function forceDelete(User $user, AssessmentAttempt $assessmentAttempt): bool
    {
        return false;
    }
}
