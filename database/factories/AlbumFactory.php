<?php

namespace Database\Factories;

use App\Enums\AlbumType;
use App\Models\Artist;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Album>
 */
class AlbumFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'title' => fake()->words(3, true),
            'type' => AlbumType::Album,
            'first_release_year' => fake()->numberBetween(1950, 2026),
            'mbid' => fake()->uuid(),
        ];
    }

    /**
     * Attach a main artist to the album.
     */
    public function withArtist(): static
    {
        return $this->hasAttached(Artist::factory(), ['position' => 0], 'artists');
    }
}
