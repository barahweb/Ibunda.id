<?php

use App\Models\Question;
use App\Models\QuestionOption;
use App\Models\Quiz;
use App\Models\QuizAttempt;
use App\Services\QuizAttemptService;
use Illuminate\Console\Scheduling\Schedule;

/**
 * Published quiz with 2 questions (option 0 correct, 1 point each).
 */
function createQuizForScheduler(?int $minutes): Quiz
{
    $quiz = Quiz::factory()->published()->create(['time_limit_minutes' => $minutes, 'passing_score' => 70]);

    for ($i = 0; $i < 2; $i++) {
        $question = Question::factory()->for($quiz)->create(['points' => 1, 'order' => $i]);
        QuestionOption::factory()->for($question)->correct()->create();
        QuestionOption::factory()->for($question)->create();
    }

    return $quiz;
}

test('an overdue in-progress attempt is closed and graded from its saved answers', function () {
    $quiz = createQuizForScheduler(10);
    $questions = $quiz->questions()->with('options')->get();
    $attempt = QuizAttempt::factory()->for($quiz)->create(['started_at' => now()->subMinutes(5)]);

    app(QuizAttemptService::class)->saveAnswer($attempt, $questions[0]->id, $questions[0]->options->firstWhere('is_correct', true)->id);

    $this->travel(6)->minutes();

    $this->artisan('quiz:close-expired-attempts')
        ->expectsOutput('1 attempt kadaluarsa ditutup.')
        ->assertSuccessful();

    $attempt->refresh();

    expect($attempt->status)->toBe(QuizAttempt::STATUS_COMPLETED);
    expect($attempt->score)->toBe(50);
    expect($attempt->answers)->toHaveCount(2);
});

test('an attempt that still has time left is left alone', function () {
    $quiz = createQuizForScheduler(10);
    $attempt = QuizAttempt::factory()->for($quiz)->create(['started_at' => now()->subMinutes(5)]);

    $this->artisan('quiz:close-expired-attempts')
        ->expectsOutput('0 attempt kadaluarsa ditutup.')
        ->assertSuccessful();

    expect($attempt->refresh()->status)->toBe(QuizAttempt::STATUS_IN_PROGRESS);
});

test('an untimed in-progress attempt is never closed', function () {
    $quiz = createQuizForScheduler(null);
    $attempt = QuizAttempt::factory()->for($quiz)->create(['started_at' => now()->subDays(5)]);

    $this->artisan('quiz:close-expired-attempts')->assertSuccessful();

    expect($attempt->refresh()->status)->toBe(QuizAttempt::STATUS_IN_PROGRESS);
});

test('an already completed attempt keeps its score', function () {
    $quiz = createQuizForScheduler(10);
    $attempt = QuizAttempt::factory()->for($quiz)->completed(90)->create(['started_at' => now()->subHours(2)]);

    $this->artisan('quiz:close-expired-attempts')
        ->expectsOutput('0 attempt kadaluarsa ditutup.')
        ->assertSuccessful();

    expect($attempt->refresh()->score)->toBe(90);
});

test('the command is scheduled to run every minute', function () {
    $event = collect(app(Schedule::class)->events())
        ->first(fn ($event) => str_contains($event->command, 'quiz:close-expired-attempts'));

    expect($event)->not->toBeNull();
    expect($event->expression)->toBe('* * * * *');
    expect($event->withoutOverlapping)->toBeTrue();
});
