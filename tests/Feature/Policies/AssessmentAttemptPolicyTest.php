<?php

use App\Models\AssessmentAttempt;
use App\Models\User;
use App\Policies\AssessmentAttemptPolicy;

test('admin can view any attempt list but participant cannot', function () {
    $policy = new AssessmentAttemptPolicy;
    $admin = User::factory()->admin()->create();
    $participant = User::factory()->create();

    expect($policy->viewAny($admin))->toBeTrue();
    expect($policy->viewAny($participant))->toBeFalse();
});

test('admin and the owner can view an attempt, a stranger cannot', function () {
    $policy = new AssessmentAttemptPolicy;
    $admin = User::factory()->admin()->create();
    $owner = User::factory()->create();
    $stranger = User::factory()->create();
    $attempt = AssessmentAttempt::factory()->for($owner)->create();

    expect($policy->view($admin, $attempt))->toBeTrue();
    expect($policy->view($owner, $attempt))->toBeTrue();
    expect($policy->view($stranger, $attempt))->toBeFalse();
});

test('nobody, including the owner and admin, can create/update/delete an attempt via the policy', function () {
    $policy = new AssessmentAttemptPolicy;
    $admin = User::factory()->admin()->create();
    $owner = User::factory()->create();
    $attempt = AssessmentAttempt::factory()->for($owner)->create();

    expect($policy->create($owner))->toBeFalse();
    expect($policy->create($admin))->toBeFalse();
    expect($policy->update($owner, $attempt))->toBeFalse();
    expect($policy->update($admin, $attempt))->toBeFalse();
    expect($policy->delete($owner, $attempt))->toBeFalse();
    expect($policy->delete($admin, $attempt))->toBeFalse();
});
