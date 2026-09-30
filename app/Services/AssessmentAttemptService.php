<?php

namespace App\Services;

use App\Helper\OejtsScorer;
use App\Models\Assessment;
use App\Models\AssessmentAttempt;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class AssessmentAttemptService
{
    public function startOrResume(Assessment $assessment, User $user): AssessmentAttempt
    {
        $existing = AssessmentAttempt::where('assessment_id', $assessment->id)
            ->where('user_id', $user->id)
            ->where('status', AssessmentAttempt::STATUS_IN_PROGRESS)
            ->first();

        if ($existing) {
            return $existing;
        }

        return AssessmentAttempt::create([
            'assessment_id' => $assessment->id,
            'user_id' => $user->id,
            'started_at' => now(),
            'status' => AssessmentAttempt::STATUS_IN_PROGRESS,
        ]);
    }

    /**
     * Simpan 1 jawaban skala Likert sementara selama attempt masih berjalan (autosave).
     * Diabaikan kalau attempt udah selesai, biar hasil yang udah keluar gak bisa berubah.
     */
    public function saveAnswer(AssessmentAttempt $attempt, string $questionId, int $value): void
    {
        DB::transaction(function () use ($attempt, $questionId, $value) {
            $locked = AssessmentAttempt::whereKey($attempt->id)->lockForUpdate()->firstOrFail();

            if ($locked->isCompleted()) {
                return;
            }

            $question = $locked->assessment->questions()->find($questionId);

            if ($question === null || $value < 1 || $value > 5) {
                return;
            }

            $locked->answers()->updateOrCreate(
                ['assessment_question_id' => $question->id],
                ['value' => $value],
            );
        });
    }

    /**
     * @param  array<string, int|string|null>  $answers  question_id => nilai Likert 1-5. Soal yang
     *                                                   gak ada di sini dinilai dari jawaban yang udah tersimpan (autosave).
     */
    public function submit(AssessmentAttempt $attempt, array $answers): AssessmentAttempt
    {
        return DB::transaction(function () use ($attempt, $answers) {
            // Kunci baris attempt dan cek ulang status di dalam transaksi, jaga-jaga
            // terhadap dua request submit yang nyaris bersamaan, biar cuma salah satu
            // yang benar-benar menskor & bikin baris jawaban.
            $locked = AssessmentAttempt::whereKey($attempt->id)->lockForUpdate()->firstOrFail();

            if ($locked->isCompleted()) {
                return $locked;
            }

            $savedAnswers = $locked->answers()->whereNotNull('value')->pluck('value', 'assessment_question_id');

            $questions = $locked->assessment->questions()->get();
            $valuesByQuestionId = [];

            foreach ($questions as $question) {
                // String kosong dianggap "gak dikirim", bukan "sengaja dikosongin", biar
                // jawaban yang udah ke-autosave gak ketiban dianggap belum dijawab.
                $submitted = $answers[$question->id] ?? null;
                $value = ($submitted !== null && $submitted !== '') ? (int) $submitted : $savedAnswers->get($question->id);

                if ($value !== null && ($value < 1 || $value > 5)) {
                    $value = null;
                }

                $valuesByQuestionId[$question->id] = $value;

                $locked->answers()->updateOrCreate(
                    ['assessment_question_id' => $question->id],
                    ['value' => $value],
                );
            }

            $dimensionScores = OejtsScorer::sumsByDimension($questions, $valuesByQuestionId);
            $resultType = OejtsScorer::resolveResultType($dimensionScores);

            $locked->update([
                'submitted_at' => now(),
                'status' => AssessmentAttempt::STATUS_COMPLETED,
                'result_type' => $resultType,
                'dimension_scores' => $dimensionScores,
            ]);

            return $locked;
        });
    }
}
