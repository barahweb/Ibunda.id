<?php

use App\Models\Assessment;
use App\Models\AssessmentQuestion;
use App\Models\User;
use Livewire\Volt\Volt;

test('guest is redirected to login', function () {
    $assessment = Assessment::factory()->create();

    $response = $this->get(route('admin.assessments.questions', $assessment));

    $response->assertRedirect(route('login'));
});

test('participant cannot access question management', function () {
    $participant = User::factory()->create();
    $assessment = Assessment::factory()->create();

    $response = $this->actingAs($participant)->get(route('admin.assessments.questions', $assessment));

    $response->assertForbidden();
});

test('admin can view questions for an assessment', function () {
    $admin = User::factory()->admin()->create();
    $assessment = Assessment::factory()->for($admin, 'creator')->create();
    AssessmentQuestion::factory()->for($assessment)->dimension('EI')->create([
        'statement_left' => 'Suka bikin daftar',
        'statement_right' => 'Mengandalkan ingatan',
    ]);

    $response = $this->actingAs($admin)->get(route('admin.assessments.questions', $assessment));

    $response->assertOk()->assertSee('Suka bikin daftar')->assertSee('Mengandalkan ingatan');
});

test('admin can create a question', function () {
    $admin = User::factory()->admin()->create();
    $assessment = Assessment::factory()->for($admin, 'creator')->create();
    $this->actingAs($admin);

    Volt::test('admin.assessments.questions', ['assessment' => $assessment])
        ->call('createQuestion')
        ->set('dimension', 'EI')
        ->set('statementLeft', 'Suka bikin daftar')
        ->set('statementRight', 'Mengandalkan ingatan')
        ->call('save')
        ->assertHasNoErrors();

    $question = AssessmentQuestion::where('statement_left', 'Suka bikin daftar')->first();

    expect($question)->not->toBeNull();
    expect($question->dimension)->toBe('EI');
    expect($question->statement_right)->toBe('Mengandalkan ingatan');
});

test('dimension must be one of the four valid values', function () {
    $admin = User::factory()->admin()->create();
    $assessment = Assessment::factory()->for($admin, 'creator')->create();
    $this->actingAs($admin);

    Volt::test('admin.assessments.questions', ['assessment' => $assessment])
        ->call('createQuestion')
        ->set('dimension', 'XX')
        ->set('statementLeft', 'Kiri')
        ->set('statementRight', 'Kanan')
        ->call('save')
        ->assertHasErrors(['dimension']);
});

test('admin can edit a question', function () {
    $admin = User::factory()->admin()->create();
    $assessment = Assessment::factory()->for($admin, 'creator')->create();
    $question = AssessmentQuestion::factory()->for($assessment)->dimension('EI')->create(['statement_left' => 'Lama']);
    $this->actingAs($admin);

    Volt::test('admin.assessments.questions', ['assessment' => $assessment])
        ->call('editQuestion', $question->id)
        ->set('statementLeft', 'Baru')
        ->call('save')
        ->assertHasNoErrors();

    expect($question->refresh()->statement_left)->toBe('Baru');
});

test('creating a question is blocked via the form when the assessment is published', function () {
    $admin = User::factory()->admin()->create();
    $assessment = Assessment::factory()->for($admin, 'creator')->published()->create();
    $before = $assessment->questions()->count();
    $this->actingAs($admin);

    Volt::test('admin.assessments.questions', ['assessment' => $assessment])
        ->call('createQuestion')
        ->set('dimension', 'EI')
        ->set('statementLeft', 'Kiri')
        ->set('statementRight', 'Kanan')
        ->call('save')
        ->assertDispatched('toast-show');

    expect($assessment->questions()->count())->toBe($before);
});

test('editing a question is blocked via the form when the assessment is published', function () {
    $admin = User::factory()->admin()->create();
    $assessment = Assessment::factory()->for($admin, 'creator')->published()->create();
    $question = AssessmentQuestion::factory()->for($assessment)->dimension('EI')->create(['statement_left' => 'Asli']);
    $this->actingAs($admin);

    Volt::test('admin.assessments.questions', ['assessment' => $assessment])
        ->call('editQuestion', $question->id)
        ->set('statementLeft', 'Diubah')
        ->call('save')
        ->assertDispatched('toast-show');

    expect($question->refresh()->statement_left)->toBe('Asli');
});

test('admin can delete a question from a draft assessment', function () {
    $admin = User::factory()->admin()->create();
    $assessment = Assessment::factory()->for($admin, 'creator')->draft()->create();
    $question = AssessmentQuestion::factory()->for($assessment)->create();
    $this->actingAs($admin);

    Volt::test('admin.assessments.questions', ['assessment' => $assessment])->call('deleteQuestion', $question->id);

    expect(AssessmentQuestion::find($question->id))->toBeNull();
});

test('a question cannot be deleted while the assessment is published', function () {
    $admin = User::factory()->admin()->create();
    $assessment = Assessment::factory()->for($admin, 'creator')->published()->create();
    $question = AssessmentQuestion::factory()->for($assessment)->create();
    $this->actingAs($admin);

    Volt::test('admin.assessments.questions', ['assessment' => $assessment])
        ->call('deleteQuestion', $question->id)
        ->assertDispatched('toast-show');

    expect(AssessmentQuestion::find($question->id))->not->toBeNull();
});

test('a question from another assessment cannot be edited, deleted, or moved through this assessment page', function () {
    $admin = User::factory()->admin()->create();
    $assessment = Assessment::factory()->for($admin, 'creator')->draft()->create();
    $otherAssessment = Assessment::factory()->for($admin, 'creator')->draft()->create();
    $foreign = AssessmentQuestion::factory()->for($otherAssessment)->create(['statement_left' => 'Punya assessment lain', 'order' => 0]);
    $this->actingAs($admin);

    // Komponen baru tiap panggilan: setelah 1 request 404, instance-nya gak punya snapshot valid lagi.
    foreach (['editQuestion', 'deleteQuestion', 'moveDown'] as $action) {
        Volt::test('admin.assessments.questions', ['assessment' => $assessment])
            ->call($action, $foreign->id)
            ->assertNotFound();
    }

    expect($foreign->fresh()->statement_left)->toBe('Punya assessment lain');
    expect(AssessmentQuestion::find($foreign->id))->not->toBeNull();
});

test('admin can reorder questions', function () {
    $admin = User::factory()->admin()->create();
    $assessment = Assessment::factory()->for($admin, 'creator')->create();
    $first = AssessmentQuestion::factory()->for($assessment)->create(['order' => 0]);
    $second = AssessmentQuestion::factory()->for($assessment)->create(['order' => 1]);
    $this->actingAs($admin);

    Volt::test('admin.assessments.questions', ['assessment' => $assessment])->call('moveDown', $first->id);

    expect($first->refresh()->order)->toBe(1);
    expect($second->refresh()->order)->toBe(0);
});

test('the dimension progress badge reflects how many questions exist per dimension', function () {
    $admin = User::factory()->admin()->create();
    $assessment = Assessment::factory()->for($admin, 'creator')->create();
    AssessmentQuestion::factory()->for($assessment)->dimension('EI')->count(3)->create();
    AssessmentQuestion::factory()->for($assessment)->dimension('SN')->count(8)->create();
    $this->actingAs($admin);

    $response = $this->get(route('admin.assessments.questions', $assessment));

    $response->assertOk()->assertSee('11/32 pernyataan')->assertSee('EI 3/8')->assertSee('SN 8/8');
});
