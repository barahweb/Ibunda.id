<?php

use App\Models\Question;
use App\Models\QuestionOption;
use App\Models\Quiz;
use App\Models\QuizAttempt;
use App\Models\User;
use Livewire\Volt\Volt;

/**
 * Creates a published quiz with $count questions, each with 2 options
 * (index 0 correct, index 1 wrong), 1 point each.
 */
function createPublishedQuizWithQuestions(int $count = 2): Quiz
{
    $quiz = Quiz::factory()->published()->create(['passing_score' => 70]);

    for ($i = 0; $i < $count; $i++) {
        $question = Question::factory()->for($quiz)->create(['points' => 1, 'order' => $i]);
        QuestionOption::factory()->for($question)->correct()->create(['option_text' => "Correct {$i}"]);
        QuestionOption::factory()->for($question)->create(['option_text' => "Wrong {$i}"]);
    }

    return $quiz;
}

test('guest is redirected to login', function () {
    $quiz = createPublishedQuizWithQuestions();

    $this->get(route('quizzes.index'))->assertRedirect(route('login'));
    $this->get(route('quizzes.attempt', $quiz))->assertRedirect(route('login'));
});

test('quiz list only shows published quizzes', function () {
    $participant = User::factory()->create();
    $published = createPublishedQuizWithQuestions(1);
    $published->update(['title' => 'Quiz Terbit']);
    Quiz::factory()->draft()->create(['title' => 'Quiz Draft']);

    $response = $this->actingAs($participant)->get(route('quizzes.index'));

    $response->assertOk()->assertSee('Quiz Terbit')->assertDontSee('Quiz Draft');
});

test('participant cannot attempt a draft quiz', function () {
    $participant = User::factory()->create();
    $quiz = Quiz::factory()->draft()->create();

    $response = $this->actingAs($participant)->get(route('quizzes.attempt', $quiz));

    $response->assertForbidden();
});

test('a published quiz without questions cannot be attempted', function () {
    $participant = User::factory()->create();
    $quiz = Quiz::factory()->published()->create();

    $response = $this->actingAs($participant)->get(route('quizzes.attempt', $quiz));

    $response->assertNotFound();
    expect(QuizAttempt::where('quiz_id', $quiz->id)->exists())->toBeFalse();
});

test('a published quiz without questions is hidden from the browse list', function () {
    $participant = User::factory()->create();
    $withQuestions = createPublishedQuizWithQuestions(1);
    $empty = Quiz::factory()->published()->create(['title' => 'Quiz Kosong']);

    $response = $this->actingAs($participant)->get(route('quizzes.index'));

    $response->assertSee($withQuestions->title)->assertDontSee('Quiz Kosong');
});

test('visiting the attempt page starts an in-progress attempt', function () {
    $participant = User::factory()->create();
    $quiz = createPublishedQuizWithQuestions();

    $this->actingAs($participant)->get(route('quizzes.attempt', $quiz))->assertOk();

    expect(QuizAttempt::where('quiz_id', $quiz->id)->where('user_id', $participant->id)->count())->toBe(1);
    expect(QuizAttempt::where('quiz_id', $quiz->id)->first()->status)->toBe(QuizAttempt::STATUS_IN_PROGRESS);
});

test('revisiting the attempt page resumes the same in-progress attempt', function () {
    $participant = User::factory()->create();
    $quiz = createPublishedQuizWithQuestions();
    $this->actingAs($participant);

    $this->get(route('quizzes.attempt', $quiz));
    $this->get(route('quizzes.attempt', $quiz));

    expect(QuizAttempt::where('quiz_id', $quiz->id)->where('user_id', $participant->id)->count())->toBe(1);
});

