<?php

use App\Models\Assessment;
use App\Models\AssessmentAnswer;
use App\Models\AssessmentAttempt;
use App\Models\AssessmentQuestion;
use App\Models\User;

test('assessment belongs to its creator and has many questions', function () {
    $admin = User::factory()->admin()->create();
    $assessment = Assessment::factory()->for($admin, 'creator')->create();
    $question = AssessmentQuestion::factory()->for($assessment)->create();

    expect($assessment->creator->is($admin))->toBeTrue();
    expect($admin->assessmentsCreated->pluck('id'))->toContain($assessment->id);
    expect($assessment->questions->pluck('id'))->toContain($question->id);
});

test('assessment question exposes its left and right letters from the dimension', function () {
    $eiQuestion = AssessmentQuestion::factory()->dimension('EI')->create();
    $jpQuestion = AssessmentQuestion::factory()->dimension('JP')->create();

    expect($eiQuestion->leftLetter())->toBe('E');
    expect($eiQuestion->rightLetter())->toBe('I');
    expect($jpQuestion->leftLetter())->toBe('J');
    expect($jpQuestion->rightLetter())->toBe('P');
});

test('assessment attempt belongs to assessment and user, and tracks its answers', function () {
    $user = User::factory()->create();
    $assessment = Assessment::factory()->published()->create();
    $question = AssessmentQuestion::factory()->for($assessment)->create();

    $attempt = AssessmentAttempt::factory()->for($assessment)->for($user)->completed('INTJ')->create();
    $answer = AssessmentAnswer::factory()
        ->for($attempt, 'attempt')
        ->for($question, 'question')
        ->create(['value' => 4]);

    expect($attempt->assessment->is($assessment))->toBeTrue();
    expect($attempt->user->is($user))->toBeTrue();
    expect($attempt->answers->pluck('id'))->toContain($answer->id);
    expect($answer->question->is($question))->toBeTrue();
    expect($user->assessmentAttempts->pluck('id'))->toContain($attempt->id);
});

test('a completed attempt stores its result type and dimension scores', function () {
    $attempt = AssessmentAttempt::factory()->completed('ENFP', ['EI' => 30, 'SN' => 28, 'TF' => 26, 'JP' => 20])->create();
    $inProgress = AssessmentAttempt::factory()->create();

    expect($attempt->isCompleted())->toBeTrue();
    expect($attempt->result_type)->toBe('ENFP');
    expect($attempt->dimension_scores)->toBe(['EI' => 30, 'SN' => 28, 'TF' => 26, 'JP' => 20]);
    expect($inProgress->isCompleted())->toBeFalse();
    expect($inProgress->result_type)->toBeNull();
});
