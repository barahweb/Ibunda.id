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
        Schema::create('assessment_attempts', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('assessment_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('user_id')->constrained()->cascadeOnDelete();
            $table->timestamp('started_at');
            $table->timestamp('submitted_at')->nullable();
            $table->enum('status', ['in_progress', 'completed'])->default('in_progress');
            // result_type = kode 4 huruf hasil (mis. "INTJ"), dimension_scores = jumlah
            // skala Likert per dimensi (mis. {"EI":18,"SN":30,"TF":22,"JP":26}).
            $table->char('result_type', 4)->nullable();
            $table->json('dimension_scores')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'status']);
            $table->index(['assessment_id', 'status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('assessment_attempts');
    }
};
