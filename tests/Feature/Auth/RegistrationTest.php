<?php

use App\Models\User;
use Illuminate\Support\Facades\Http;
use Livewire\Volt\Volt;

test('registration screen can be rendered', function () {
    $response = $this->get('/register');

    $response->assertStatus(200);
});

test('new users can register', function () {
    Http::fake([
        'https://challenges.cloudflare.com/*' => Http::response(['success' => true]),
    ]);

    $response = Volt::test('auth.register')
        ->set('name', 'Test User')
        ->set('email', 'test@example.com')
        ->set('password', 'password')
        ->set('password_confirmation', 'password')
        ->set('turnstileToken', 'fake-token')
        ->call('register');

    $response
        ->assertHasNoErrors()
        ->assertRedirect(route('dashboard', absolute: false));

    $this->assertAuthenticated();
});

test('registration is blocked when the captcha fails verification', function () {
    Http::fake([
        'https://challenges.cloudflare.com/*' => Http::response(['success' => false]),
    ]);

    $response = Volt::test('auth.register')
        ->set('name', 'Test User')
        ->set('email', 'test@example.com')
        ->set('password', 'password')
        ->set('password_confirmation', 'password')
        ->set('turnstileToken', 'fake-token')
        ->call('register');

    $response->assertHasErrors('turnstileToken');

    expect(User::where('email', 'test@example.com')->exists())->toBeFalse();
    $this->assertGuest();
});

test('rapid repeated registration attempts are rate limited', function () {
    $component = Volt::test('auth.register')
        ->set('name', 'Test User')
        ->set('email', 'invalid-email')
        ->set('password', 'password')
        ->set('password_confirmation', 'password');

    // Habiskan jatah rate limit-nya pakai submit yang gagal validasi (tetap dihitung).
    for ($i = 0; $i < 5; $i++) {
        $component->call('register');
    }

    // Percobaan berikutnya diblokir walau datanya valid, karena limitnya udah kepakai.
    $component
        ->set('email', 'blocked@example.com')
        ->call('register')
        ->assertHasErrors('email');

    expect(User::where('email', 'blocked@example.com')->exists())->toBeFalse();
    $this->assertGuest();
});
