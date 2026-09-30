<?php

use App\Models\Question;
use App\Models\QuestionOption;
use App\Models\Quiz;
use App\Models\QuizAttempt;
use App\Models\User;
use App\Services\QuizAttemptService;
use Illuminate\Support\Str;
use Livewire\Volt\Volt;

/**
 * Published quiz with $questions questions (option 0 correct, option 1 wrong, 1 point each).
 */
function createAutosaveQuiz(int $questions = 2, ?int $minutes = null): Quiz
{
    $quiz = Quiz::factory()->published()->create(['time_limit_minutes' => $minutes, 'passing_score' => 70]);

    for ($i = 0; $i < $questions; $i++) {
        $question = Question::factory()->for($quiz)->create(['points' => 1, 'order' => $i]);
        QuestionOption::factory()->for($question)->correct()->create(['option_text' => "Correct {$i}"]);
        QuestionOption::factory()->for($question)->create(['option_text' => "Wrong {$i}"]);
    }

    return $quiz;
}

test('picking an option is saved to the attempt right away', function () {
    $participant = User::factory()->create();
    $quiz = createAutosaveQuiz();
    $question = $quiz->questions()->with('options')->first();
    $wrongOption = $question->options->firstWhere('is_correct', false);
    $this->actingAs($participant);

    Volt::test('quizzes.attempt', ['quiz' => $quiz])->call('saveAnswer', $question->id, $wrongOption->id);

    $answer = QuizAttempt::where('quiz_id', $quiz->id)->first()->answers()->first();

    expect($answer->question_id)->toBe($question->id);
    expect($answer->selected_option_id)->toBe($wrongOption->id);
});

test('changing an answer updates the same saved row', function () {
    $participant = User::factory()->create();
    $quiz = createAutosaveQuiz();
    $question = $quiz->questions()->with('options')->first();
    $this->actingAs($participant);

    $component = Volt::test('quizzes.attempt', ['quiz' => $quiz])
        ->call('saveAnswer', $question->id, $question->options->firstWhere('is_correct', false)->id)
        ->call('saveAnswer', $question->id, $question->options->firstWhere('is_correct', true)->id);

    $answers = QuizAttempt::where('quiz_id', $quiz->id)->first()->answers;

    expect($answers)->toHaveCount(1);
    expect($answers->first()->selected_option_id)->toBe($question->options->firstWhere('is_correct', true)->id);
});

test('an option from another question is not saved', function () {
    $participant = User::factory()->create();
    $quiz = createAutosaveQuiz();
    $questions = $quiz->questions()->with('options')->get();
    $this->actingAs($participant);

    Volt::test('quizzes.attempt', ['quiz' => $quiz])
        ->call('saveAnswer', $questions[0]->id, $questions[1]->options->first()->id);

    expect(QuizAttempt::where('quiz_id', $quiz->id)->first()->answers)->toHaveCount(0);
});

test('an unknown question is not saved', function () {
    $participant = User::factory()->create();
    $quiz = createAutosaveQuiz();
    $option = $quiz->questions()->with('options')->first()->options->first();
    $this->actingAs($participant);

    Volt::test('quizzes.attempt', ['quiz' => $quiz])
        ->call('saveAnswer', (string) Str::uuid(), $option->id);

    expect(QuizAttempt::where('quiz_id', $quiz->id)->first()->answers)->toHaveCount(0);
});

test('a completed attempt can no longer change its saved answers', function () {
    $participant = User::factory()->create();
    $quiz = createAutosaveQuiz(1);
    $question = $quiz->questions()->with('options')->first();
    $correctOption = $question->options->firstWhere('is_correct', true);
    $wrongOption = $question->options->firstWhere('is_correct', false);
    $this->actingAs($participant);

    Volt::test('quizzes.attempt', ['quiz' => $quiz])
        ->set("answers.{$question->id}", $correctOption->id)
        ->call('submit')
        ->call('saveAnswer', $question->id, $wrongOption->id);

    $answer = QuizAttempt::where('quiz_id', $quiz->id)->first()->answers()->first();

    expect($answer->selected_option_id)->toBe($correctOption->id);
    expect($answer->is_correct)->toBeTrue();
});

