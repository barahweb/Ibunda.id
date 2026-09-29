<?php

use App\Models\User;
use Illuminate\Support\Facades\Route;

beforeEach(function () {
    Route::get('/__test/admin-only', fn () => 'ok')->middleware(['auth', 'admin']);
});

test('guest is redirected to login', function () {
    $response = $this->get('/__test/admin-only');

    $response->assertRedirect(route('login'));
});

test('participant is forbidden', function () {
    $participant = User::factory()->create();

    $response = $this->actingAs($participant)->get('/__test/admin-only');

    $response->assertForbidden();
});

test('admin can access', function () {
    $admin = User::factory()->admin()->create();

    $response = $this->actingAs($admin)->get('/__test/admin-only');

    $response->assertOk()->assertSee('ok');
});
