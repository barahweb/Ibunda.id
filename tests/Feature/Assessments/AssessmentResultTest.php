<?php

use App\Models\Assessment;
use App\Models\AssessmentAnswer;
use App\Models\AssessmentAttempt;
use App\Models\AssessmentQuestion;
use App\Models\User;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Sleep;
use Illuminate\Support\Str;
use Livewire\Volt\Volt;

function completedAssessmentAttempt(User $user, string $type = 'INTJ', ?array $scores = null): AssessmentAttempt
{
    $assessment = Assessment::factory()->published()->create(['title' => 'Tes OEJTS']);

    return AssessmentAttempt::factory()->for($assessment)->for($user)->completed($type, $scores)->create();
}

test('guest is redirected to login for result, history, and submissions pages', function () {
    $attempt = completedAssessmentAttempt(User::factory()->create());

    $this->get(route('assessments.attempts.result', $attempt))->assertRedirect(route('login'));
    $this->get(route('assessments.history'))->assertRedirect(route('login'));
    $this->get(route('admin.assessments.submissions', $attempt->assessment))->assertRedirect(route('login'));
});

test('a participant can view their own result with the type and every dimension', function () {
    $participant = User::factory()->create();
    $attempt = completedAssessmentAttempt($participant, 'ENTP', ['EI' => 18, 'SN' => 30, 'TF' => 22, 'JP' => 26]);

    $this->actingAs($participant)
        ->get(route('assessments.attempts.result', $attempt))
        ->assertOk()
        ->assertSee('ENTP')
        ->assertSee('Skor 18 dari 40')
        ->assertSee('Open Psychometrics');
});

test('dimensions are listed in the fixed order even if the stored json keys are reordered', function () {
    $participant = User::factory()->create();
    // Urutan key kayak yang dikembalikan MySQL: EI, JP, SN, TF.
    $attempt = completedAssessmentAttempt($participant, 'ENTP', ['EI' => 18, 'JP' => 26, 'SN' => 30, 'TF' => 22]);

    $this->actingAs($participant)
        ->get(route('assessments.attempts.result', $attempt))
        ->assertSeeInOrder(['Extraversion', 'Sensing', 'Thinking', 'Judging']);
});

test('a stranger cannot view someone else\'s result', function () {
    $attempt = completedAssessmentAttempt(User::factory()->create());

    $this->actingAs(User::factory()->create())
        ->get(route('assessments.attempts.result', $attempt))
        ->assertForbidden();
});

test('an admin can view any participant\'s result', function () {
    $attempt = completedAssessmentAttempt(User::factory()->create());

    $this->actingAs(User::factory()->admin()->create())
        ->get(route('assessments.attempts.result', $attempt))
        ->assertOk();
});

test('the result page is 404 for an attempt that is still in progress, even for the owner', function () {
    $participant = User::factory()->create();
    $attempt = AssessmentAttempt::factory()->for($participant)->create();

    $this->actingAs($participant)->get(route('assessments.attempts.result', $attempt))->assertNotFound();
});

test('unknown or malformed attempt ids return 404', function () {
    $this->actingAs(User::factory()->create());

    $this->get(route('assessments.attempts.result', (string) Str::uuid()))->assertNotFound();
    $this->get(route('assessments.attempts.result', 'bukan-uuid'))->assertNotFound();
});

test('the type description is shown when it exists in the config', function () {
    config(['oejts.types.INTJ' => ['name' => 'Sang Arsitek', 'description' => 'Deskripsi uji']]);
    $participant = User::factory()->create();
    $attempt = completedAssessmentAttempt($participant, 'INTJ');

    $this->actingAs($participant)
        ->get(route('assessments.attempts.result', $attempt))
        ->assertSee('Sang Arsitek')
        ->assertSee('Deskripsi uji');
});

test('history only lists the participant\'s own attempts', function () {
    $participant = User::factory()->create();
    $other = User::factory()->create();
    completedAssessmentAttempt($participant, 'ISFJ');
    completedAssessmentAttempt($other, 'ENFP');

    $this->actingAs($participant)
        ->get(route('assessments.history'))
        ->assertOk()
        ->assertSee('ISFJ')
        ->assertDontSee('ENFP');
});

test('history shows answered progress for an in-progress attempt and a result link for a finished one', function () {
    $participant = User::factory()->create();
    $assessment = Assessment::factory()->published()->create();
    $questions = AssessmentQuestion::factory()->for($assessment)->count(4)->create();
    $attempt = AssessmentAttempt::factory()->for($assessment)->for($participant)->create();
    AssessmentAnswer::factory()->for($attempt, 'attempt')->for($questions[0], 'question')->create(['value' => 3]);
    completedAssessmentAttempt($participant, 'ESTJ');

    $this->actingAs($participant)
        ->get(route('assessments.history'))
        ->assertOk()
        ->assertSee('1/4 terjawab')
        ->assertSee('Lihat Hasil')
        ->assertSee('Lanjutkan');
});

test('a participant cannot open the admin submissions page', function () {
    $assessment = Assessment::factory()->published()->create();

    $this->actingAs(User::factory()->create())
        ->get(route('admin.assessments.submissions', $assessment))
        ->assertForbidden();
});

test('admin sees every participant\'s attempt and the completed count', function () {
    $assessment = Assessment::factory()->published()->create();
    $done = User::factory()->create(['name' => 'Peserta Selesai']);
    $busy = User::factory()->create(['name' => 'Peserta Jalan']);
    AssessmentAttempt::factory()->for($assessment)->for($done)->completed('INFJ')->create();
    AssessmentAttempt::factory()->for($assessment)->for($busy)->create();

    $this->actingAs(User::factory()->admin()->create())
        ->get(route('admin.assessments.submissions', $assessment))
        ->assertOk()
        ->assertSee('Peserta Selesai')
        ->assertSee('Peserta Jalan')
        ->assertSee('INFJ')
        ->assertSee('Total Selesai');
});

