<?php

namespace Database\Seeders;

// use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
	/**
	 * Seed the application's database.
	 */
	public function run(): void
	{
		// Admin pour Filament
		User::create([
			'name' => 'Admin Filament',
			'email' => 'admin@example.com',
			'password' => Hash::make('password'),
			'is_admin' => true,
		]);

		// Utilisateur classique pour l'app Vue
		User::create([
			'name' => 'User Vue',
			'email' => 'user@example.com',
			'password' => Hash::make('password'),
			'is_admin' => false,
		]);
	}
}
