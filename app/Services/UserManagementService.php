<?php

namespace App\Services;

use App\Models\User;

class UserManagementService
{
    /**
     * Balik role user antara admin <-> peserta.
     *
     * @throws \DomainException kalau target-nya admin terakhir yang tersisa
     */
    public function toggleRole(User $target): User
    {
        if ($target->isAdmin() && User::where('role', User::ROLE_ADMIN)->count() <= 1) {
            throw new \DomainException('Gak bisa mencabut role admin terakhir yang tersisa.');
        }

        // 'role' sengaja gak ada di $fillable User (jaga-jaga mass-assignment dari input
        // sembarangan), jadi di sini di-set langsung sebagai perubahan yang legitimate
        // dari admin lewat service layer, bukan dari input user mentah.
        $target->forceFill([
            'role' => $target->isAdmin() ? User::ROLE_PARTICIPANT : User::ROLE_ADMIN,
        ])->save();

        return $target;
    }
}
