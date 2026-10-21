<?php

use App\Models\User;
use Database\Seeders\DemoCollectionSeeder;

beforeEach(function () {
    config(['seeder.user' => ['name' => 'Demo', 'email' => 'demo@example.test', 'password' => 'secret-password']]);
    User::factory()->create(['email' => 'demo@example.test']);
});

test('the demo seeder fills the collection of the regular user', function () {
    $this->seed(DemoCollectionSeeder::class);

    expect(User::firstWhere('email', 'demo@example.test')->collectionItems()->count())->toBeGreaterThan(24);
});

test('running the demo seeder twice does not duplicate items', function () {
    $this->seed(DemoCollectionSeeder::class);
    $count = User::firstWhere('email', 'demo@example.test')->collectionItems()->count();

    $this->seed(DemoCollectionSeeder::class);

    expect(User::firstWhere('email', 'demo@example.test')->collectionItems()->count())->toBe($count);
});
