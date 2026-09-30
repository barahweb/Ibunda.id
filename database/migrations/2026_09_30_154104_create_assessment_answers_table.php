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
        Schema::create('assessment_answers', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('assessment_attempt_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('assessment_question_id')->constrained()->cascadeOnDelete();
            // value 1-5 (skala Likert), null = disubmit tapi soal ini gak dijawab.
            $table->unsignedTinyInteger('value')->nullable();
            $table->timestamps();

            $table->unique(['assessment_attempt_id', 'assessment_question_id'], 'assessment_answers_attempt_question_unique');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('assessment_answers');
    }
};