test('answers picked after the deadline are not saved', function () {
    $participant = User::factory()->create();
    $quiz = createAutosaveQuiz(1, minutes: 1);
    $question = $quiz->questions()->with('options')->first();
    $this->actingAs($participant);

    $component = Volt::test('quizzes.attempt', ['quiz' => $quiz]);

    $this->travel(2)->minutes();

    $component->call('saveAnswer', $question->id, $question->options->first()->id);

    expect(QuizAttempt::where('quiz_id', $quiz->id)->first()->answers)->toHaveCount(0);
});

test('reopening an in-progress attempt restores the saved answers', function () {
    $participant = User::factory()->create();
    $quiz = createAutosaveQuiz();
    $question = $quiz->questions()->with('options')->first();
    $option = $question->options->firstWhere('is_correct', true);
    $this->actingAs($participant);

    Volt::test('quizzes.attempt', ['quiz' => $quiz])->call('saveAnswer', $question->id, $option->id);

    Volt::test('quizzes.attempt', ['quiz' => $quiz])
        ->assertSet('answers', [$question->id => $option->id]);
});

test('submitting scores the saved answers for questions the browser did not send', function () {
    $participant = User::factory()->create();
    $quiz = createAutosaveQuiz();
    $questions = $quiz->questions()->with('options')->get();
    $attempt = QuizAttempt::factory()->for($quiz)->for($participant)->create();
    $service = app(QuizAttemptService::class);

    $service->saveAnswer($attempt, $questions[0]->id, $questions[0]->options->firstWhere('is_correct', true)->id);
    $service->submit($attempt, []);

    $attempt->refresh();

    expect($attempt->score)->toBe(50);
    expect($attempt->answers)->toHaveCount(2);
});

test('an expired attempt is graded from its saved answers when reopened', function () {
    $participant = User::factory()->create();
    $quiz = createAutosaveQuiz(2, minutes: 1);
    $questions = $quiz->questions()->with('options')->get();
    $attempt = QuizAttempt::factory()->for($quiz)->for($participant)->create();

    app(QuizAttemptService::class)->saveAnswer($attempt, $questions[0]->id, $questions[0]->options->firstWhere('is_correct', true)->id);

    $this->travel(2)->minutes();

    $this->actingAs($participant)
        ->get(route('quizzes.attempt', $quiz))
        ->assertOk()
        ->assertSee('Quiz Selesai');

    $attempt->refresh();

    expect($attempt->status)->toBe(QuizAttempt::STATUS_COMPLETED);
    expect($attempt->score)->toBe(50);
});

test('history shows how many questions are answered in an in-progress attempt', function () {
    $participant = User::factory()->create();
    $quiz = createAutosaveQuiz(2);
    $questions = $quiz->questions()->with('options')->get();
    $attempt = QuizAttempt::factory()->for($quiz)->for($participant)->create();

    app(QuizAttemptService::class)->saveAnswer($attempt, $questions[0]->id, $questions[0]->options->first()->id);

    $this->actingAs($participant)
        ->get(route('quizzes.history'))
        ->assertOk()
        ->assertSee('Progress')
        ->assertSee('1/2 soal terjawab');
});

test('history does not show answered progress for a completed attempt', function () {
    $participant = User::factory()->create();
    $quiz = createAutosaveQuiz(2);
    QuizAttempt::factory()->for($quiz)->for($participant)->completed(90)->create();

    $this->actingAs($participant)
        ->get(route('quizzes.history'))
        ->assertOk()
        ->assertDontSee('soal terjawab');
});

test('submitting with an empty string for a question does not discard its saved answer', function () {
    $quiz = createAutosaveQuiz(1);
    $question = $quiz->questions()->with('options')->first();
    $correctOption = $question->options->firstWhere('is_correct', true);
    $attempt = QuizAttempt::factory()->for($quiz)->create();
    $service = app(QuizAttemptService::class);

    $service->saveAnswer($attempt, $question->id, $correctOption->id);
    $service->submit($attempt, [$question->id => '']);

    $attempt->refresh();

    expect($attempt->score)->toBe(100);
    expect($attempt->answers()->first()->selected_option_id)->toBe($correctOption->id);
});
