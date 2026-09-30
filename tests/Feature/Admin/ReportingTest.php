<?php

use App\Models\Quiz;
use App\Models\QuizAttempt;
use App\Models\User;
use Livewire\Volt\Volt;

test('guest is redirected to login', function () {
    $this->get(route('admin.reports.index'))->assertRedirect(route('login'));
});

test('a participant cannot access the reports page', function () {
    $participant = User::factory()->create();

    $response = $this->actingAs($participant)->get(route('admin.reports.index'));

    $response->assertForbidden();
});

test('admin sees completed attempts from every quiz in one place', function () {
    $admin = User::factory()->admin()->create();
    $quizA = Quiz::factory()->published()->create(['title' => 'Quiz A']);
    $quizB = Quiz::factory()->published()->create(['title' => 'Quiz B']);
    $participant = User::factory()->create(['name' => 'Peserta Satu']);

    QuizAttempt::factory()->for($quizA)->for($participant)->completed(80)->create();
    QuizAttempt::factory()->for($quizB)->for($participant)->completed(60)->create();

    $response = $this->actingAs($admin)->get(route('admin.reports.index'));

    $response->assertOk()->assertSee('Quiz A')->assertSee('Quiz B')->assertSee('Peserta Satu');
});

test('the reports page can be filtered by participant name or email', function () {
    $admin = User::factory()->admin()->create();
    $this->actingAs($admin);
    $quiz = Quiz::factory()->published()->create();
    $match = User::factory()->create(['name' => 'Budi Santoso', 'email' => 'budi@example.com']);
    $other = User::factory()->create(['name' => 'Citra Lestari', 'email' => 'citra@example.com']);

    QuizAttempt::factory()->for($quiz)->for($match)->completed(80)->create();
    QuizAttempt::factory()->for($quiz)->for($other)->completed(70)->create();

    Volt::test('admin.reports.index')
        ->set('search', 'Budi')
        ->assertSee('Budi Santoso')
        ->assertDontSee('Citra Lestari');
});

test('the reports page can be filtered by quiz', function () {
    $admin = User::factory()->admin()->create();
    $this->actingAs($admin);
    $quizA = Quiz::factory()->published()->create(['title' => 'Quiz Filter A']);
    $quizB = Quiz::factory()->published()->create(['title' => 'Quiz Filter B']);
    $participant = User::factory()->create();

    QuizAttempt::factory()->for($quizA)->for($participant)->completed(80)->create();
    QuizAttempt::factory()->for($quizB)->for($participant)->completed(70)->create();

    // Judul quiz B tetap muncul di dropdown filter, jadi assertSee/assertDontSee ke teks
    // gak bisa dipakai di sini — cek langsung koleksi hasil query-nya.
    $attempts = Volt::test('admin.reports.index')
        ->set('quizId', $quizA->id)
        ->get('attempts');

    expect($attempts)->toHaveCount(1);
    expect($attempts->first()->quiz_id)->toBe($quizA->id);
});

test('the reports page can be filtered by pass/fail status', function () {
    $admin = User::factory()->admin()->create();
    $this->actingAs($admin);
    $quiz = Quiz::factory()->published()->create(['passing_score' => 70]);
    $passer = User::factory()->create(['name' => 'Peserta Lulus']);
    $failer = User::factory()->create(['name' => 'Peserta Gagal']);

    QuizAttempt::factory()->for($quiz)->for($passer)->completed(80)->create();
    QuizAttempt::factory()->for($quiz)->for($failer)->completed(40)->create();

    Volt::test('admin.reports.index')
        ->set('status', 'passed')
        ->assertSee('Peserta Lulus')
        ->assertDontSee('Peserta Gagal');
});

test('the summary stats reflect the current filters', function () {
    $admin = User::factory()->admin()->create();
    $this->actingAs($admin);
    $quiz = Quiz::factory()->published()->create(['passing_score' => 70]);
    $passer = User::factory()->create();
    $failer = User::factory()->create();

    QuizAttempt::factory()->for($quiz)->for($passer)->completed(80)->create();
    QuizAttempt::factory()->for($quiz)->for($failer)->completed(40)->create();

    $stats = Volt::test('admin.reports.index')->get('stats');

    expect($stats['total'])->toBe(2);
    expect($stats['average_score'])->toBe(60.0);
    expect($stats['gradable_total'])->toBe(2);
    expect($stats['passed'])->toBe(1);
});

test('admin can export the filtered report as csv', function () {
    $admin = User::factory()->admin()->create();
    $this->actingAs($admin);
    $quiz = Quiz::factory()->published()->create(['passing_score' => 70]);
    $participant = User::factory()->create(['name' => 'Peserta Export']);

    QuizAttempt::factory()->for($quiz)->for($participant)->completed(80)->create();

    Volt::test('admin.reports.index')
        ->call('export')
        ->assertFileDownloaded();
});
