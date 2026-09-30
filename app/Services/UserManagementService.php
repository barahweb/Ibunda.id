<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\DB;

class UserManagementService
{
    /**
     * Balik role user antara admin <-> peserta.
     *
     * @throws \DomainException kalau target-nya admin terakhir yang tersisa
     */
    public function toggleRole(User $target): User
    {
        return DB::transaction(function () use ($target) {
            $locked = User::whereKey($target->id)->lockForUpdate()->firstOrFail();

            // Kunci baris admin pas ngitung, jaga-jaga terhadap dua request "cabut admin"
            // yang nyaris bersamaan buat dua admin berbeda pas cuma tersisa 2 admin, biar
            // gak keduanya lolos cek dan aplikasi kehabisan admin sama sekali.
            if ($locked->isAdmin() && User::where('role', User::ROLE_ADMIN)->lockForUpdate()->count() <= 1) {
                throw new \DomainException('Gak bisa mencabut role admin terakhir yang tersisa.');
            }

            // 'role' sengaja gak ada di $fillable User (jaga-jaga mass-assignment dari input
            // sembarangan), jadi di sini di-set langsung sebagai perubahan yang legitimate
            // dari admin lewat service layer, bukan dari input user mentah.
            $locked->forceFill([
                'role' => $locked->isAdmin() ? User::ROLE_PARTICIPANT : User::ROLE_ADMIN,
            ])->save();

            return $locked;
        });
    }
}
