<?php

use App\Models\User;

test('guests are redirected to the admin login page', function () {
    $this->get('/admin')->assertRedirect('/admin/login');
});

test('regular users cannot access the admin panel', function () {
    $this->actingAs(User::factory()->create(['is_admin' => false]))
        ->get('/admin')
        ->assertForbidden();
});

test('admins can access the admin panel', function () {
    $this->actingAs(User::factory()->create(['is_admin' => true]))
        ->get('/admin')
        ->assertOk();
});
