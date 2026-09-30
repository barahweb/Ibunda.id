<?php

use App\Models\Question;
use App\Models\QuestionOption;
use App\Models\Quiz;
use App\Models\QuizAttempt;
use App\Models\User;
use Livewire\Volt\Volt;

/**
 * Published quiz with $questions questions (option 0 correct, 1 point each).
 */
function createTimedQuiz(?int $minutes, int $questions = 2): Quiz
{
    $quiz = Quiz::factory()->published()->create(['time_limit_minutes' => $minutes, 'passing_score' => 70]);

    for ($i = 0; $i < $questions; $i++) {
        $question = Question::factory()->for($quiz)->create(['points' => 1, 'order' => $i]);
        QuestionOption::factory()->for($question)->correct()->create(['option_text' => "Correct {$i}"]);
        QuestionOption::factory()->for($question)->create(['option_text' => "Wrong {$i}"]);
    }

    return $quiz;
}

test('an attempt without a time limit never expires', function () {
    $quiz = createTimedQuiz(null);
    $attempt = QuizAttempt::factory()->for($quiz)->create(['started_at' => now()->subDays(3)]);

    expect($attempt->deadline())->toBeNull();
    expect($attempt->isExpired())->toBeFalse();
});

test('an attempt expires once its time limit has passed', function () {
    $quiz = createTimedQuiz(10);
    $fresh = QuizAttempt::factory()->for($quiz)->create(['started_at' => now()->subMinutes(5)]);
    $stale = QuizAttempt::factory()->for($quiz)->create(['started_at' => now()->subMinutes(11)]);

    expect($fresh->isExpired())->toBeFalse();
    expect($stale->isExpired())->toBeTrue();
});

test('a completed attempt is never considered expired', function () {
    $quiz = createTimedQuiz(10);
    $attempt = QuizAttempt::factory()->for($quiz)->completed(100)->create(['started_at' => now()->subHours(2)]);

    expect($attempt->isExpired())->toBeFalse();
});

test('the countdown is shown only for quizzes that have a time limit', function () {
    $participant = User::factory()->create();
    $timed = createTimedQuiz(10);
    $untimed = createTimedQuiz(null);
    $this->actingAs($participant);

    $this->get(route('quizzes.attempt', $timed))->assertOk()->assertSee('Sisa waktu');
    $this->get(route('quizzes.attempt', $untimed))->assertOk()->assertDontSee('Sisa waktu');
});

test('opening an expired in-progress attempt closes it automatically', function () {
    $participant = User::factory()->create();
    $quiz = createTimedQuiz(10);
    $attempt = QuizAttempt::factory()->for($quiz)->for($participant)->create(['started_at' => now()->subMinutes(11)]);

    $this->actingAs($participant)
        ->get(route('quizzes.attempt', $quiz))
        ->assertOk()
        ->assertSee('Quiz Selesai');

    $attempt->refresh();

    expect($attempt->status)->toBe(QuizAttempt::STATUS_COMPLETED);
    expect($attempt->score)->toBe(0);
    expect($attempt->answers)->toHaveCount(2);
});

test('auto submit scores whatever was answered once time is up', function () {
    $participant = User::factory()->create();
    $quiz = createTimedQuiz(1);
    $questions = $quiz->questions()->with('options')->get();
    $this->actingAs($participant);

    $component = Volt::test('quizzes.attempt', ['quiz' => $quiz])
        ->set("answers.{$questions[0]->id}", $questions[0]->options->firstWhere('is_correct', true)->id);

    $this->travel(2)->minutes();

    $component->call('autoSubmit')->assertHasNoErrors();

    $attempt = QuizAttempt::where('quiz_id', $quiz->id)->first();

    expect($attempt->status)->toBe(QuizAttempt::STATUS_COMPLETED);
    expect($attempt->score)->toBe(50);
});

test('auto submit is rejected while there is still time left', function () {
    $participant = User::factory()->create();
    $quiz = createTimedQuiz(10);
    $this->actingAs($participant);

    Volt::test('quizzes.attempt', ['quiz' => $quiz])
        ->call('autoSubmit')
        ->assertStatus(403);

    expect(QuizAttempt::where('quiz_id', $quiz->id)->first()->status)->toBe(QuizAttempt::STATUS_IN_PROGRESS);
});

test('remaining seconds counts down to the deadline and stops at zero', function () {
    $this->freezeTime();
    $quiz = createTimedQuiz(10);
    $fresh = QuizAttempt::factory()->for($quiz)->create(['started_at' => now()->subMinutes(4)]);
    $stale = QuizAttempt::factory()->for($quiz)->create(['started_at' => now()->subMinutes(11)]);
    $untimed = QuizAttempt::factory()->for(createTimedQuiz(null))->create();

    expect($fresh->remainingSeconds())->toBe(360);
    expect($stale->remainingSeconds())->toBe(0);
    expect($untimed->remainingSeconds())->toBeNull();
});

test('history shows the remaining time for an in-progress timed attempt', function () {
    $this->freezeTime();
    $participant = User::factory()->create();
    $quiz = createTimedQuiz(10);
    QuizAttempt::factory()->for($quiz)->for($participant)->create(['started_at' => now()]);

    $this->actingAs($participant)
        ->get(route('quizzes.history'))
        ->assertOk()
        ->assertSee('Sisa Waktu')
        ->assertSee('remaining: 600');
});

test('history marks an overdue in-progress attempt as out of time', function () {
    $participant = User::factory()->create();
    $quiz = createTimedQuiz(10);
    QuizAttempt::factory()->for($quiz)->for($participant)->create(['started_at' => now()->subMinutes(11)]);

    $this->actingAs($participant)
        ->get(route('quizzes.history'))
        ->assertOk()
        ->assertSee('Waktu habis');
});

test('history shows no time limit for an untimed in-progress attempt', function () {
    $participant = User::factory()->create();
    QuizAttempt::factory()->for(createTimedQuiz(null))->for($participant)->create();

    $this->actingAs($participant)
        ->get(route('quizzes.history'))
        ->assertOk()
        ->assertSee('Tanpa batas waktu');
});

test('history does not show a countdown for a completed attempt', function () {
    $participant = User::factory()->create();
    QuizAttempt::factory()->for(createTimedQuiz(10))->for($participant)->completed(90)->create();

    $this->actingAs($participant)
        ->get(route('quizzes.history'))
        ->assertOk()
        ->assertDontSee('remaining:')
        ->assertDontSee('Tanpa batas waktu');
});

test('auto submit tolerates a few seconds of clock drift before the real deadline', function () {
    $participant = User::factory()->create();
    $quiz = createTimedQuiz(10);
    $this->actingAs($participant);

    $component = Volt::test('quizzes.attempt', ['quiz' => $quiz]);

    // 3 detik tersisa dari deadline asli, masih di dalam toleransi grace period 5 detik.
    $this->travel(597)->seconds();

    $component->call('autoSubmit')->assertHasNoErrors();

    expect(QuizAttempt::where('quiz_id', $quiz->id)->first()->status)->toBe(QuizAttempt::STATUS_COMPLETED);
});
