<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // 'status' dipakai buat filter di hampir semua halaman (browse quiz, dashboard,
        // laporan) tapi belum ke-index sama sekali sebelumnya.
        Schema::table('quizzes', function (Blueprint $table) {
            $table->index('status');
        });

        Schema::table('quiz_attempts', function (Blueprint $table) {
            // Kombinasi (user_id, status) buat query dari sisi peserta (dashboard,
            // riwayat, startOrResume), (quiz_id, status) buat query dari sisi admin
            // (submissions per quiz, laporan, quiz terpopuler).
            $table->index(['user_id', 'status']);
            $table->index(['quiz_id', 'status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('quizzes', function (Blueprint $table) {
            $table->dropIndex(['status']);
        });

        Schema::table('quiz_attempts', function (Blueprint $table) {
            $table->dropIndex(['user_id', 'status']);
            $table->dropIndex(['quiz_id', 'status']);
        });
    }
};
