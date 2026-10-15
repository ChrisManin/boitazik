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
        $admin = config('seeder.admin');
        $this->ensureCredentialsConfigured('admin', $admin);

        User::updateOrCreate(['email' => $admin['email']], [
            'name' => $admin['name'],
            'password' => Hash::make($admin['password']),
            'is_admin' => true,
        ]);

        // Utilisateur classique pour l'app Vue
        $user = config('seeder.user');
        $this->ensureCredentialsConfigured('user', $user);

        User::updateOrCreate(['email' => $user['email']], [
            'name' => $user['name'],
            'password' => Hash::make($user['password']),
            'is_admin' => false,
        ]);
    }

    /**
     * @param  array{name: string, email: ?string, password: ?string}  $credentials
     */
    private function ensureCredentialsConfigured(string $key, array $credentials): void
    {
        if (empty($credentials['email']) || empty($credentials['password'])) {
            throw new \RuntimeException(sprintf(
                'SEED_%s_EMAIL et SEED_%s_PASSWORD doivent être définies dans .env avant de lancer le seeder.',
                strtoupper($key),
                strtoupper($key),
            ));
        }
    }
}