test('unknown assessment id returns 404 on the admin submissions page', function () {
    $this->actingAs(User::factory()->admin()->create())
        ->get(route('admin.assessments.submissions', (string) Str::uuid()))
        ->assertNotFound();
});

test('scores outside the normal 8-40 range still render without breaking the bar', function () {
    $participant = User::factory()->create();
    $attempt = completedAssessmentAttempt($participant, 'ESTJ', ['EI' => 0, 'SN' => 45, 'TF' => 8, 'JP' => 40]);

    $this->actingAs($participant)
        ->get(route('assessments.attempts.result', $attempt))
        ->assertOk()
        ->assertSee('Skor 0 dari 40')
        ->assertSee('left: 0%')
        ->assertSee('left: 100%');
});

test('the result page offers a card download and only celebrates right after submitting', function () {
    $participant = User::factory()->create();
    $attempt = completedAssessmentAttempt($participant, 'INTJ', ['EI' => 30, 'SN' => 30, 'TF' => 10, 'JP' => 10]);
    $this->actingAs($participant);

    $this->get(route('assessments.attempts.result', $attempt))
        ->assertOk()
        ->assertSee('Unduh Kartu Hasil')
        ->assertSee('hasil-tes-kepribadian-intj.png', false)
        ->assertSee('celebrate: false', false);

    $this->get(route('assessments.attempts.result', ['attempt' => $attempt, 'baru' => 1]))
        ->assertSee('celebrate: true', false);
});

test('the radar chart shows the winning letter of each dimension with its strength', function () {
    $participant = User::factory()->create();
    $attempt = completedAssessmentAttempt($participant, 'ISTJ', ['EI' => 40, 'SN' => 8, 'TF' => 24, 'JP' => 8]);

    $this->actingAs($participant)
        ->get(route('assessments.attempts.result', $attempt))
        ->assertOk()
        ->assertSee('Grafik radar kecenderungan kepribadian ISTJ')
        ->assertSee('I &middot; Introversion', false)
        ->assertSee('S &middot; Sensing', false)
        ->assertSee('100%')
        ->assertSee('0%');
});

test('the owner can request an AI interpretation from the result page', function () {
    config(['services.ai.provider' => 'gemini', 'services.gemini.key' => 'test-key']);
    Http::fake(['generativelanguage.googleapis.com/*' => Http::response(['candidates' => [['content' => ['parts' => [['text' => 'Kamu suka rencana matang.']]]]]])]);
    $participant = User::factory()->create();
    $attempt = completedAssessmentAttempt($participant, 'INTJ', ['EI' => 30, 'SN' => 30, 'TF' => 10, 'JP' => 10]);
    $this->actingAs($participant);

    Volt::test('assessments.result', ['attempt' => $attempt])
        ->assertSee('Buat Interpretasi AI')
        ->call('generateInterpretation')
        ->assertSee('Kamu suka rencana matang.')
        ->assertDontSee('Buat Interpretasi AI');

    expect($attempt->fresh()->ai_interpretation)->toBe('Kamu suka rencana matang.');
});

test('the button is hidden when the API is not configured', function () {
    config(['services.ai.provider' => 'gemini', 'services.gemini.key' => null]);
    $participant = User::factory()->create();
    $attempt = completedAssessmentAttempt($participant, 'INTJ', ['EI' => 30, 'SN' => 30, 'TF' => 10, 'JP' => 10]);

    $this->actingAs($participant)
        ->get(route('assessments.attempts.result', $attempt))
        ->assertOk()
        ->assertDontSee('Interpretasi AI');
});

test('an admin sees an existing interpretation but cannot generate one for someone else', function () {
    config(['services.ai.provider' => 'gemini', 'services.gemini.key' => 'test-key']);
    Http::fake();
    $owner = User::factory()->create();
    $admin = User::factory()->admin()->create();
    $withText = completedAssessmentAttempt($owner, 'INTJ', ['EI' => 30, 'SN' => 30, 'TF' => 10, 'JP' => 10]);
    $withText->forceFill(['ai_interpretation' => 'Teks tersimpan.'])->save();
    $without = completedAssessmentAttempt($owner, 'ENTP', ['EI' => 10, 'SN' => 30, 'TF' => 10, 'JP' => 30]);
    $this->actingAs($admin);

    $this->get(route('assessments.attempts.result', $withText))->assertSee('Teks tersimpan.');
    $this->get(route('assessments.attempts.result', $without))->assertDontSee('Buat Interpretasi AI');

    Volt::test('assessments.result', ['attempt' => $without])->call('generateInterpretation')->assertForbidden();
    Http::assertNothingSent();
});

test('requesting interpretations is rate limited per user', function () {
    config(['services.ai.provider' => 'gemini', 'services.gemini.key' => 'test-key']);
    Sleep::fake();
    Http::fake(['generativelanguage.googleapis.com/*' => Http::response([], 500)]);
    $participant = User::factory()->create();
    $attempt = completedAssessmentAttempt($participant, 'INTJ', ['EI' => 30, 'SN' => 30, 'TF' => 10, 'JP' => 10]);
    $this->actingAs($participant);

    $component = Volt::test('assessments.result', ['attempt' => $attempt]);

    for ($i = 0; $i < 7; $i++) {
        $component->call('generateInterpretation');
    }

    Http::assertSentCount(10);
});
