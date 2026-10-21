<?php

namespace Database\Factories;

use App\Enums\ReleaseFormat;
use App\Models\Album;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Release>
 */
class ReleaseFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'album_id' => Album::factory(),
            'format' => ReleaseFormat::Cd,
            'label' => fake()->company(),
            'catalog_number' => fake()->bothify('???-####'),
            'country' => fake()->countryCode(),
            'release_year' => fake()->numberBetween(1950, 2026),
            'barcode' => fake()->ean13(),
            'mbid' => fake()->uuid(),
        ];
    }
}
