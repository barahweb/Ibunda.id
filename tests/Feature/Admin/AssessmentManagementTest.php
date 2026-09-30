<?php

use App\Models\Assessment;
use App\Models\AssessmentQuestion;
use App\Models\User;
use Livewire\Volt\Volt;

function seedPublishReadyAdminAssessment(User $admin): Assessment
{
    $assessment = Assessment::factory()->for($admin, 'creator')->draft()->create();

    foreach (AssessmentQuestion::DIMENSIONS as $dimension) {
        AssessmentQuestion::factory()->for($assessment)->dimension($dimension)->count(8)->create();
    }

    return $assessment;
}

test('guest is redirected to login', function () {
    $response = $this->get(route('admin.assessments.index'));

    $response->assertRedirect(route('login'));
});

test('participant cannot access assessment management', function () {
    $participant = User::factory()->create();

    $response = $this->actingAs($participant)->get(route('admin.assessments.index'));

    $response->assertForbidden();
});

test('admin can view the assessment list', function () {
    $admin = User::factory()->admin()->create();
    Assessment::factory()->for($admin, 'creator')->create(['title' => 'Tes Kepribadian (OEJTS)']);

    $response = $this->actingAs($admin)->get(route('admin.assessments.index'));

    $response->assertOk()->assertSee('Tes Kepribadian (OEJTS)');
});

test('admin can create an assessment as draft', function () {
    $admin = User::factory()->admin()->create();
    $this->actingAs($admin);

    Volt::test('admin.assessments.index')
        ->call('createAssessment')
        ->set('title', 'Tes Kepribadian Baru')
        ->set('description', 'Deskripsi singkat')
        ->call('save')
        ->assertHasNoErrors();

    $assessment = Assessment::where('title', 'Tes Kepribadian Baru')->first();

    expect($assessment)->not->toBeNull();
    expect($assessment->status)->toBe(Assessment::STATUS_DRAFT);
    expect($assessment->created_by)->toBe($admin->id);
});

test('title is required to save an assessment', function () {
    $admin = User::factory()->admin()->create();
    $this->actingAs($admin);

    Volt::test('admin.assessments.index')
        ->call('createAssessment')
        ->set('title', '')
        ->call('save')
        ->assertHasErrors(['title' => 'required']);
});

test('admin can edit an existing assessment', function () {
    $admin = User::factory()->admin()->create();
    $this->actingAs($admin);
    $assessment = Assessment::factory()->for($admin, 'creator')->create(['title' => 'Judul Lama']);

    Volt::test('admin.assessments.index')
        ->call('editAssessment', $assessment->id)
        ->set('title', 'Judul Baru')
        ->call('save')
        ->assertHasNoErrors();

    expect($assessment->refresh()->title)->toBe('Judul Baru');
});

test('admin can delete an assessment', function () {
    $admin = User::factory()->admin()->create();
    $this->actingAs($admin);
    $assessment = Assessment::factory()->for($admin, 'creator')->create();

    Volt::test('admin.assessments.index')->call('deleteAssessment', $assessment->id);

    expect(Assessment::find($assessment->id))->toBeNull();
});

test('an assessment without exactly 32 questions cannot be published', function () {
    $admin = User::factory()->admin()->create();
    $this->actingAs($admin);
    $assessment = Assessment::factory()->for($admin, 'creator')->draft()->create();
    AssessmentQuestion::factory()->for($assessment)->dimension('EI')->count(3)->create();

    Volt::test('admin.assessments.index')
        ->call('togglePublish', $assessment->id)
        ->assertDispatched('toast-show');

    expect($assessment->refresh()->status)->toBe(Assessment::STATUS_DRAFT);
});

test('an assessment with exactly 32 questions split 8-8-8-8 can be published', function () {
    $admin = User::factory()->admin()->create();
    $this->actingAs($admin);
    $assessment = seedPublishReadyAdminAssessment($admin);

    Volt::test('admin.assessments.index')
        ->call('togglePublish', $assessment->id)
        ->assertHasNoErrors();

    expect($assessment->refresh()->status)->toBe(Assessment::STATUS_PUBLISHED);
});

test('search filters assessments by title', function () {
    $admin = User::factory()->admin()->create();
    $this->actingAs($admin);
    Assessment::factory()->for($admin, 'creator')->create(['title' => 'Tes Kepribadian']);
    Assessment::factory()->for($admin, 'creator')->create(['title' => 'Tes Stres']);

    Volt::test('admin.assessments.index')
        ->set('search', 'Kepribadian')
        ->assertSee('Tes Kepribadian')
        ->assertDontSee('Tes Stres');
});
