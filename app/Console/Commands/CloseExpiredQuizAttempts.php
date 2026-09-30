<?php

namespace App\Console\Commands;

use App\Models\QuizAttempt;
use App\Services\QuizAttemptService;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Collection;

class CloseExpiredQuizAttempts extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'quiz:close-expired-attempts';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Tutup attempt quiz yang batas waktunya sudah lewat, dinilai dari jawaban yang tersimpan';

    /**
     * Execute the console command.
     */
    public function handle(QuizAttemptService $quizAttemptService): int
    {
        $closed = 0;

        QuizAttempt::query()
            ->where('status', QuizAttempt::STATUS_IN_PROGRESS)
            ->whereHas('quiz', fn ($query) => $query->whereNotNull('time_limit_minutes'))
            ->with('quiz')
            ->chunkById(100, function (Collection $attempts) use ($quizAttemptService, &$closed) {
                foreach ($attempts as $attempt) {
                    if ($attempt->isExpired()) {
                        $quizAttemptService->submit($attempt, []);
                        $closed++;
                    }
                }
            });

        $this->info("{$closed} attempt kadaluarsa ditutup.");

        return self::SUCCESS;
    }
}
