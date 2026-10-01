<?php

use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Support\Facades\Hash;

test('it creates a verified admin with a hashed password', function () {
    $this->artisan('app:create-admin', ['--name' => 'Admin Baru', '--email' => 'baru@example.com', '--password' => 'RahasiaKuat123!'])
        ->assertSuccessful();

    $admin = User::where('email', 'baru@example.com')->first();

    expect($admin->isAdmin())->toBeTrue();
    expect($admin->email_verified_at)->not->toBeNull();
    expect($admin->password)->not->toBe('RahasiaKuat123!');
    expect(Hash::check('RahasiaKuat123!', $admin->password))->toBeTrue();
});

test('it refuses a weak password and creates nothing', function () {
    $this->artisan('app:create-admin', ['--name' => 'Admin', '--email' => 'lemah@example.com', '--password' => '123'])
        ->assertFailed();

    expect(User::where('email', 'lemah@example.com')->exists())->toBeFalse();
});

test('an existing user is promoted without changing the password', function () {
    $user = User::factory()->create(['email' => 'ada@example.com', 'password' => 'PasswordLama123!']);
    $oldHash = $user->fresh()->password;

    $this->artisan('app:create-admin', ['--email' => 'ada@example.com'])->assertSuccessful();

    expect($user->fresh()->isAdmin())->toBeTrue();
    expect($user->fresh()->password)->toBe($oldHash);
});

test('the demo seeder is skipped in production unless explicitly allowed', function () {
    app()->detectEnvironment(fn () => 'production');

    (new DatabaseSeeder)->run();
    expect(User::count())->toBe(0);

    config(['app.seed_demo_data' => true]);
    (new DatabaseSeeder)->run();
    expect(User::where('email', 'admin@example.com')->exists())->toBeTrue();
});

test('it rejects a malformed email and is idempotent for an existing admin', function () {
    $this->artisan('app:create-admin', ['--name' => 'X', '--email' => 'bukan-email', '--password' => 'RahasiaKuat123!'])->assertFailed();
    expect(User::count())->toBe(0);

    $admin = User::factory()->admin()->create(['email' => 'sudah@example.com']);
    $this->artisan('app:create-admin', ['--email' => 'sudah@example.com'])->assertSuccessful();

    expect(User::where('email', 'sudah@example.com')->count())->toBe(1);
    expect($admin->fresh()->isAdmin())->toBeTrue();
});
