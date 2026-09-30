<?php

use App\Models\Assessment;
use App\Models\User;
use App\Policies\AssessmentPolicy;

test('admin can view any assessments but participant cannot', function () {
    $policy = new AssessmentPolicy;
    $admin = User::factory()->admin()->make();
    $participant = User::factory()->make();

    expect($policy->viewAny($admin))->toBeTrue();
    expect($policy->viewAny($participant))->toBeFalse();
});

test('anyone can view a published assessment, only admin can view a draft', function () {
    $policy = new AssessmentPolicy;
    $admin = User::factory()->admin()->make();
    $participant = User::factory()->make();
    $published = Assessment::factory()->published()->make();
    $draft = Assessment::factory()->draft()->make();

    expect($policy->view($admin, $published))->toBeTrue();
    expect($policy->view($participant, $published))->toBeTrue();
    expect($policy->view($admin, $draft))->toBeTrue();
    expect($policy->view($participant, $draft))->toBeFalse();
});

test('only admin can create, update, and delete assessments', function () {
    $policy = new AssessmentPolicy;
    $admin = User::factory()->admin()->make();
    $participant = User::factory()->make();
    $assessment = Assessment::factory()->make();

    expect($policy->create($admin))->toBeTrue();
    expect($policy->create($participant))->toBeFalse();
    expect($policy->update($admin, $assessment))->toBeTrue();
    expect($policy->update($participant, $assessment))->toBeFalse();
    expect($policy->delete($admin, $assessment))->toBeTrue();
    expect($policy->delete($participant, $assessment))->toBeFalse();
});
