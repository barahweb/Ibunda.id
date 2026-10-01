<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('assessment_attempts', function (Blueprint $table) {
            $table->text('ai_interpretation')->nullable()->after('dimension_scores');
            $table->timestamp('ai_interpreted_at')->nullable()->after('ai_interpretation');
        });
    }

    public function down(): void
    {
        Schema::table('assessment_attempts', function (Blueprint $table) {
            $table->dropColumn(['ai_interpretation', 'ai_interpreted_at']);
        });
    }
};
