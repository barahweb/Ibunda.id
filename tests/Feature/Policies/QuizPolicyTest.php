<?php

use App\Models\Quiz;
use App\Models\User;
use App\Policies\QuizPolicy;

test('admin can view any quizzes but participant cannot', function () {
    $policy = new QuizPolicy;
    $admin = User::factory()->admin()->make();
    $participant = User::factory()->make();

    expect($policy->viewAny($admin))->toBeTrue();
    expect($policy->viewAny($participant))->toBeFalse();
});

test('anyone can view a published quiz, only admin can view a draft', function () {
    $policy = new QuizPolicy;
    $admin = User::factory()->admin()->make();
    $participant = User::factory()->make();
    $published = Quiz::factory()->published()->make();
    $draft = Quiz::factory()->draft()->make();

    expect($policy->view($admin, $published))->toBeTrue();
    expect($policy->view($participant, $published))->toBeTrue();
    expect($policy->view($admin, $draft))->toBeTrue();
    expect($policy->view($participant, $draft))->toBeFalse();
});

test('only admin can create, update, and delete quizzes', function () {
    $policy = new QuizPolicy;
    $admin = User::factory()->admin()->make();
    $participant = User::factory()->make();
    $quiz = Quiz::factory()->make();

    expect($policy->create($admin))->toBeTrue();
    expect($policy->create($participant))->toBeFalse();
    expect($policy->update($admin, $quiz))->toBeTrue();
    expect($policy->update($participant, $quiz))->toBeFalse();
    expect($policy->delete($admin, $quiz))->toBeTrue();
    expect($policy->delete($participant, $quiz))->toBeFalse();
});
