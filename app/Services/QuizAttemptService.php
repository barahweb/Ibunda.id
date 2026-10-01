<?php

namespace App\Services;

use App\Models\Quiz;
use App\Models\QuizAttempt;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class QuizAttemptService
{
    public function startOrResume(Quiz $quiz, User $user): QuizAttempt
    {
        $existing = QuizAttempt::where('quiz_id', $quiz->id)
            ->where('user_id', $user->id)
            ->where('status', QuizAttempt::STATUS_IN_PROGRESS)
            ->first();

        if ($existing) {
            return $existing;
        }

        return QuizAttempt::create([
            'quiz_id' => $quiz->id,
            'user_id' => $user->id,
            'started_at' => now(),
            'status' => QuizAttempt::STATUS_IN_PROGRESS,
        ]);
    }

    /**
     * Simpan pilihan jawaban sementara selama attempt masih berjalan (autosave). Diabaikan
     * kalau attempt udah selesai atau lewat batas waktu, biar jawaban yang udah diskor
     * gak bisa berubah.
     */
    public function saveAnswer(QuizAttempt $attempt, string $questionId, string $optionId): void
    {
        DB::transaction(function () use ($attempt, $questionId, $optionId) {
            $locked = QuizAttempt::whereKey($attempt->id)->lockForUpdate()->firstOrFail();

            if ($locked->isCompleted() || $locked->isExpired()) {
                return;
            }

            $question = $locked->quiz->questions()->with('options')->find($questionId);

            if ($question === null || !$question->options->contains('id', $optionId)) {
                return;
            }

            $locked->answers()->updateOrCreate(
                ['question_id' => $question->id],
                ['selected_option_id' => $optionId],
            );
        });
    }

    /**
     * @param  array<string, string>  $answers  question_id => selected_option_id. Soal yang
     *                                          gak ada di sini dinilai dari jawaban yang udah tersimpan (autosave).
     */
    public function submit(QuizAttempt $attempt, array $answers): QuizAttempt
    {
        return DB::transaction(function () use ($attempt, $answers) {
            // Kunci baris attempt dan cek ulang status di dalam transaksi, jaga-jaga
            // terhadap dua request submit yang nyaris bersamaan (double click, replay),
            // biar cuma salah satu yang benar-benar menskor & bikin baris jawaban.
            $locked = QuizAttempt::whereKey($attempt->id)->lockForUpdate()->firstOrFail();

            if ($locked->isCompleted()) {
                return $locked;
            }

            $savedAnswers = $locked->answers()->whereNotNull('selected_option_id')->pluck('selected_option_id', 'question_id');

            $totalPoints = 0;
            $earnedPoints = 0;

            foreach ($locked->quiz->questions()->with('options')->get() as $question) {
                // String kosong dianggap "gak dikirim", bukan "sengaja dikosongin", biar
                // jawaban yang udah ke-autosave gak ketiban dianggap belum dijawab.
                $submitted = $answers[$question->id] ?? null;
                $selectedOptionId = ($submitted !== null && $submitted !== '') ? $submitted : $savedAnswers->get($question->id);

                // Opsi yang dipilih wajib benar-benar milik soal ini, kalau nggak, anggap
                // belum dijawab. Ini jaga-jaga terhadap payload yang dipalsukan (option_id
                // dari soal lain), UI normal nggak akan pernah kirim kombinasi kayak gini.
                if ($selectedOptionId !== null && !$question->options->contains('id', $selectedOptionId)) {
                    $selectedOptionId = null;
                }

                $isCorrect = $selectedOptionId !== null
                    && $question->correctOption?->id === $selectedOptionId;

                $locked->answers()->updateOrCreate(
                    ['question_id' => $question->id],
                    [
                        'selected_option_id' => $selectedOptionId,
                        'is_correct' => $isCorrect,
                        'points_awarded' => $isCorrect ? $question->points : 0,
                    ],
                );

                $totalPoints += $question->points;
                $earnedPoints += $isCorrect ? $question->points : 0;
            }

            $score = $totalPoints > 0 ? (int) round(($earnedPoints / $totalPoints) * 100) : 0;

            $locked->update([
                'submitted_at' => now(),
                'score' => $score,
                'status' => QuizAttempt::STATUS_COMPLETED,
            ]);

            return $locked;
        });
    }
}