test('submitting without answering all questions shows an error', function () {
    $participant = User::factory()->create();
    $quiz = createPublishedQuizWithQuestions(2);
    $this->actingAs($participant);

    $questions = $quiz->questions()->with('options')->get();

    Volt::test('quizzes.attempt', ['quiz' => $quiz])
        ->set("answers.{$questions[0]->id}", $questions[0]->options[0]->id)
        ->call('submit')
        ->assertDispatched('toast-show');

    expect(QuizAttempt::where('quiz_id', $quiz->id)->first()->status)->toBe(QuizAttempt::STATUS_IN_PROGRESS);
});

test('a selected option that does not belong to its question is rejected as unanswered', function () {
    $participant = User::factory()->create();
    $quiz = createPublishedQuizWithQuestions(2);
    $this->actingAs($participant);

    $questions = $quiz->questions()->with('options')->get();
    $foreignOption = $questions[1]->options->first();

    Volt::test('quizzes.attempt', ['quiz' => $quiz])
        ->set("answers.{$questions[0]->id}", $foreignOption->id)
        ->set("answers.{$questions[1]->id}", $questions[1]->options->firstWhere('is_correct', true)->id)
        ->call('submit')
        ->assertHasNoErrors();

    $attempt = QuizAttempt::where('quiz_id', $quiz->id)->first();
    $tamperedAnswer = $attempt->answers()->where('question_id', $questions[0]->id)->first();

    expect($tamperedAnswer->selected_option_id)->toBeNull();
    expect($tamperedAnswer->is_correct)->toBeFalse();
    expect($attempt->score)->toBe(50);
});

test('submitting all correct answers scores 100 and passes', function () {
    $participant = User::factory()->create();
    $quiz = createPublishedQuizWithQuestions(2);
    $this->actingAs($participant);

    $questions = $quiz->questions()->with('options')->get();

    $component = Volt::test('quizzes.attempt', ['quiz' => $quiz]);

    foreach ($questions as $question) {
        $correctOption = $question->options->firstWhere('is_correct', true);
        $component->set("answers.{$question->id}", $correctOption->id);
    }

    $component->call('submit')->assertHasNoErrors();

    $attempt = QuizAttempt::where('quiz_id', $quiz->id)->first();

    expect($attempt->status)->toBe(QuizAttempt::STATUS_COMPLETED);
    expect($attempt->score)->toBe(100);
    expect($attempt->hasPassed())->toBeTrue();
    expect($attempt->answers)->toHaveCount(2);
});

test('submitting a mix of correct and wrong answers calculates score proportionally', function () {
    $participant = User::factory()->create();
    $quiz = createPublishedQuizWithQuestions(2);
    $this->actingAs($participant);

    $questions = $quiz->questions()->with('options')->get();

    $component = Volt::test('quizzes.attempt', ['quiz' => $quiz])
        ->set("answers.{$questions[0]->id}", $questions[0]->options->firstWhere('is_correct', true)->id)
        ->set("answers.{$questions[1]->id}", $questions[1]->options->firstWhere('is_correct', false)->id)
        ->call('submit')
        ->assertHasNoErrors();

    $attempt = QuizAttempt::where('quiz_id', $quiz->id)->first();

    expect($attempt->score)->toBe(50);
    expect($attempt->hasPassed())->toBeFalse();

    $wrongAnswer = $attempt->answers()->where('question_id', $questions[1]->id)->first();
    expect($wrongAnswer->is_correct)->toBeFalse();
    expect($wrongAnswer->points_awarded)->toBe(0);
});

test('a completed attempt cannot be resubmitted', function () {
    $participant = User::factory()->create();
    $quiz = createPublishedQuizWithQuestions(1);
    $this->actingAs($participant);

    $question = $quiz->questions()->with('options')->first();
    $correctOption = $question->options->firstWhere('is_correct', true);

    $component = Volt::test('quizzes.attempt', ['quiz' => $quiz])
        ->set("answers.{$question->id}", $correctOption->id)
        ->call('submit');

    $attempt = QuizAttempt::where('quiz_id', $quiz->id)->first();
    expect($attempt->answers)->toHaveCount(1);

    $component->call('submit');

    expect($attempt->refresh()->answers)->toHaveCount(1);
});
