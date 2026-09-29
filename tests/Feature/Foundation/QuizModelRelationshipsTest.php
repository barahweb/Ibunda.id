<?php

use App\Models\Question;
use App\Models\QuestionOption;
use App\Models\Quiz;
use App\Models\QuizAnswer;
use App\Models\QuizAttempt;
use App\Models\User;

test('quiz belongs to its creator and has many questions', function () {
    $admin = User::factory()->admin()->create();
    $quiz = Quiz::factory()->for($admin, 'creator')->create();
    $question = Question::factory()->for($quiz)->create();

    expect($quiz->creator->is($admin))->toBeTrue();
    expect($admin->quizzesCreated->pluck('id'))->toContain($quiz->id);
    expect($quiz->questions->pluck('id'))->toContain($question->id);
});

test('question has many options and exposes the correct one', function () {
    $question = Question::factory()->create();
    $correct = QuestionOption::factory()->for($question)->correct()->create();
    QuestionOption::factory()->for($question)->count(3)->create();

    expect($question->options)->toHaveCount(4);
    expect($question->correctOption->is($correct))->toBeTrue();
});

test('quiz attempt belongs to quiz and user, and tracks its answers', function () {
    $user = User::factory()->create();
    $quiz = Quiz::factory()->published()->create();
    $question = Question::factory()->for($quiz)->create();
    $option = QuestionOption::factory()->for($question)->correct()->create();

    $attempt = QuizAttempt::factory()->for($quiz)->for($user)->completed(80)->create();
    $answer = QuizAnswer::factory()
        ->for($attempt, 'attempt')
        ->for($question)
        ->create(['selected_option_id' => $option->id, 'is_correct' => true, 'points_awarded' => 1]);

    expect($attempt->quiz->is($quiz))->toBeTrue();
    expect($attempt->user->is($user))->toBeTrue();
    expect($attempt->answers->pluck('id'))->toContain($answer->id);
    expect($answer->selectedOption->is($option))->toBeTrue();
    expect($user->quizAttempts->pluck('id'))->toContain($attempt->id);
});

test('attempt passes when score meets the quiz passing score, fails otherwise', function () {
    $quiz = Quiz::factory()->create(['passing_score' => 70]);

    $passed = QuizAttempt::factory()->for($quiz)->completed(80)->create();
    $failed = QuizAttempt::factory()->for($quiz)->completed(50)->create();
    $inProgress = QuizAttempt::factory()->for($quiz)->create();

    expect($passed->hasPassed())->toBeTrue();
    expect($failed->hasPassed())->toBeFalse();
    expect($inProgress->hasPassed())->toBeNull();
});
