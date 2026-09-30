<?php

use App\Models\User;
use Livewire\Volt\Volt;

test('guest is redirected to login', function () {
    $this->get(route('admin.users.index'))->assertRedirect(route('login'));
});

test('a participant cannot access the user management page', function () {
    $participant = User::factory()->create();

    $response = $this->actingAs($participant)->get(route('admin.users.index'));

    $response->assertForbidden();
});

test('admin sees all users with their role', function () {
    $admin = User::factory()->admin()->create(['name' => 'Admin Utama']);
    $participant = User::factory()->create(['name' => 'Peserta Biasa']);

    $response = $this->actingAs($admin)->get(route('admin.users.index'));

    $response->assertOk()->assertSee('Admin Utama')->assertSee('Peserta Biasa');
});

test('the list can be filtered by name or email', function () {
    $admin = User::factory()->admin()->create();
    $this->actingAs($admin);
    User::factory()->create(['name' => 'Budi Santoso', 'email' => 'budi@example.com']);
    User::factory()->create(['name' => 'Citra Lestari', 'email' => 'citra@example.com']);

    Volt::test('admin.users.index')
        ->set('search', 'Budi')
        ->assertSee('Budi Santoso')
        ->assertDontSee('Citra Lestari');
});

test('the list can be filtered by role', function () {
    $admin = User::factory()->admin()->create();
    $this->actingAs($admin);
    $otherAdmin = User::factory()->admin()->create(['name' => 'Admin Lain']);
    User::factory()->create(['name' => 'Peserta Saja']);

    Volt::test('admin.users.index')
        ->set('role', 'admin')
        ->assertSee('Admin Lain')
        ->assertDontSee('Peserta Saja');
});

test('admin can promote a participant to admin', function () {
    $admin = User::factory()->admin()->create();
    $participant = User::factory()->create();
    $this->actingAs($admin);

    Volt::test('admin.users.index')->call('toggleRole', $participant->id);

    expect($participant->refresh()->isAdmin())->toBeTrue();
});

test('admin can demote another admin back to participant', function () {
    $admin = User::factory()->admin()->create();
    $otherAdmin = User::factory()->admin()->create();
    $this->actingAs($admin);

    Volt::test('admin.users.index')->call('toggleRole', $otherAdmin->id);

    expect($otherAdmin->refresh()->isAdmin())->toBeFalse();
});

test('the last remaining admin cannot be demoted', function () {
    $admin = User::factory()->admin()->create();
    $other = User::factory()->admin()->create();
    $other->update(['role' => User::ROLE_PARTICIPANT]);
    $this->actingAs($admin);

    Volt::test('admin.users.index')->call('toggleRole', $admin->id);

    expect($admin->refresh()->isAdmin())->toBeTrue();
});

test('an admin cannot change their own role', function () {
    $admin = User::factory()->admin()->create();
    User::factory()->admin()->create();
    $this->actingAs($admin);

    Volt::test('admin.users.index')
        ->call('toggleRole', $admin->id)
        ->assertForbidden();

    expect($admin->refresh()->isAdmin())->toBeTrue();
});
